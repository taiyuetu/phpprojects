<?php

/**
 * Build an absolute app URL from a root-relative path.
 * e.g. url('/customers/5/edit')
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

/** Currently logged-in user's array, or null. */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Format a number as currency. */
function money($amount): string
{
    return '$' . number_format((float) $amount, 2);
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
