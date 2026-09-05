<?php

/**
 * Build an absolute app URL from a root-relative path.
 * e.g. url('/customers/5/edit')
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
function url(string $path = ''): string
{
    $root = rtrim(URL_ROOT, '/');
    $path = '/' . ltrim($path, '/');
    return $root . $path;
}

/** HTML-escape shorthand for use in views. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Read & clear a flash message. */
function flash(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

/**
 * Runtime app setting (设置 → 应用信息), falling back to Setting::defaults()
 * and then to $fallback. Safe to call before the table exists.
 */
function appSetting(string $name, ?string $fallback = null): ?string
{
    try {
        $value = Setting::get($name, null);
    } catch (Throwable $e) {
        return $fallback;
    }
    return ($value === null || $value === '') ? $fallback : $value;
}

/** Editable application name (config.php's APP_NAME is only the default). */
function appName(): string
{
    return (string) appSetting('app_name', APP_NAME);
}

/**
 * Copyright line for the UI (sidebar bottom + login page).
 *
 * Editable as an app setting so a deployment can show its own legal entity
 * without touching code; source file headers carry the canonical notice.
 */
function appCopyright(): string
{
    return (string) appSetting('copyright_notice', APP_COPYRIGHT_UI);
}

/**
 * Character length of a UTF-8 string.
 *
 * mbstring is frequently missing from a "PHP + SQLite" install (this project
 * deliberately avoids it elsewhere), and strlen() would count bytes — treating
 * 中文 as 3x too long. mb_* when present, iconv as fallback, strlen as a last
 * resort.
 */
function textLength(string $text): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($text, 'UTF-8');
    }
    if (function_exists('iconv_strlen')) {
        $len = @iconv_strlen($text, 'UTF-8');
        if ($len !== false) {
            return $len;
        }
    }
    if (preg_match_all('/./us', $text, $m) !== false) {
        return count($m[0]);
    }
    return strlen($text);
}

/**
 * Clip a UTF-8 string to $limit characters, appending an ellipsis.
 * Replaces mb_strimwidth(), which needs the mbstring extension.
 */
function textClip(string $text, int $limit, string $ell = '…'): string
{
    return textLength($text) <= $limit
        ? $text
        : textTrim($text, max(0, $limit - textLength($ell))) . $ell;
}

/** Cut a UTF-8 string to $limit characters (see textLength()). */
function textTrim(string $text, int $limit): string
{
    if (textLength($text) <= $limit) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($text, 0, $limit, 'UTF-8');
    }
    if (function_exists('iconv_substr')) {
        $cut = @iconv_substr($text, 0, $limit, 'UTF-8');
        if ($cut !== false) {
            return $cut;
        }
    }
    return substr($text, 0, $limit);
}

/**
 * A user record resolved live from the users table.
 *
 * Ownership is stored as users.id everywhere, so this is the one place views go
 * to render "who is this person" (name / 职位 / phone / WhatsApp) — and it is
 * also what makes a profile edit show up on, e.g., a customer's 负责人.
 * Cached per request because list views call it once per row.
 */
function ownerInfo($userId): ?array
{
    static $cache = [];
    $userId = (int) $userId;
    if (!$userId) {
        return null;
    }
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    try {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, name, email, phone, whatsapp, job_title, role FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $user = null;
    }
    return $cache[$userId] = $user;
}

/** One-line "负责人" cell: name (+ 职位 when known). */
function ownerLabel($userId, string $emptyAs = '—'): string
{
    $user = ownerInfo($userId);
    if (!$user) {
        return e($emptyAs);
    }
    $label = $user['name'];
    if (!empty($user['job_title'])) {
        $label .= '（' . $user['job_title'] . '）';
    }
    return e($label);
}

/**
 * Detail-page "负责人" block: the owner's live profile (name, 职位, 联系方式).
 * Same shape as the other badge-returning view helpers, so templates stay tidy.
 */
function ownerBlock($userId, string $label = '负责人'): string
{
    $user = ownerInfo($userId);
    $out  = '<p class="mb-1"><i class="bi bi-person-badge me-2"></i>' . e($label) . '：';
    if (!$user) {
        return $out . '—</p>';
    }
    $out .= e($user['name']);
    if (!empty($user['job_title'])) {
        $out .= ' <span class="text-muted small">（' . e($user['job_title']) . '）</span>';
    }
    $out .= '</p>';

    $contact = [];
    if (!empty($user['phone'])) {
        $contact[] = '<i class="bi bi-telephone me-1"></i>' . e($user['phone']);
    }
    if (!empty($user['whatsapp'])) {
        $contact[] = '<i class="bi bi-whatsapp me-1"></i>' . e($user['whatsapp']);
    }
    if (!empty($user['email'])) {
        $contact[] = '<i class="bi bi-envelope me-1"></i>' . e($user['email']);
    }
    if ($contact) {
        $out .= '<p class="mb-1 small text-muted ms-4">' . implode(' · ', $contact) . '</p>';
    }
    return $out;
}

/** Currently logged-in user's array, or null. */
function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    // Resolve from the users table instead of trusting the login-time snapshot:
    // a profile edit in 设置 (or an admin fixing a colleague's account) must show
    // up in the topbar, in orders' 负责人 field and in role checks immediately.
    // User::identity() caches per request and is flushed after a write.
    try {
        $user = User::identity((int) $_SESSION['user_id']);
    } catch (Throwable $e) {
        $user = null;
    }
    if ($user === null) {
        return $_SESSION['user'] ?? null;   // DB unreachable / account deleted
    }
    $_SESSION['user'] = $user;
    return $user;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Check if currently logged in user is admin */
function isAdmin(): bool
{
    $user = currentUser();
    return ($user['role'] ?? '') === 'admin';
}

/** Check if currently logged in user can manage a specific resource (owner or admin, or unassigned) */
function canManageResource(?int $ownerId): bool
{
    if (!isLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    // If unassigned (public/null), all sales reps can view/edit/claim
    if ($ownerId === null || $ownerId === 0) {
        return true;
    }
    return (int) $ownerId === (int) ($_SESSION['user_id'] ?? 0);
}

/** Format a number as currency. */
/** Format a number as currency using the 货币符号 app setting. */
function money($amount): string
{
    return appSetting('currency_symbol', '$') . number_format((float) $amount, 2);
}

/** Format a date for display. */
function formatDate($date, string $format = 'M j, Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

/** Ensure a CSRF token exists in the session and return it (for use in views). */
function csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a Bootstrap-style badge for a status string. */
function statusBadge(string $status): string
{
    $map = [
        'new'         => 'secondary',
        'contacted'   => 'info',
        'qualified'   => 'primary',
        'lost'        => 'danger',
        'won'         => 'success',
        'active'      => 'success',
        'inactive'    => 'secondary',
        'open'        => 'primary',
        'proposal'    => 'info',
        'negotiation' => 'warning',
        'closed_won'  => 'success',
        'closed_lost' => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    $zhMap = [
            'new' => '新建', 'contacted' => '已联系', 'qualified' => '已确认',
            'lost' => '已流失', 'won' => '已成交', 'active' => '活跃',
            'inactive' => '非活跃', 'open' => '进行中', 'proposal' => '方案阶段',
            'negotiation' => '谈判中', 'closed_won' => '成交', 'closed_lost' => '丢单',
        ];
        $label = $zhMap[$status] ?? ucwords(str_replace('_', ' ', $status));
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}
