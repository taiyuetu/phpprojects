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
/**
 * 忽略大小写比较用的“小写化”。
 * 本机 PHP 没有装 mbstring，所以有就用、没有就退到 ASCII：
 * 商品搜索里中文本来就没有大小写，退化了也不影响结果。
 */
function textLower(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

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

// ------------------------------------------------------------ 时间（上海时区）

/**
 * 业务时区：整站的「现在」一律按上海时间（UTC+8）算，不跟服务器的 PHP 默认时区走。
 *
 * bootstrap.php 里虽然 date_default_timezone_set('Asia/Shanghai')，但 CLI 脚本、定时任务、
 * 以及默认时区被改动的部署不能靠「环境凑巧正确」——所以凡是要写进库的时间都显式带时区。
 * 注意库里的时间戳有两套来源：SQLite 的 datetime('now') 写的是 UTC（created_at 这类），
 * PHP 写的是上海时间（lost_at、conversion_time、lead_time、stage_*_at…）。
 * 本文件这几个函数只负责后者，并且永远输出 +08:00 的墙上时间。
 */
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Shanghai');
}

/** 上海时间的「现在」（默认 Y-m-d H:i:s，即 PHP 侧写库的标准格式）。 */
function appNow(string $format = 'Y-m-d H:i:s'): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format($format);
}

/** 中文数字 → int（认不出返回 null）：3 / 十五 / 二十 / 五十九 */
function appCnNumber(string $s): ?int
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    if (ctype_digit($s)) {
        return (int) $s;
    }
    $d = ['零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3,
          '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];
    if ($s === '十') {
        return 10;
    }
    if (preg_match('~^十(.)$~u', $s, $m)) {
        return isset($d[$m[1]]) ? 10 + $d[$m[1]] : null;
    }
    if (preg_match('~^(.)?十(.)?$~u', $s, $m)) {
        $tens = ($m[1] ?? '') === '' ? 1 : ($d[$m[1]] ?? null);
        $ones = ($m[2] ?? '') === '' ? 0 : ($d[$m[2]] ?? null);
        return ($tens === null || $ones === null) ? null : $tens * 10 + $ones;
    }
    return $d[$s] ?? null;
}

/**
 * 任意写法的时间 → 入库标准格式 `Y-m-d H:i:s`（上海时间）；认不出来返回 ''。
 *
 * 谁在写时间：页面上的 datetime-local 控件给的是 2026-09-07T14:30，AI 可能给
 * 2026-09-07 14:30、带时区的 ISO（…Z / +08:00 要换算成上海），也可能照抄用户口中的
 * 「昨天下午3点半」。历史数据里三种格式都出现过，所以入库前统一过这道门，
 * 页面回填控件与详情展示才只需面对一种格式。
 *
 * 相对日期（今天/下周五/3月5号）复用 Ai::parseDate()，日期类型与时间类型同一套换算。
 */
function appDateTime($raw): string
{
    $v = trim((string) $raw);
    if ($v === '' || str_starts_with($v, '0000-00-00')) {
        return '';
    }
    $tz = new DateTimeZone(APP_TIMEZONE);

    // 「刚才/现在」这类此刻语义
    if (preg_match('~^(刚才|刚刚|方才|现在|此刻|此时|就在刚刚|就在刚才|now)$~iu', $v)) {
        return appNow();
    }

    // 1) 纯 ASCII：ISO / 「Y-m-d H:i(:s)」 / 「Y-m-d\TH:i」 / 带时区偏移
    if (!preg_match('~[^\x00-\x7F]~', $v)) {
        try {
            $d = new DateTimeImmutable($v, $tz);      // 自带时区的以自带的为准，没带的按上海解释
        } catch (Throwable $e) {
            return '';
        }
        return appGuardYear($d->setTimezone($tz)->format('Y-m-d H:i:s'));
    }

    // 2) 含中文：先摘钟点，剩下的当日期
    $v = strtr($v, ['今早' => '今天早上', '今晚' => '今天晚上', '今宵' => '今天晚上',
                    '明早' => '明天早上', '明晚' => '明天晚上', '昨晚' => '昨天晚上',
                    '昨宵' => '昨天晚上', '后晚' => '后天晚上']);
    $clock = null;
    $rest = $v;
    if (preg_match('~(凌晨|清晨|早上|早晨|上午|中午|正午|下午|午后|傍晚|晚上|夜里|深夜)?\s*'
                 . '([0-9]{1,2}|[零一二两三四五六七八九十]{1,3})\s*(?:[:：]|点|时)\s*'
                 . '(?:([0-9]{1,2}|[零一二三四五六七八九十]{1,3})\s*分?|(半))?~u', $rest, $m)) {
        $hour = appCnNumber($m[2]);
        $minRaw = (string) ($m[3] ?? '');
        $minute = ($m[4] ?? '') === '半' ? 30 : ($minRaw === '' ? 0 : appCnNumber($minRaw));
        if ($hour !== null && $minute !== null) {
            $period = $m[1] ?? '';
            if (in_array($period, ['下午', '午后', '傍晚', '晚上', '夜里', '深夜'], true) && $hour < 12) {
                $hour += 12;                          // 下午3点 = 15
            } elseif ($period === '凌晨' && $hour === 12) {
                $hour = 0;
            } elseif ($period === '中午' && $hour === 1) {
                $hour = 13;
            }
            if ($hour <= 23 && $minute <= 59) {
                $clock = sprintf('%02d:%02d:00', $hour, $minute);
                $rest = trim(str_replace($m[0], ' ', $rest));
            }
        }
    }

    // 日期部分：没写就是今天；钟点没写就沿用了此刻的时分秒（中文说法都是相对现在的）
    $dayStr = appNow('Y-m-d');
    // 只剩「下午」这种光秃秃的时段词：当今天处理，别拿它去难为日期解析
    $rest = trim((string) preg_replace('~^(?:凌晨|清晨|早上|早晨|上午|中午|正午|下午|午后|傍晚|晚上|夜里|深夜)+$~u', '', $rest));
    if ($rest !== '') {
        $dayTs = class_exists('Ai') ? Ai::parseDate($rest) : strtotime($rest);
        if ($dayTs === false || $dayTs === -1) {
            return '';
        }
        $dayStr = date('Y-m-d', $dayTs);              // 与 parseDate 同一时区口径往返，不换算
    }
    try {
        $d = new DateTimeImmutable($dayStr . ' ' . ($clock ?? appNow('H:i:s')), $tz);
    } catch (Throwable $e) {
        return '';
    }
    return appGuardYear($d->format('Y-m-d H:i:s'));
}

/** 离谱的年份（1970、0000）当没解析出来：宁缺不错 */
function appGuardYear(string $std): string
{
    if ($std === '') {
        return '';
    }
    $y = (int) substr($std, 0, 4);
    return ($y < 1990 || $y > 2100) ? '' : $std;
}

/** 库里的时间 → <input type="datetime-local"> 认得的 2026-09-07T14:30；空/非法返回 ''。 */
function appDateTimeLocal($raw): string
{
    $std = appDateTime($raw);
    return $std === '' ? '' : str_replace(' ', 'T', substr($std, 0, 16));
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
