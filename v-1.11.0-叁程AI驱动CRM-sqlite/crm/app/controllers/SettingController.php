<?php

/**
 * 设置 — app information (admins) + the signed-in user's own profile.
 *
 * Two deliberately different write paths:
 *   - updateApp()     writes app_settings rows (system name, currency …) and is
 *                     admin-only, because it changes what everyone sees.
 *   - updateProfile() writes only the caller's own users row. Other records do
 *                     NOT store a copy of that person's name — customers, leads,
 *                     deals, orders, follow-ups and attachments all point at
 *                     users.id and JOIN the name back at read time. So saving a
 *                     profile is immediately reflected in 负责人 columns; the
 *                     page lists the affected records so the sync is visible.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class SettingController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $userId = (int) $_SESSION['user_id'];
        $userModel = $this->model('User');

        $this->view('settings/index', [
            'user'        => $userModel->find($userId) ?: [],
            'settings'    => Setting::values(),
            'definitions' => Setting::definitions(),
            'changes'     => (new Setting())->changes(),
            'references'  => $userModel->ownedReferences($userId),
            'secrets'     => Setting::secretState(),
            'aiConfig'    => AiClient::config(),
            'aiDiag'      => AiClient::diagnostics(),
            'tab'         => $this->activeTab(),
            'csrf'        => $this->csrfToken(),
        ]);
    }

    /** ---- 应用信息 (admin only) ---- */
    public function updateApp(): void
    {
        $this->requireRole('admin', '/settings');
        $this->verifyCsrf();

        $clean = Setting::sanitize($_POST);
        if ($clean['errors']) {
            $this->setFlash('error', implode(' ', $clean['errors']));
            $this->redirect('/settings?tab=app');
            return;
        }
        if (!$clean['values']) {
            $this->setFlash('error', '没有可保存的设置项。');
            $this->redirect('/settings?tab=app');
            return;
        }

        // The AI key is a secret: an empty box means "keep it", so clearing needs
        // an explicit checkbox, otherwise admins would wipe working keys by saving.
        if (!empty($_POST['ai_api_key_clear'])) {
            $clean['values']['ai_api_key'] = '';
        }
        unset($clean['values']['ai_api_key_clear']);

        (new Setting())->setMany($clean['values'], (int) $_SESSION['user_id']);
        $labels = Setting::definitions();
        $first = reset($clean['values']);
        $which = isset($_POST['ai_provider']) || isset($_POST['ai_enabled']) ? 'AI 设置' : '应用信息';
        $this->setFlash('success', $which . '已保存，并立即对全部页面生效。');
        $this->redirect('/settings?tab=' . ($which === 'AI 设置' ? 'ai' : 'app'));
    }

    /** Reset one or all app settings back to the code default. */
    public function resetApp(): void
    {
        $this->requireRole('admin', '/settings');
        $this->verifyCsrf();

        $key = trim((string) ($_POST['setting_key'] ?? ''));
        $defaults = Setting::defaults();
        $defs     = Setting::definitions();
        // 恢复默认按选项卡分组，且绝不因“重置”而默默吞掉已存的 API Key
        // （密钥的清除只走“清除已保存的 API Key”那个勾选框）。
        $group = ($_POST['setting_group'] ?? 'app') === 'ai' ? 'ai' : 'app';

        if ($key === 'all') {
            $reset = [];
            foreach (Setting::keysInGroup($group) as $name) {   // secrets excluded
                $reset[$name] = $defaults[$name];
            }
            (new Setting())->setMany($reset, (int) $_SESSION['user_id']);
            $this->setFlash('success', ($group === 'ai' ? 'AI 设置' : '应用信息') . '已恢复为默认值。');
            $this->redirect('/settings?tab=' . ($group === 'ai' ? 'ai' : 'app'));
            return;
        }

        if (isset($defaults[$key])) {
            (new Setting())->setMany([$key => $defaults[$key]], (int) $_SESSION['user_id']);
            $this->setFlash('success', '「' . $defs[$key]['label'] . '」已恢复为默认值。');
        } else {
            $this->setFlash('error', '未知的设置项。');
        }
        $this->redirect('/settings?tab=' . (($defs[$key]['group'] ?? 'app') === 'ai' ? 'ai' : 'app'));
    }

    /** ---- 个人信息 (self-service) ---- */
    public function updateProfile(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId = (int) $_SESSION['user_id'];
        $userModel = $this->model('User');
        $errors = $userModel->updateProfile($userId, $_POST);

        if ($errors) {
            $this->setFlash('error', implode(' ', $errors));
            $this->redirect('/settings?tab=profile');
            return;
        }

        // Make the new details live for this session too: the topbar, and forms
        // such as orders/_form.php, read currentUser()['name'].
        User::syncSession($userId);

        $affected = $userModel->ownedReferenceCount($userId);
        $this->setFlash('success', '个人信息已保存，已同步到 ' . $affected . ' 条记录（客户 / 线索 / 商机 / 订单的负责人等）。');
        $this->redirect('/settings?tab=profile');
    }

    /** ---- 修改密码 ---- */
    public function updatePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId = (int) $_SESSION['user_id'];
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $userModel = $this->model('User');
        $user = $userModel->find($userId);

        if (!$user || !$userModel->verifyPassword($current, $user['password'])) {
            $this->setFlash('error', '当前密码不正确。');
            $this->redirect('/settings?tab=password');
            return;
        }
        if (strlen($new) < 6) {
            $this->setFlash('error', '新密码至少需要 6 个字符。');
            $this->redirect('/settings?tab=password');
            return;
        }
        if ($new !== $confirm) {
            $this->setFlash('error', '两次输入的新密码不一致。');
            $this->redirect('/settings?tab=password');
            return;
        }

        $userModel->updatePassword($userId, $new);
        $this->setFlash('success', '密码已更新，下次登录请使用新密码。');
        $this->redirect('/settings?tab=password');
    }

    /** Which tab to open after a redirect (?tab=app|profile|password). */
    private function activeTab(): string
    {
        $tab = trim((string) ($_GET['tab'] ?? ''));
        if (in_array($tab, ['app', 'ai'], true) && !isAdmin()) {
            $tab = '';
        }
        return in_array($tab, ['app', 'ai', 'profile', 'password'], true) ? $tab : 'profile';
    }
}
