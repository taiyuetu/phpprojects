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
            $max   = (int) ($def['max'] ?? 255);
            if ($max > 0 && textLength($value) > $max) {
                $errors[] = $def['label'] . ' 最多 ' . $max . ' 个字符。';
                $value = textTrim($value, $max);
            }

            if (($def['type'] ?? 'text') === 'select') {
                $allowed = array_column($def['options'] ?? [], 'value');
                if (!in_array($value, $allowed, true)) {
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
