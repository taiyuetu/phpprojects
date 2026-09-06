<?php

/**
 * Transport for OpenAI-compatible chat endpoints.
 *
 * Why hand-rolled: this project runs on a plain "PHP + SQLite" install where the
 * curl extension is frequently missing (our own test suite hit exactly that), so
 * everything goes through PHP streams — the same approach tests/bootstrap.php
 * uses. No SDK, no Composer.
 *
 * Every value can be overridden by the environment, which lets a deployment keep
 * the API key out of the database entirely:
 *   AI_ENABLED / AI_PROVIDER / AI_MODEL / AI_BASE_URL / AI_API_KEY / AI_MODE
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class AiClient
{
    /** Wall-clock budget for one model call, in seconds (设置 → AI 助手 可调). */
    public const DEFAULT_TIMEOUT = 45.0;
    public const MAX_TIMEOUT     = 300.0;
    /** Give up early when the host is unreachable, instead of burning the budget. */
    public const CONNECT_TIMEOUT = 8.0;
    /** Keep this much room before PHP's own max_execution_time fires. */
    public const HEADROOM = 10;

    /** Test hook: callable(string $url, ?array $payload, string $key, float $timeout): array */
    public static $transport = null;

    /**
     * Can this PHP reach an https endpoint at all? Cloud providers are all https
     * and the stream wrapper needs the openssl extension — often absent from small
     * Windows PHP builds (this project's own machine included). Saying so up
     * front beats a vague "connection failed", and 本地 Ollama (http) keeps working.
     */
    public static function httpsAvailable(): bool
    {
        static $ok = null;
        if ($ok === null) {
            // Note: stream_get_transports() lists the *wrappers* ("ssl"/"tls"),
            // never the literal "https" — that is an URL scheme, not a transport.
            // Checking for 'https' here silently reported "unavailable" on a
            // perfectly working build, so look for the TLS transport instead.
            $transports = stream_get_transports();
            $ok = extension_loaded('openssl')
                && (in_array('ssl', $transports, true) || in_array('tls', $transports, true));
        }
        return $ok;
    }

    /**
     * PHP's own max_execution_time (30 s under php -S / Apache, and it counts the
     * model's thinking time) was fataling the page mid-request. Raise it for this
     * request, then hand back a stream timeout that always gives up first — so a
     * slow model becomes a readable error, not a Fatal error on the screen.
     */
    public static function allowTime(float $seconds): float
    {
        $need  = (int) ceil($seconds) + self::HEADROOM + 5;
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0 && $limit < $need) {
            @set_time_limit($need);
            $limit = $need;
        }
        return self::effectiveTimeout($seconds, $limit);
    }

    /**
     * Does this 4xx mean "I don't know that parameter"? Providers word it many ways,
     * so match the parameter name itself or a generic unknown-parameter phrase.
     */
    public static function rejectsParam(string $error, array $names): bool
    {
        $e = strtolower($error);
        foreach ($names as $name) {
            if (strpos($e, strtolower((string) $name)) !== false) {
                return true;
            }
        }
        return (bool) preg_match('~unknown|unrecognized|unexpected|invalid (request )?param|not supported~', $e);
    }

    /** What a "never got a response" outcome means, and how to fix it. */
    public static function noResponseError(string $url, float $timeout): string
    {
        $host = preg_replace('~https?://([^/]+).*~', '$1', $url);
        return (int) $timeout . ' 秒内没有收到 AI 响应（接口 ' . $host . '）：'
            . '可在 设置 → AI 助手 调大“响应超时”，或换更快的模型（flash 档）并调小“最大回复长度”。';
    }

    /** Pure half of allowTime(), so the margin is testable without set_time_limit(). */
    public static function effectiveTimeout(float $seconds, int $phpLimit): float
    {
        if ($phpLimit <= 0) {
            return max(1.0, $seconds);            // CLI: no script time limit at all
        }
        return max(5.0, min($seconds, $phpLimit - self::HEADROOM));
    }

    /** How to fix a missing https transport, for the 设置 page and error text. */
    public static function httpsFixHint(): string
    {
        return '当前 PHP 无法发起 https 请求（openssl 扩展未启用）：在 php.ini 取消 `;extension=openssl` 的注释并重启服务即可连接云端服务商；' .
               '不改的话仍可用本地 Ollama（http://127.0.0.1）或内置演示模型。';
    }

    /**
     * Per-request environment facts the 设置 page shows, so “测试连接失败” isn’t
     * a mystery: which model ids this build offers, and whether https works at all.
     */
    public static function diagnostics(): array
    {
        return [
            'https'          => self::httpsAvailable(),
            'https_hint'     => self::httpsAvailable() ? '' : self::httpsFixHint(),
            'transports'     => implode(', ', stream_get_transports()),
            'php'            => PHP_VERSION,
        ];
    }

    /** @return array<string,array<string,mixed>> provider key => metadata */
    public static function providers(): array
    {
        return [
            'mock' => [
                'label'         => '内置演示模型（离线，不联网）',
                'base'          => '',
                'default_model' => 'triphase-mock',
                'key_required'  => false,
            ],
            'ollama' => [
                'label'         => '本地 Ollama（OpenAI 兼容，数据不出本机）',
                'base'          => 'http://127.0.0.1:11434/v1',
                'default_model' => 'qwen2.5:7b',
                'key_required'  => false,
                'models'        => ['qwen2.5:7b', 'qwen2.5:14b', 'llama3.1:8b', 'deepseek-r1:7b'],
                'fast_params'   => ['think' => false],
            ],
            'openai' => [
                'label'         => 'OpenAI',
                'base'          => 'https://api.openai.com/v1',
                'default_model' => 'gpt-4o-mini',
                'key_required'  => true,
                'models'        => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini'],
            ],
            'deepseek' => [
                'label'         => 'DeepSeek（V4）',
                'base'          => 'https://api.deepseek.com',
                'default_model' => 'deepseek-v4-flash',
                'key_required'  => true,
                'models'        => ['deepseek-v4-flash', 'deepseek-v4-pro'],
                // V4 的正式版 ID（官方文档：base_url 不变，model 改成这两个）。
                // 旧的 deepseek-chat / deepseek-reasoner 仍可用，但不再是预设选项。
                // 实测（同一条「新建线索」指令）：默认带思考 3.5s 且只回一个空计划；
                // thinking=disabled 1.2s 且直接给出可执行的 create_lead —— 所以默认关掉思考。
                'fast_params'   => ['thinking' => ['type' => 'disabled']],
            ],
            'moonshot' => [
                'label'         => '月之暗面 Kimi',
                'base'          => 'https://api.moonshot.cn/v1',
                'default_model' => 'moonshot-v1-8k',
                'key_required'  => true,
                'models'        => ['moonshot-v1-8k', 'moonshot-v1-32k', 'kimi-latest'],
            ],
            'dashscope' => [
                'label'         => '阿里通义千问（百炼 DashScope 兼容模式）',
                'base'          => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                'default_model' => 'qwen3.8-flash',
                'key_required'  => true,
                'models'        => ['qwen3.8-max', 'qwen3.8-max-0902', 'qwen3.8-flash', 'qwen3.7-plus'],
                'fast_params'   => ['enable_thinking' => false],
            ],
            'zhipu' => [
                'label'         => '智谱 GLM',
                'base'          => 'https://open.bigmodel.cn/api/paas/v4',
                'default_model' => 'glm-4-flash',
                'key_required'  => true,
                'models'        => ['glm-4-flash', 'glm-4-plus'],
            ],
            'siliconflow' => [
                'label'         => '硅基流动 SiliconFlow',
                'base'          => 'https://api.siliconflow.cn/v1',
                'default_model' => 'Qwen/Qwen2.5-7B-Instruct',
                'key_required'  => true,
                'models'        => ['Qwen/Qwen2.5-7B-Instruct', 'deepseek-ai/DeepSeek-V3'],
            ],
            'custom' => [
                'label'         => '自定义 OpenAI 兼容端点',
                'base'          => '',
                'default_model' => '',
                'key_required'  => false,
            ],
        ];
    }

    /** Effective configuration: env override > 设置里的值 > 服务商默认. */
    public static function config(): array
    {
        $env = static function (string $name): ?string {
            $v = getenv($name);
            if ($v === false || $v === '') {
                $v = $_ENV[$name] ?? $_SERVER[$name] ?? '';
            }
            $v = trim((string) $v);
            return $v === '' ? null : $v;
        };

        $providerKey = $env('AI_PROVIDER') ?? (string) Setting::get('ai_provider', 'mock');
        if (!isset(self::providers()[$providerKey])) {
            $providerKey = 'mock';
        }
        $provider = self::providers()[$providerKey];

        $baseUrl = $env('AI_BASE_URL') ?? trim((string) Setting::get('ai_base_url', ''));
        if ($baseUrl === '') {
            $baseUrl = $provider['base'];
        }
        $apiKey = $env('AI_API_KEY') ?? (string) Setting::get('ai_api_key', '');

        return [
            'enabled'      => (($env('AI_ENABLED') ?? (string) Setting::get('ai_enabled', '0')) === '1'),
            'auto_apply'   => (($env('AI_MODE') ?? (string) Setting::get('ai_mode', 'preview')) === 'auto'),
            'provider'     => $providerKey,
            'label'        => $provider['label'],
            'needs_key'    => (bool) $provider['key_required'],
            'base_url'     => $baseUrl,
            'model'        => ($env('AI_MODEL') ?: trim((string) Setting::get('ai_model', ''))) ?: $provider['default_model'],
            'api_key'      => $apiKey,
            'key_from_env' => $env('AI_API_KEY') !== null,
            'temperature'  => (float) ((string) Setting::get('ai_temperature', '0.2') ?: 0.2),
            'timeout'      => max(5.0, min(self::MAX_TIMEOUT, (float) (Setting::get('ai_timeout', '') ?: self::DEFAULT_TIMEOUT))),
            'max_tokens'   => max(0, (int) (Setting::get('ai_max_tokens', '') ?: 800)),
            // “快速模式”：把思考型模型改成直接作答。关掉思考是本机实测最大的提速来源。
            'fast_mode'    => ($env('AI_FAST_MODE') ?? (string) Setting::get('ai_fast_mode', '1')) !== '0',
            'fast_params'  => is_array($provider['fast_params'] ?? null) ? $provider['fast_params'] : [],
            'suggest_models' => $provider['models'] ?? [],
        ];
    }

    /** Show a stored key without exposing it: first 3 + last 4 characters. */
    public static function maskKey(string $key): string
    {
        $len = strlen($key);
        if ($len === 0) {
            return '';
        }
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($key, 0, 3) . str_repeat('•', min(12, max(4, $len - 7))) . substr($key, -4);
    }

    /**
     * One chat completion.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,error?:string,model:string,latency_ms:int,usage?:array}
     */
    public static function chat(array $messages, ?array $override = null): array
    {
        $cfg = $override ? array_merge(self::config(), $override) : self::config();
        $t0  = microtime(true);
        $fail = static function (string $message) use ($cfg, $t0): array {
            return ['ok' => false, 'content' => '', 'error' => $message, 'model' => (string) $cfg['model'],
                    'latency_ms' => (int) round((microtime(true) - $t0) * 1000)];
        };

        if (!$cfg['enabled']) {
            return $fail('AI 助手未启用：请先在 设置 → AI 助手 里开启。');
        }
        if (($cfg['provider'] ?? '') === 'mock') {
            return $fail('演示模型由 Ai::complete() 在本地处理，不应到达 AiClient。');
        }
        $url = self::chatUrl((string) $cfg['base_url']);
        if (!$url['ok']) {
            return $fail($url['error']);
        }
        $endpoint = $url['url'];
        if ($cfg['needs_key'] && $cfg['api_key'] === '') {
            return $fail('缺少 API Key：请在 设置 → AI 助手 填写，或在 .env 里设置 AI_API_KEY。');
        }

        $payload = [
            'model'       => $cfg['model'],
            'messages'    => $messages,
            'temperature' => $cfg['temperature'],
            'stream'      => false,
        ];
        // Bound the answer: an unbounded completion is the single biggest cause of
        // a 30-60 s wait, and a plan needs far fewer tokens than a chat reply.
        if ((int) ($cfg['max_tokens'] ?? 0) > 0) {
            $payload['max_tokens'] = (int) $cfg['max_tokens'];
        }

        $note = '';
        $fast = !empty($cfg['fast_mode']) && !empty($cfg['fast_params']) ? (array) $cfg['fast_params'] : [];
        if ($fast) {
            $payload = array_merge($payload, $fast);
        }

        $timeout = self::allowTime((float) $cfg['timeout']);
        $res = self::postJson($endpoint, $payload, (string) $cfg['api_key'], $timeout);
        if (!$res['ok'] && $fast && self::rejectsParam((string) $res['error'], array_keys($fast))) {
            // The endpoint does not know the parameter (a proxy, an older gateway).
            // Fall back once instead of failing the user's request.
            $res = self::postJson($endpoint, array_diff_key($payload, array_flip(array_keys($fast))),
                (string) $cfg['api_key'], $timeout);
            $note = '（该接口不接受“快速模式”参数，已改用默认回复方式）';
        }
        $ms  = (int) round((microtime(true) - $t0) * 1000);

        if (!$res['ok']) {
            return ['ok' => false, 'content' => '', 'error' => self::redact((string) $res['error'], $cfg),
                    'model' => (string) $cfg['model'], 'latency_ms' => $ms];
        }


        $body = $res['json'];
        $text = $body['choices'][0]['message']['content'] ?? '';
        if (is_array($text)) {
            // Some gateways return content as [{type:'text', text:'…'}]
            $text = implode('', array_map(static fn($p) => (string) (is_array($p) ? ($p['text'] ?? '') : $p), $text));
        }
        if (trim((string) $text) === '') {
            // Thinking models (DeepSeek V4 with reasoning on, Qwen3 in thinking mode)
            // can leave `content` empty and put everything in `reasoning_content`.
            // Reading it back is better than reporting a silent model.
            $alt = $body['choices'][0]['message']['reasoning_content']
                ?? $body['choices'][0]['message']['reasoning']
                ?? $body['choices'][0]['delta']['content']
                ?? '';
            if (is_string($alt) && trim($alt) !== '') {
                $text = $alt;
            }
        }
        if (trim((string) $text) === '') {
            return ['ok' => false, 'content' => '', 'notice' => $note, 'error' => '模型返回了空内容。'
                    . ($fast ? '' : '（可在 设置 → AI 助手 开启“快速模式”，让思考型模型直接作答）'),
                    'model' => (string) $cfg['model'], 'latency_ms' => $ms];
        }

        return [
            'ok'         => true,
            'content'    => (string) $text,
            'model'      => (string) (($body['model'] ?? '') ?: $cfg['model']),
            'latency_ms' => $ms,
            'notice'     => $note,
            'usage'      => is_array($body['usage'] ?? null) ? $body['usage'] : [],
        ];
    }

    /**
     * Ask the endpoint which models it offers (设置 → 拉取模型列表).
     *
     * @return array{ok:bool,models?:array<int,string>,error?:string}
     */
    public static function listModels(?array $override = null): array
    {
        $cfg = $override ? array_merge(self::config(), $override) : self::config();
        $url = self::chatUrl((string) $cfg['base_url']);
        if (!$url['ok']) {
            return ['ok' => false, 'error' => $url['error']];
        }
        $modelsUrl = preg_replace('~/chat/completions$~', '', $url['url']) . '/models';
        $res = self::postJson($modelsUrl, null, (string) $cfg['api_key'], self::allowTime(15.0));
        if (!$res['ok']) {
            return ['ok' => false, 'error' => self::redact((string) $res['error'], $cfg)];
        }
        $ids = [];
        foreach ((array) ($res['json']['data'] ?? []) as $row) {
            if (!empty($row['id'])) {
                $ids[] = (string) $row['id'];
            }
        }
        sort($ids);
        return ['ok' => true, 'models' => $ids];
    }

    /**
     * Chat-completions URL built from the configured base, or an error when the
     * address is unusable (scheme allow-list + https outside localhost).
     *
     * @return array{ok:bool,url?:string,error?:string}
     */
    private static function chatUrl(string $base): array
    {
        $bad = static fn(string $why): array => ['ok' => false, 'error' => $why];

        if (trim($base) === '') {
            return $bad('缺少接口地址：请在 设置 → AI 助手 填写，或改用内置服务商。');
        }
        $parts = parse_url(trim($base));
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return $bad('接口地址不完整：需要类似 https://api.example.com/v1 的完整地址。');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $bad('接口地址只支持 http / https。');
        }
        // Credentials in the URL would end up in server logs — refuse them.
        if (isset($parts['user'])) {
            return $bad('接口地址不允许包含用户名/密码，请把凭据放在 API Key 里。');
        }
        $host  = strtolower((string) $parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
        if (!$local && $scheme !== 'https') {
            return $bad('非本机地址必须使用 https，避免密钥与客户资料在网络上明文传输。');
        }
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (!str_ends_with($path, '/chat/completions')) {
            $path .= '/chat/completions';
        }
        return ['ok' => true, 'url' => $scheme . '://' . $host
            . (isset($parts['port']) ? ':' . $parts['port'] : '') . $path];
    }

    /**
     * POST (or GET when $payload is null) JSON over PHP streams.
     *
     * @return array{ok:bool,json:array,error:string,status:int,raw:string}
     */
    private static function postJson(string $url, ?array $payload, string $key, float $timeout): array
    {
        if (is_callable(self::$transport)) {   // tests (and any custom transport) skip the checks below
            return call_user_func(self::$transport, $url, $payload, $key, $timeout)
                + ['json' => [], 'error' => '', 'status' => 0, 'raw' => ''];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Connection: close',
            'User-Agent: ' . APP_NAME . '/' . APP_VERSION . ' (AI assistant)',
        ];
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }

        $http = [
            'method'          => $payload === null ? 'GET' : 'POST',
            'header'          => implode("\r\n", $headers),
            'ignore_errors'   => true,      // we want the provider's error body
            'follow_location' => 0,
            'timeout'         => max(1.0, $timeout),
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'protocol_version' => 1.1,
        ];
        if ($payload !== null) {
            $content = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $http['content'] = $content;
            $http['header'] .= "\r\nContent-Length: " . strlen((string) $content);
        }

        if (str_starts_with($url, 'https://') && !self::httpsAvailable()) {
            // Fail fast with a fix instruction instead of a mystery "cannot connect".
            return ['ok' => false, 'json' => [], 'error' => self::httpsFixHint(), 'status' => 0, 'raw' => ''];
        }

        $raw = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        if ($raw === false) {
            $err = '';
            foreach (($http_response_header ?? []) as $line) {
                if (str_starts_with((string) $line, 'HTTP/')) {
                    $err = $line;
                }
            }
            // No status line at all = never got a response: unreachable or too slow.
            // Say which, and how to fix it, instead of a bare "connection failed".
            if ($err === '') {
                return ['ok' => false, 'json' => [], 'status' => 0, 'raw' => '', 'error' => self::noResponseError($url, $timeout)];
            }
            return ['ok' => false, 'json' => [], 'error' => '无法连接 AI 接口' . ($err ? "（{$err}）" : '')
                        . '：请检查接口地址、网络与超时设置。', 'status' => 0, 'raw' => ''];
        }

        $status = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $m)) {
                $status = (int) $m[1];
            }
        }
        $json = json_decode((string) $raw, true);
        if ($status >= 400) {
            $detail = '';
            if (is_array($json)) {
                $detail = (string) ($json['error']['message'] ?? $json['message'] ?? json_encode($json, JSON_UNESCAPED_UNICODE));
            }
            return ['ok' => false, 'json' => is_array($json) ? $json : [],
                    'error' => "AI 接口返回 HTTP {$status}" . ($detail ? "：{$detail}" : ''),
                    'status' => $status, 'raw' => (string) $raw];
        }
        if (!is_array($json)) {
            return ['ok' => false, 'json' => [], 'error' => 'AI 接口返回的内容不是合法 JSON。',
                    'status' => $status, 'raw' => (string) $raw];
        }
        return ['ok' => true, 'json' => $json, 'error' => '', 'status' => $status, 'raw' => (string) $raw];
    }

    /** Never let a key leak through an error message that echoed the request. */
    private static function redact(string $text, array $cfg): string
    {
        $out = $text;
        if (!empty($cfg['api_key'])) {
            $out = str_replace((string) $cfg['api_key'], self::maskKey((string) $cfg['api_key']), $out);
        }
        return textClip($out, 500);
    }
}
