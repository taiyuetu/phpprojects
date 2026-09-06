<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * 数据库结构的唯一读取入口（PRAGMA + sqlite_master）。
 *
 * 为什么要单独一个类：文档（AppMap）与 AI 的可写字段（Ai::fieldsFor）都必须来自
 * 真实表结构，而不是任何人手写的清单。若让 Ai 去调 AppMap，就会形成
 * AppMap::all() → aiTools() → Ai::tools() → AppMap::schema() → AppMap::all() 的死递归
 * （实测直接把进程吃到内存上限）。这个类只依赖 Database，谁都能安全地用它。
 *
 * 缓存是进程级的：一次请求内结构不会变；迁移后请调用 Schema::flush()。
 */
class Schema
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return string[] 全部业务表名（不含 sqlite_ 内部表） */
    public static function tableNames(): array
    {
        $rows = (new Database())->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->resultSet();
        return array_map(static fn($r) => (string) $r['name'], $rows);
    }

    /** 整库结构：表名 => ['columns','pk_columns','foreign','checks','indexes','rows'] */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $db = new Database();
        $out = [];
        foreach (self::tableNames() as $table) {
            $cols = $db->query('SELECT name, type, "notnull", dflt_value, pk FROM pragma_table_info(:t) ORDER BY cid')
                ->bind(':t', $table)->resultSet();
            $row = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name = :t")
                ->bind(':t', $table)->single();
            $sql = (string) ($row['sql'] ?? '');

            $checks = [];
            // CHECK (status IN ('a','b')) 里套着括号：只用 [^)]* 会在第一个 ")" 处截断，枚举值全丢
            if (preg_match_all('/CHECK\s*\(((?:[^()]|\([^()]*\))*)\)/i', $sql, $m)) {
                foreach ($m[1] as $c) {
                    $checks[] = (string) preg_replace('/\s+/', ' ', trim((string) $c));
                }
            }
            $foreign = [];
            if (preg_match_all('/FOREIGN KEY\s*\((\w+)\)\s*REFERENCES\s*(\w+)\s*\((\w+)\)([^,]*)/i', $sql, $m, PREG_SET_ORDER)) {
                foreach ($m as $f) {
                    $foreign[] = $f[1] . ' → ' . $f[2] . '.' . $f[3] . (trim((string) ($f[4] ?? '')) ? ' (' . trim($f[4]) . ')' : '');
                }
            }
            $indexes = [];
            foreach ($db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = :t AND sql IS NOT NULL")
                ->bind(':t', $table)->resultSet() as $ix) {
                $indexes[] = (string) $ix['name'];
            }
            try {
                $rows = (int) $db->query("SELECT COUNT(*) AS c FROM \"{$table}\"")->single()['c'];
            } catch (Throwable $e) {
                $rows = -1;
            }
            $out[$table] = [
                'columns'    => $cols,
                'primary'    => 'id',
                'pk_columns' => array_values(array_map(static fn($c) => (string) $c['name'],
                    array_filter($cols, static fn($c) => (int) $c['pk'] > 0))),
                'foreign'    => $foreign,
                'checks'     => $checks,
                'indexes'    => $indexes,
                'rows'       => $rows,
            ];
        }
        return self::$cache = $out;
    }

    /** @return array<int,array<string,mixed>> 列定义（name/type/notnull/dflt_value/pk） */
    public static function columns(string $table): array
    {
        return (array) (self::all()[$table]['columns'] ?? []);
    }

    public static function has(string $table): bool
    {
        return isset(self::all()[$table]);
    }

    /** 某列的定义，找不到返回 null */
    public static function column(string $table, string $column): ?array
    {
        foreach (self::columns($table) as $c) {
            if ((string) ($c['name'] ?? '') === $column) {
                return $c;
            }
        }
        return null;
    }

    /** @return array<string,string> "表.列" => "a|b|c"，来自 CHECK (col IN (...)) */
    public static function enums(): array
    {
        $out = [];
        foreach (self::all() as $table => $info) {
            foreach ($info['checks'] as $check) {
                if (preg_match("~^(\w+)\s+IN\s*\((.*)\)$~i", (string) $check, $m)) {
                    $vals = array_map(static fn($v) => trim((string) $v, " \n'"), explode(',', $m[2]));
                    $out[$table . '.' . $m[1]] = implode('|', $vals);
                }
            }
        }
        return $out;
    }

    /** 某表的枚举列：列名 => 值列表 */
    public static function enumsFor(string $table): array
    {
        $out = [];
        foreach (self::enums() as $key => $values) {
            [$t, $col] = explode('.', (string) $key, 2);
            if ($t === $table) {
                $out[$col] = explode('|', (string) $values);
            }
        }
        return $out;
    }
}
