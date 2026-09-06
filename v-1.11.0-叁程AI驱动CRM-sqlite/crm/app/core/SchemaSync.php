<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * 结构对齐：注册表（static::$fields）里声明了、但库里还没有的列 → ALTER 语句。
 *
 * 目的：加一个可写字段后，跑
 *     php database/schema-sync.php --table=products --apply
 * 就能把缺的列补进当前库（幂等：不缺列时什么都不做），其余交给
 *     php database/migrate.php
 * 的基线自愈与增量迁移。列的 SQL 类型从注册表的语义类型推导（安全默认值：
 * 新列允许 NULL / 有默认则带默认，避免 SQLite 对 ADD COLUMN 的限制）。
 *
 * 依赖方向与 Fields 一致：Schema + 各模型静态注册表，无递归。
 */
class SchemaSync
{
    /** 参与 diff 的表 = 建了注册表的表 */
    public static function tables(): array
    {
        return Fields::registeredTables();
    }

    /** 注册表里有、库里还没有的列：字段 => 声明元数据 */
    public static function missing(string $table): array
    {
        $declared = Fields::declaredFor($table);
        if ($declared === []) {
            return [];
        }
        $existing = [];
        foreach (Schema::columns($table) as $c) {
            $existing[(string) $c['name']] = true;
        }
        $missing = [];
        foreach ($declared as $name => $meta) {
            if (!isset($existing[$name])) {
                $missing[$name] = $meta;
            }
        }
        return $missing;
    }

    /** 语义类型 → SQLite 类型（安全默认：TEXT；数字 REAL；布尔/整型 INTEGER） */
    public static function sqlType(array $meta): string
    {
        $t = (string) ($meta['type'] ?? '');
        if ($t === 'number' || $t === 'money') {
            return 'REAL';
        }
        if ($t === 'bool' || $t === 'int') {
            return 'INTEGER';
        }
        return 'TEXT';
    }

    /** 加列子句（含默认值；新列一律不加 NOT NULL —— SQLite 的 ADD COLUMN 约束很死） */
    public static function addColumnClause(string $table, string $name, array $meta): string
    {
        $sql = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . self::sqlType($meta);
        if (array_key_exists('default', $meta)) {
            $d = $meta['default'];
            $lit = is_int($d) || is_float($d) || ctype_digit((string) $d) ? (string) $d
                 : "'" . str_replace("'", "''", (string) $d) . "'";
            $sql .= ' DEFAULT ' . $lit;
        }
        return $sql . ';';
    }

    /** 当前库为让结构跟上注册表要执行的全部 ALTER */
    public static function statements(string $table): array
    {
        $out = [];
        foreach (self::missing($table) as $name => $meta) {
            $out[] = self::addColumnClause($table, $name, $meta);
        }
        return $out;
    }

    /** 给 schema.sql 的 CREATE TABLE 用的列定义行（人工同步基线时粘贴，纯提示） */
    public static function schemaLine(string $name, array $meta): string
    {
        $sql = '    ' . $name . ' ' . self::sqlType($meta);
        if (array_key_exists('default', $meta)) {
            $d = $meta['default'];
            $lit = is_int($d) || is_float($d) || ctype_digit((string) $d) ? (string) $d
                 : "'" . str_replace("'", "''", (string) $d) . "'";
            $sql .= ' DEFAULT ' . $lit;
        }
        return $sql . ',';
    }
}
