<?php

/**
 * CRM staff accounts.
 *
 * The users row is the single source of truth for a person: every other table
 * (customers.owner_id, leads.owner_id, deals.owner_id, orders.owner_id,
 * follow_ups.user_id, activities.user_id, attachments.uploaded_by) stores only
 * an id, and reads the name back with a JOIN. So an edit made on the 设置 page
 * is reflected immediately everywhere — including "负责人" on customers —
 * without any copy/propagation step. User::ownedReferences() reports exactly
 * which records resolve to this person, and the 设置 page shows that list so
 * the sync is visible instead of implicit.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class User extends Model
{
    protected string $table = 'users';

    /** Fields a user may edit about themselves on the 设置 page. */
    public const PROFILE_FIELDS = ['name', 'email', 'phone', 'whatsapp', 'job_title', 'notes'];

    public function findByEmail(string $email)
    {
        return $this->findBy('email', $email);
    }

    /**
     * 按姓名找用户 id（AI 的「负责人」参数允许写姓名）。
     * 精确匹配优先；只有模糊匹配到唯一一个人时才用模糊结果——
     * 同名的人不能猜，猜错了就是把客户交到别人手里。
     */
    public function idByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $exact = $this->db()->query('SELECT id FROM users WHERE name = :n ORDER BY id LIMIT 1')
            ->bind(':n', $name)->single();
        if ($exact) {
            return (int) $exact['id'];
        }
        $like = $this->db()->query('SELECT id FROM users WHERE name LIKE :p ORDER BY id LIMIT 2')
            ->bind(':p', '%' . $this->escapeLike($name) . '%')->resultSet();
        return count($like) === 1 ? (int) $like[0]['id'] : 0;
    }

    /** 姓名里的通配符必须拆掉，否则一个 % 就能“模糊”到所有人 */
    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '[', ']'], '', $value);
    }

    public function register(string $name, string $email, string $password, string $role = 'sales'): int
    {
        return $this->create([
            'name'     => $name,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => $role,
        ]);
    }

    public function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Save profile fields; only whitelisted keys are written.
     *
     * @return array<int,string> validation errors (empty on success)
     */
    public function updateProfile(int $id, array $input): array
    {
        $data   = [];
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors[] = '姓名不能为空。';
        } elseif (textLength($name) > 60) {
            $errors[] = '姓名最多 60 个字符。';
        } else {
            $data['name'] = $name;
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            $errors[] = '邮箱不能为空。';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '邮箱格式不正确。';
        } elseif ($this->emailTakenByOther($email, $id)) {
            $errors[] = '该邮箱已被其他账号使用。';
        } else {
            $data['email'] = $email;
        }

        foreach (['phone' => 40, 'whatsapp' => 40, 'job_title' => 60] as $field => $max) {
            $value = trim((string) ($input[$field] ?? ''));
            if (textLength($value) > $max) {
                $errors[] = $this->fieldLabel($field) . ' 最多 ' . $max . ' 个字符。';
                $value = textTrim($value, $max);
            }
            $data[$field] = $value === '' ? null : $value;
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if (textLength($notes) > 500) {
            $errors[] = '备注最多 500 个字符。';
            $notes = textTrim($notes, 500);
        }
        $data['notes'] = $notes === '' ? null : $notes;

        if ($errors) {
            return $errors;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->update($id, $data);
        return [];
    }

    /** Change own password (caller must have verified the current one). */
    public function updatePassword(int $id, string $plainPassword): bool
    {
        return $this->update($id, [
            'password'   => password_hash($plainPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function emailTakenByOther(string $email, int $exceptId): bool
    {
        $row = $this->db()->query('SELECT id FROM users WHERE email = :e AND id <> :id')
            ->bind(':e', $email)->bind(':id', $exceptId, PDO::PARAM_INT)
            ->single();
        return (bool) $row;
    }

    /**
     * Which records currently resolve to this user as 负责人 / 操作人.
     *
     * Used by the 设置 page to show what a profile edit "syncs to"; the numbers
     * come from the same FK columns the JOINs use, so they cannot drift.
     *
     * @return array<int, array{label:string, count:int, url:string}>
     */
    public function ownedReferences(int $id): array
    {
        $map = [
            ['label' => '客户（负责人）',   'table' => 'customers',   'column' => 'owner_id',   'url' => '/customers'],
            ['label' => '线索（负责人）',   'table' => 'leads',       'column' => 'owner_id',   'url' => '/leads'],
            ['label' => '商机（负责人）',   'table' => 'deals',       'column' => 'owner_id',   'url' => '/deals'],
            ['label' => '订单（负责人）',   'table' => 'orders',      'column' => 'owner_id',   'url' => '/orders'],
            ['label' => '跟进记录（记录人）', 'table' => 'follow_ups', 'column' => 'user_id',    'url' => '/customers'],
            ['label' => '客户动态（操作人）', 'table' => 'activities', 'column' => 'user_id',    'url' => '/customers'],
            ['label' => '附件（上传人）',   'table' => 'attachments', 'column' => 'uploaded_by', 'url' => '/deals'],
        ];

        $out = [];
        foreach ($map as $item) {
            $label  = $item['label'];
            $table  = $item['table'];
            $column = $item['column'];
            $url    = $item['url'];
            $row = $this->db()->query("SELECT COUNT(*) AS total FROM {$table} WHERE {$column} = :id")
                ->bind(':id', $id, PDO::PARAM_INT)
                ->single();
            $out[] = [
                'label' => $label,
                'count' => (int) ($row['total'] ?? 0),
                'url'   => $url,
            ];
        }
        return $out;
    }

    /** Total number of records that reference this user. */
    public function ownedReferenceCount(int $id): int
    {
        $total = 0;
        foreach ($this->ownedReferences($id) as $item) {
            $total += $item['count'];
        }
        return $total;
    }

    /** Label for a profile field, used in error messages. */
    public static function fieldLabel(string $field): string
    {
        return [
            'name'     => '姓名',
            'email'    => '邮箱',
            'phone'    => '电话',
            'whatsapp' => 'WhatsApp',
            'job_title' => '职位',
            'notes'    => '备注',
        ][$field] ?? $field;
    }

    /** Refresh the per-request cache and the session copy after a profile edit. */
    public static function syncSession(int $id): void
    {
        self::flushIdentityCache();
        $user = self::identity($id);
        if ($user) {
            $_SESSION['user'] = $user;
        }
    }

    // --------------------------------------------------------------- identity

    /** @var array<int,array{id:int,name:string,email:string,role:string}|null> */
    private static array $identityCache = [];

    /**
     * The four fields the app shows about a person, resolved from users.
     *
     * Cached per request because list pages ask for the same owners over and
     * over, and flushed by syncSession() so a profile save is visible right away
     * — this is the read side of "a person's details live in exactly one place".
     */
    public static function identity(int $id): ?array
    {
        if (!array_key_exists($id, self::$identityCache)) {
            $row = null;
            try {
                $db = Database::connection();
                $stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $found = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($found) {
                    $row = [
                        'id'    => (int) $found['id'],
                        'name'  => (string) $found['name'],
                        'email' => (string) $found['email'],
                        'role'  => (string) $found['role'],
                    ];
                }
            } catch (Throwable $e) {
                $row = null;
            }
            self::$identityCache[$id] = $row;
        }
        return self::$identityCache[$id];
    }

    public static function flushIdentityCache(): void
    {
        self::$identityCache = [];
    }
}
