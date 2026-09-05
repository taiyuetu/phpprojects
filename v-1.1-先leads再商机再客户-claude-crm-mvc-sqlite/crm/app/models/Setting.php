<?php

/**
 * App-level settings (key/value rows in app_settings).
 *
 * Why a table and not config.php: config.php holds deploy-time constants
 * (DB path, APP_NAME fallback, env), while these values are edited by an admin
 * at runtime from the 设置 page — system name, tagline, currency symbol.
 *
 * Reading is cheap: values are loaded once per request into a static cache and
 * merged over Setting::defaults(), so a missing table (fresh checkout, tests)
 * or a missing row still returns a sane value instead of blowing up.
 *
 * Views should use the appSetting() / appName() helpers in core/helpers.php.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class Setting extends Model
{
    protected string $table = 'app_settings';

    /** @var array<string,string>|null per-request cache of merged values */
    private static ?array $cache = null;

    /**
     * Editable settings, in the order the 设置 form shows them.
     *
     * type: text | textarea | select
     * 'default' is used whenever the row is absent (never stores it).
     */
    public static function definitions(): array
    {
        return [
            'app_name' => [
                'label'    => '系统名称',
                'type'     => 'text',
                'default'  => APP_NAME,
                'max'      => 40,
                'required' => true,
                'hint'     => '显示在侧边栏、浏览器标题与登录页。',
            ],
            'app_tagline' => [
                'label'   => '系统副标题',
                'type'    => 'text',
                'default' => APP_TAGLINE,
                'max'     => 60,
                'hint'    => '显示在系统名称下方与登录页（可留空）。',
            ],
            'company_name' => [
                'label'   => '公司名称',
                'type'    => 'text',
                'default' => '',
                'max'     => 80,
                'hint'    => '显示在侧边栏底部（可留空）。',
            ],
            'copyright_notice' => [
                'label'   => '版权信息',
                'type'    => 'text',
                'default' => APP_COPYRIGHT_UI,
                'max'     => 120,
                'hint'    => '显示在侧边栏底部与登录页（部署给自家客户时，可换成贵司主体）。',
            ],
            'currency_symbol' => [
                'label'   => '货币符号',
                'type'    => 'select',
                'default' => '$',
                'options' => [
                    ['value' => '$',   'label' => '$  美元 / 通用'],
                    ['value' => 'US$', 'label' => 'US$  美元'],
                    ['value' => '¥',   'label' => '¥  人民币'],
                    ['value' => 'NT$', 'label' => 'NT$  新台币'],
                    ['value' => '€',   'label' => '€  欧元'],
                    ['value' => '£',   'label' => '£  英镑'],
                    ['value' => 'HK$', 'label' => 'HK$  港币'],
                    ['value' => '₩',   'label' => '₩  韩元'],
                    ['value' => '฿',   'label' => '฿  泰铢'],
                    ['value' => 'RM',  'label' => 'RM  令吉'],
                    ['value' => 'S$',  'label' => 'S$  新加坡元'],
                ],
                'hint' => '用于客户、线索、商机、订单等所有金额显示。',
            ],

            // ---------------- AI 助手（设置 → AI 助手）----------------
            // Providers, default endpoints and model lists live in AiClient so the
            // select below can never drift from what the client actually speaks.
            'ai_enabled' => [
                'group'   => 'ai',
                'label'   => '启用 AI 助手',
                'type'    => 'select',
                'default' => '0',
                'options' => [
                    ['value' => '0', 'label' => '关闭（默认）'],
                    ['value' => '1', 'label' => '开启'],
                ],
                'hint'    => '关闭时 /ai 页面与接口一律不可用，不会发出任何外部请求。',
            ],
            'ai_provider' => [
                'group'   => 'ai',
                'label'   => 'AI 服务商',
                'type'    => 'select',
                'default' => 'mock',
                'options' => [],          // filled by Setting::definitionOptions()
                'hint'    => '选本地 Ollama 时数据不出内网；选云端服务商则客户资料会发送给第三方，请先确认合规要求。',
            ],
            'ai_model' => [
                'group'   => 'ai',
                'label'       => '模型',
                'type'        => 'text',
                'default'     => '',
                'max'         => 80,
                'placeholder' => '留空则用服务商默认模型',
                'hint'        => '可点“拉取模型列表”看看该端点提供哪些模型。',
            ],
            'ai_base_url' => [
                'group'   => 'ai',
                'label'       => '接口地址',
                'type'        => 'text',
                'default'     => '',
                'max'         => 200,
                'placeholder' => 'https://…/v1（自建网关或兼容接口才需填写）',
                'hint'        => '只允许 http/https；非本机地址强制 https。',
            ],
            'ai_api_key' => [
                'group'   => 'ai',
                'label'   => 'API Key',
                'type'    => 'password',
                'secret'  => true,
                'default' => '',
                'max'     => 300,
                'hint'    => '仅存在服务端，页面不会回显；留空表示保持原值不变。更推荐写在 .env 的 AI_API_KEY（优先于此处填写的值）。',
            ],
            'ai_mode' => [
                'group'   => 'ai',
                'label'   => '执行方式',
                'type'    => 'select',
                'default' => 'preview',
                'options' => [
                    ['value' => 'preview', 'label' => '预览确认（默认：先列出改动，你点“确认执行”才写库）'],
                    ['value' => 'auto',    'label' => '自动执行（校验通过直接写库，仍记录审计）'],
                ],
                'hint'    => '无论哪种方式，AI 只能调用白名单工具，并受“只能操作自己负责的数据”限制。',
            ],
            'ai_timeout' => [
                'label'   => '响应超时',
                'group'   => 'ai',
                'type'    => 'select',
                'default' => '45',
                'options' => [
                    ['value' => '20',  'label' => '20 秒（快模型，超时更早报错）'],
                    ['value' => '45',  'label' => '45 秒（推荐）'],
                    ['value' => '90',  'label' => '90 秒（推理型/思考模型）'],
                    ['value' => '180', 'label' => '3 分钟（超大上下文）'],
                ],
                'hint'    => '等待模型回答的最长时间。到点会给出可读的错误提示，而不是让 PHP 把页面直接 Fatal。',
            ],
            'ai_max_tokens' => [
                'label'   => '最大回复长度',
                'group'   => 'ai',
                'type'    => 'select',
                'default' => '800',
                'options' => [
                    ['value' => '400',  'label' => '400 tokens（够小计划，最快）'],
                    ['value' => '800',  'label' => '800 tokens（推荐）'],
                    ['value' => '1600', 'label' => '1600 tokens（多步计划）'],
                    ['value' => '0',    'label' => '不限制（交给服务商，可能很慢）'],
                ],
                'hint'    => '限制模型输出长度：这是 AI 响应慢最常见的原因，计划 JSON 其实用不了多少 token。',
            ],
            'ai_fast_mode' => [
                'label'   => '快速模式',
                'group'   => 'ai',
                'type'    => 'select',
                'default' => '1',
                'options' => [
                    ['value' => '1', 'label' => '开（推荐：让模型直接产出计划）'],
                    ['value' => '0', 'label' => '关（保留模型的思考过程，慢很多）'],
                ],
                'hint'    => '思考型模型会先写一大段推理再回答：本机实测同一条“新建线索”指令，默认 3.5 秒且只回一个空计划，开启快速模式后 1.2 秒并给出可执行的 create_lead。'
                             . '服务商不支持该参数时会自动退回默认方式，不会报错。',
            ],
            'ai_allow_delete' => [
                'label'   => '允许 AI 删除数据',
                'group'   => 'ai',
                'type'    => 'select',
                'default' => '1',
                'options' => [
                    ['value' => '1', 'label' => '允许（仍需人工“确认执行”）'],
                    ['value' => '0', 'label' => '禁止（delete_* 工具会被拒绝）'],
                ],
                'hint'    => '删除能力的总开关。关闭后 AI 只能查询、新增、修改；开启时删除也永远不会自动执行——'
                             . '计划里先显示每条删除的理由与「连带影响」，你点“确认执行”才生效，且 ai_actions 留有被删记录快照。',
            ],
            'ai_temperature' => [
                'group'   => 'ai',
                'label'   => '创造力（temperature）',
                'type'    => 'select',
                'default' => '0.2',
                'options' => [
                    ['value' => '0',   'label' => '0    严格抽取'],
                    ['value' => '0.2', 'label' => '0.2  推荐（结构化操作）'],
                    ['value' => '0.5', 'label' => '0.5  文案摘要类'],
                    ['value' => '0.8', 'label' => '0.8  只建议用于写邮件草稿'],
                ],
                'hint'    => '做数据操作时越低越稳定。',
            ],
        ];
    }

    /** key => default value. */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::definitions() as $key => $def) {
            $out[$key] = (string) $def['default'];
        }
        return $out;
    }

    /**
     * Options for a setting, resolving the dynamic ones.
     *
     * ai_provider is declared with an empty list so it cannot drift from the
     * endpoints AiClient actually knows how to talk to.
     */
    public static function definitionOptions(string $name): array
    {
        $def = self::definitions()[$name] ?? [];
        if (!empty($def['options'])) {
            return $def['options'];
        }
        if ($name === 'ai_provider') {
            $out = [];
            foreach (AiClient::providers() as $key => $provider) {
                $out[] = ['value' => $key, 'label' => $provider['label']];
            }
            return $out;
        }
        return [];
    }

    /**
     * Setting keys belonging to one 设置 tab, optionally including secrets.
     * Lets "恢复默认" reset exactly that tab and nothing else — so wiping the
     * app info can never silently drop the AI configuration (or a stored key).
     */
    public static function keysInGroup(string $group, bool $includeSecrets = false): array
    {
        $out = [];
        foreach (self::definitions() as $name => $def) {
            if ((string) ($def['group'] ?? 'app') !== $group) {
                continue;
            }
            if (!$includeSecrets && !empty($def['secret'])) {
                continue;
            }
            $out[] = $name;
        }
        return $out;
    }

    /** Is this setting a secret (never echoed back to the browser)? */
    public static function isSecret(string $name): bool
    {
        return !empty(self::definitions()[$name]['secret']);
    }

    /** Drop the per-request cache (after a write, or between tests). */
    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /** All settings merged over defaults: stored rows win, missing rows fall back. */
    public static function values(): array
    {
        if (self::$cache === null) {
            self::$cache = self::defaults();
            try {
                $rows = (new self())->db()->query('SELECT name, value FROM app_settings')->resultSet();
            } catch (Throwable $e) {
                $rows = [];   // table not created yet — defaults keep the UI alive
            }
            foreach ($rows as $row) {
                // An empty stored value means "not customised" -> keep the code default
                // (e.g. so an accidentally cleared 货币符号 never prints amounts bare).
                $value = (string) ($row['value'] ?? '');
                if ($value !== '' && isset($row['name']) && array_key_exists($row['name'], self::$cache)) {
                    self::$cache[$row['name']] = $value;
                }
            }
        }
        return self::$cache;
    }

    /** Read one setting. */
    public static function get(string $name, ?string $fallback = null): ?string
    {
        $values = self::values();
        return $values[$name] ?? $fallback;
    }

    /**
     * Settings for rendering a form: secret values are blanked so an API key can
     * never end up in page source, and secret state is reported separately
     * through secretState().
     */
    public static function publicValues(): array
    {
        $values = self::values();
        foreach ($values as $name => $value) {
            if (self::isSecret($name)) {
                $values[$name] = '';
            }
        }
        return $values;
    }

    /** name => masked preview (or '' when unset) for every secret setting. */
    public static function secretState(): array
    {
        $out = [];
        foreach (self::definitions() as $name => $def) {
            if (empty($def['secret'])) {
                continue;
            }
            $raw = (string) (self::values()[$name] ?? '');
            $out[$name] = ['set' => $raw !== '', 'masked' => $raw === '' ? '' : AiClient::maskKey($raw)];
        }
        return $out;
    }

    /**
     * Validate + whitelist a submitted settings form.
     *
     * Unknown keys are ignored, so a form cannot invent rows; select values are
     * restricted to their declared options.
     *
     * @return array{values:array<string,string>, errors:array<int,string>}
     */
    public static function sanitize(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (self::definitions() as $key => $def) {
            if (!array_key_exists($key, $input)) {
                continue;   // not submitted in this form -> leave untouched
            }
            $value = trim((string) $input[$key]);

            // Secrets: an empty box means "keep what is stored"; a blanked box
            // must never wipe a working key by accident.
            if (!empty($def['secret']) && ($value === '' || str_contains($value, '已保存'))) {
                continue;
            }

            $max   = (int) ($def['max'] ?? 255);
            if ($max > 0 && textLength($value) > $max) {
                $errors[] = $def['label'] . ' 最多 ' . $max . ' 个字符。';
                $value = textTrim($value, $max);
            }

            if (($def['type'] ?? 'text') === 'select') {
                $allowed = array_column(self::definitionOptions($key), 'value');
                if ($allowed && !in_array($value, $allowed, true)) {
                    $errors[] = $def['label'] . ' 的取值不在可选范围内。';
                    $value = (string) $def['default'];
                }
            }

            if (!empty($def['required']) && $value === '') {
                $errors[] = $def['label'] . '不能为空。';
                $value = (string) $def['default'];
            }

            $values[$key] = $value;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Upsert several settings at once.
     *
     * @param array<string,string> $values
     */
    public function setMany(array $values, ?int $userId = null): bool
    {
        if (!$values) {
            return true;
        }
        $stmt = $this->db()->query(
            'INSERT INTO app_settings (name, value, updated_by, updated_at)
             VALUES (:name, :value, :by, datetime(\'now\'))
             ON CONFLICT(name) DO UPDATE
               SET value = excluded.value,
                   updated_by = excluded.updated_by,
                   updated_at = excluded.updated_at'
        );
        foreach ($values as $name => $value) {
            $stmt->bind(':name', $name);
            $stmt->bind(':value', (string) $value);
            $stmt->bind(':by', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            if (!$stmt->execute()) {
                return false;
            }
        }
        self::flushCache();
        return true;
    }

    /** Who last changed each setting + when (for the 设置 page audit line). */
    public function changes(): array
    {
        try {
            $rows = $this->db()->query(
                'SELECT s.name, s.updated_at, u.name AS updated_by_name
                 FROM app_settings s LEFT JOIN users u ON u.id = s.updated_by'
            )->resultSet();
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = $row;
        }
        return $out;
    }
}
