<?php

/**
 * AI 助手 — 自然语言 → 校验过的数据操作计划 → （预览确认后）写库。
 *
 * Flow: plan() asks the model for a JSON plan, validates it against the tool
 * whitelist and the caller's permissions, and stores it in ai_actions as
 * `pending`. In 预览确认 mode (the default) nothing is written until apply() is
 * clicked — and apply() re-validates, because the data may have changed while the
 * plan sat there. In 自动执行 mode plan() goes straight through. Both paths leave
 * an audit row, which is what /ai/history shows.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class AiController extends Controller
{
    /** Instruction length ceiling — keeps a request from shipping a novel. */
    private const MAX_INSTRUCTION = 6000;

    public function index(): void
    {
        $this->requireAuth();

        $model = $this->model('Ai');
        $planId = (int) ($_GET['plan'] ?? 0);
        $plan = $planId ? $model->find($planId) : null;
        // Only the author may see their own pending plan (admins may audit anyone's).
        if ($plan && (int) $plan['user_id'] !== (int) $_SESSION['user_id'] && !isAdmin()) {
            $plan = null;
        }

        $this->view('ai/index', [
            'config'  => AiClient::config(),
            'tools'   => Ai::tools(),
            'plan'    => $plan,
            'actions' => $plan ? Ai::validatePlan(Ai::planOf($plan)['actions'], (int) $_SESSION['user_id'])['actions'] : [],
            'recent'  => $model->history((int) $_SESSION['user_id'], false, 8),
            'csrf'    => $this->csrfToken(),
        ]);
    }

    /** 指令 → 计划（不写库，除非处于自动执行模式） */
    public function plan(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId = (int) $_SESSION['user_id'];
        $instruction = textClip(trim((string) ($_POST['instruction'] ?? '')), self::MAX_INSTRUCTION);
        if ($instruction === '') {
            $this->setFlash('error', '请先输入要让 AI 处理的内容。');
            $this->redirect('/ai');
            return;
        }

        $config = AiClient::config();
        if (!$config['enabled']) {
            $this->setFlash('error', 'AI 助手未启用，请联系管理员在 设置 → AI 助手 开启。');
            $this->redirect('/ai');
            return;
        }

        $model = $this->model('Ai');
        $result = Ai::complete($instruction);

        if (!$result['ok']) {
            // Failed calls are audited too: they explain why nothing happened.
            $id = $model->record($userId, $instruction, [
                'actions' => [], 'reply' => '', 'status' => 'failed', 'error' => $result['error'] ?? 'AI 调用失败',
                'latency_ms' => (int) ($result['latency_ms'] ?? 0),
            ], $config);
            $this->setFlash('error', 'AI 没有返回可执行的计划：' . ($result['error'] ?? '未知错误'));
            $this->redirect('/ai?plan=' . $id);
            return;
        }

        $checked = Ai::validatePlan($result['actions'], $userId);

        // 多轮里的查询已经真跑过了（只读工具不写库），把它们的结果合并进来：
        // 用户问“有哪些”，答案就在这些轮次里，不该只到最后一轮。
        $roundResults = [];
        foreach ((array) ($result['rounds'] ?? []) as $round) {
            foreach ((array) ($round['results'] ?? []) as $r) {
                $roundResults[] = $r;
            }
        }

        // 查询不是写操作：运行到这里的只读动作一并执行，只把写/删留给下一步确认。
        $readSteps  = array_values(array_filter($checked['actions'], static fn($a) => !empty($a['read'])));
        $writeSteps = array_values(array_filter($checked['actions'], static fn($a) => empty($a['read'])));
        $readRun = ($readSteps && !$checked['blocked']) ? Ai::execute($readSteps, $userId) : null;
        // 合计给确认页用：批量删除时人要看的是「6 条 + 连带 11 线索」，而不是数表格行数
        $summary = Ai::planSummary($checked['actions']);
        $allReads = array_merge($roundResults, $readRun ? (array) $readRun['results'] : []);

        $payload = [
            'actions'      => $writeSteps,
            'reply'        => (string) $result['reply'],
            'blocked'      => $checked['blocked'],
            'errors'       => $checked['errors'],
            'latency_ms'   => (int) ($result['latency_ms'] ?? 0),
            'read_results' => $allReads,
            'read_count'   => count($allReads),
            // 多轮查询的真实轨迹（第 1 轮查了什么、查到几条）与合计
            'rounds'       => (array) ($result['rounds'] ?? []),
            'summary'      => $summary,
        ];

        // 删除永远不自动执行，即使开了“自动执行”模式
        $destructive = Ai::hasDestructive($writeSteps);

        if ($config['auto_apply'] && !$checked['blocked'] && $writeSteps && !$destructive) {
            $id = $model->record($userId, $instruction, $payload + ['status' => 'pending'], $config);
            $run = Ai::execute($writeSteps, $userId, $id);
            $model->finish($id, $run['refused'] && !$run['applied'] ? 'failed' : 'executed', $run['results'],
                $run['refused'] ? '部分操作被拒绝' : null);
            $this->setFlash($run['applied'] ? 'success' : 'error',
                'AI 已自动执行：' . $run['message'] . '（详见“操作记录”）');
            $this->redirect('/ai?plan=' . $id);
            return;
        }

        // 只有查询（包括多轮里查完就给出答案的）：本次请求已经结束，不留下待确认计划
        if (!$writeSteps && !$checked['blocked']) {
            $id = $model->record($userId, $instruction, $payload + ['status' => 'pending'], $config);
            $model->finish($id, $allReads ? 'executed' : 'cancelled', $allReads, null);
            $this->setFlash('success', $allReads
                ? '查询完成：共 ' . count($allReads) . ' 项查询已执行（本次未改动任何数据）。'
                    . (trim((string) $result['reply']) !== '' ? ' ' . textClip((string) $result['reply'], 200) : '')
                : ($result['reply'] !== '' ? $result['reply'] : 'AI 认为无需改动数据。'));
            $this->redirect('/ai?plan=' . $id);
            return;
        }

        // A plan that failed validation is stored as a failure, not as something
        // someone could accidentally confirm later.
        $id = $model->record($userId, $instruction, $payload + [
            'status' => $checked['blocked'] ? 'failed' : 'pending',
            'error'  => $checked['blocked'] ? textClip(implode('；', $checked['errors']), 900) : null,
        ], $config);
        if ($checked['blocked']) {
            $this->setFlash('error', '计划中有 ' . count($checked['errors']) . ' 项未通过校验，已阻止执行。');
        } elseif ($destructive) {
            $bits = [];
            foreach ($summary['cascade'] as $cname => $cn) {
                $bits[] = $cname . ' ' . $cn;
            }
            $this->setFlash('warning', '将删除 ' . $summary['delete'] . ' 条记录'
                . ($bits ? '，连带 ' . implode('、', $bits) : '')
                . '（合计约 ' . $summary['total'] . ' 行数据）。按安全规则删除不会自动执行，'
                . '请核对下面每一条的「将删除」与理由，再点“确认执行”。');
        } elseif (!empty($payload['read_count'])) {
            $this->setFlash('success', '查询已执行（' . $payload['read_count'] . ' 项），另有 ' . count($writeSteps) . ' 项写操作待你确认。');
        } else {
            $this->setFlash('success', '已生成 ' . count($writeSteps) . ' 项待确认操作，请核对后点“确认执行”。');
        }
        $this->redirect('/ai?plan=' . $id);
    }

    /** 人工确认后才真正写库 */
    public function apply(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId = (int) $_SESSION['user_id'];
        $id = (int) ($_POST['id'] ?? 0);
        $model = $this->model('Ai');

        $row = $model->pendingFor($id, $userId);
        if (!$row) {
            $this->setFlash('error', '找不到待执行的计划（可能已被执行或取消）。');
            $this->redirect('/ai');
            return;
        }

        // Re-validate: permissions and data may have changed since the plan was made.
        $checked = Ai::validatePlan(Ai::planOf($row)['actions'], $userId);
        if ($checked['blocked']) {
            $model->finish($id, 'invalid', array_map(static fn($a) => [
                'tool' => $a['tool'], 'ok' => false, 'skipped' => true, 'message' => implode('；', $a['errors']),
            ], $checked['actions']), implode('；', $checked['errors']));
            $this->setFlash('error', '执行前复查未通过：' . implode('；', array_slice($checked['errors'], 0, 3)));
            $this->redirect('/ai?plan=' . $id);
            return;
        }

        // $id is passed in so a delete_ai_request cannot erase the very row being executed
        $run = Ai::execute($checked['actions'], $userId, $id);
        $model->finish($id, $run['applied'] ? 'executed' : 'failed', $run['results'],
            $run['refused'] ? "拒绝 {$run['refused']} 项" : null);

        $detail = [];
        foreach ($run['results'] as $r) {
            $detail[] = ($r['ok'] ?? false ? '✓ ' : '✗ ') . ($r['message'] ?? '');
        }
        $this->setFlash($run['applied'] ? 'success' : 'error', 'AI 操作完成：' . $run['message']
            . ($detail ? '｜' . implode('；', array_slice($detail, 0, 3)) : ''));
        $this->redirect('/ai?plan=' . $id);
    }

    public function cancel(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $model = $this->model('Ai');
        $row = $model->pendingFor($id, (int) $_SESSION['user_id']);
        if ($row) {
            $model->finish($id, 'cancelled', [], '用户取消');
            $this->setFlash('success', '已取消该计划，未改动任何数据。');
        } else {
            $this->setFlash('error', '找不到待取消的计划。');
        }
        $this->redirect('/ai');
    }

    /** Delete one AI request record (own records only, unless admin). */
    public function destroyRequest(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId = (int) $_SESSION['user_id'];
        $model  = $this->model('Ai');
        $row    = $model->find((int) $id);

        if (!$row) {
            $this->setFlash('error', '记录不存在（可能已被删除）。');
            $this->redirect('/ai/history');
            return;
        }
        if ((int) $row['user_id'] !== $userId && !isAdmin()) {
            $this->setFlash('error', '只能删除自己发起的 AI 请求记录。');
            $this->redirect('/ai/history');
            return;
        }

        // The row is gone, so keep a trace in the server log: what it was and who removed it
        error_log('[ai] request #' . (int) $id . ' deleted by user ' . $userId
            . ' (status=' . $row['status'] . ', instruction=' . textClip((string) $row['instruction'], 60) . ')');
        $model->delete((int) $id);
        $this->setFlash('success', 'AI 请求记录已删除。');
        $this->redirect('/ai/history');
    }

    public function history(): void
    {
        $this->requireAuth();

        $this->view('ai/history', [
            'rows'   => $this->model('Ai')->history((int) $_SESSION['user_id'], isAdmin(), 50),
            'config' => AiClient::config(),
            'csrf'   => $this->csrfToken(),
        ]);
    }

    /** 连接测试（管理员，从 设置 → AI 助手 触发） */
    public function test(): void
    {
        $this->requireRole('admin', '/settings?tab=ai');
        $this->verifyCsrf();

        $config = AiClient::config();
        if ($config['provider'] === 'mock') {
            $probe = Ai::complete('演示：新建线索 联系人 王小明 公司 演示科技 邮箱 demo@example.com 来源 Website');
            $this->setFlash($probe['ok'] ? 'success' : 'error', $probe['ok']
                ? '演示模型正常：离线可生成 ' . count($probe['actions']) . ' 项计划（正式使用请在上面切换服务商并填写 API Key）。'
                : '演示模型异常：' . ($probe['error'] ?? '未知错误'));
            $this->redirect('/settings?tab=ai');
            return;
        }

        // Ask for the model list first: cheapest round trip that proves the key works.
        $models = AiClient::listModels();
        if ($models['ok']) {
            $list = array_slice($models['models'], 0, 6);
            $this->setFlash('success', '连接成功，该端点提供 ' . count($models['models']) . ' 个模型：'
                . implode(', ', $list) . (count($models['models']) > 6 ? ' …' : ''));
            $this->redirect('/settings?tab=ai');
            return;
        }

        // Some providers don't expose /models — fall back to a 1-token chat.
        $chat = AiClient::chat([['role' => 'user', 'content' => 'Reply with the single word: ok']]);
        if ($chat['ok']) {
            $this->setFlash('success', '连接成功（' . $chat['model'] . '，' . $chat['latency_ms'] . 'ms）：'
                . textClip(trim((string) $chat['content']), 60));
        } else {
            $this->setFlash('error', '连接失败：' . ($chat['error'] ?? $models['error'] ?? '未知错误'));
        }
        $this->redirect('/settings?tab=ai');
    }
}
