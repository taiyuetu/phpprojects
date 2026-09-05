<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * 字段语义层：把"数据库只有的类型"补上"业务语义"，一处声明、多处派生。
 *
 * 背景（为什么要这一层）：
 *   Schema 只回答"有什么列、什么类型、什么约束"——它读不到"这一列中文叫什么、
 *   要不要进关键词搜索、表单给什么控件、CSV 里叫什么"这类语义。于是这些语义过去
 *   散落在 Ai::columnLabels()、各 Controller 的 validate()、_form.php、csvColumns()
 *   等地方，加一个字段要同时改好几处，漏一处就出现"表单能填、导入报错"。
 *
 * 做法：
 *   每个 Model 用一行 `protected static array $fields` 声明它的**稀疏语义**——
 *   只写数据库推不出来的部分（label / searchable / type / options / form / csv…）。
 *   本类负责把声明与 Schema 实表合并成每列的完整描述，并提供派生查询：
 *
 *     Fields::columns(...)            列 → 完整描述（label/type/options/可空…）
 *     Fields::searchableColumns(...)  关键词搜索扫哪些列（显式声明优先，否则自动推断）
 *
 *   依赖方向刻意单向：Fields → Schema，绝不去碰业务 Model / Ai，因此任何消费方
 *   调它都不会形成 AppMap→Ai→… 那种死递归。数据库结构仍然是唯一权威，声明只
 *   是"加语义"，两边不一致（声明里有库里没有的列）由 FieldsTest 兜底抓出。
 */
class Fields
{
    /** 已建立字段语义注册表的表 → 拥有注册表的 Model 类（class_exists 会自动加载类文件） */
    private const REGISTERED = [
        'customers' => Customer::class,
        'leads'     => Lead::class,
        'deals'     => Deal::class,
        'orders'    => Order::class,
        'products'  => Product::class,
    ];

    /** 已建立字段语义注册表的全部表（schema-sync 用它跑全库 diff） */
    public static function registeredTables(): array
    {
        return array_keys(self::REGISTERED);
    }

    /** 某表在对应 Model 里声明的稀疏语义（没有注册表的表返回 []） */
    public static function declaredFor(string $table): array
    {
        $cls = self::REGISTERED[$table] ?? null;
        return $cls !== null ? (array) $cls::fieldDefsStatic() : [];
    }

    /**
     * 某列的可选值（注册表 options > DB CHECK 枚举 > 模型 fieldEnumOptions 钩子）。
     * 返回 null 表示没有可选值约束。
     */
    public static function optionsFor(string $table, string $field): ?array
    {
        $declared = self::declaredFor($table);
        $desc = self::columns($table, $declared)[$field] ?? null;
        if ($desc !== null && isset($desc['options'])) {
            return array_map('strval', (array) $desc['options']);
        }
        $cls = self::REGISTERED[$table] ?? null;
        if ($cls !== null && is_subclass_of($cls, Model::class)) {
            $options = (new $cls())->fieldEnumOptions($field);
            if ($options) {
                return array_map('strval', (array) $options);
            }
        }
        return null;
    }

    /** 某表的列描述：DB 列（Schema）为主干，覆盖上模型声明的语义。 */
    public static function columns(string $table, array $declared = []): array
    {
        $enums = Schema::enumsFor($table);
        $out = [];
        foreach (Schema::columns($table) as $c) {
            $name = (string) ($c['name'] ?? '');
            $extra = $declared[$name] ?? [];
            $pk = (int) ($c['pk'] ?? 0) > 0;
            $notNull = (int) ($c['notnull'] ?? 0) === 1;

            $desc = [
                'name'        => $name,
                'label'       => (string) ($extra['label'] ?? $name),
                'type'        => self::resolveType($name, (string) ($c['type'] ?? ''), $enums, $extra),
                'pk'          => $pk,
                'notnull'     => $notNull,
                'dflt_value'  => $c['dflt_value'] ?? null,
                // 有没有"不带值也能 INSERT"的能力：NOT NULL 但无默认值的列才真正必填
                'nullable'    => !$notNull || ($c['dflt_value'] ?? null) !== null,
            ];
            if (isset($extra['options'])) {
                $desc['options'] = $extra['options'];
            } elseif ($desc['type'] === 'enum' && isset($enums[$name])) {
                $desc['options'] = $enums[$name];      // CHECK 约束里的枚举自动带出
            }
            if (isset($extra['searchable'])) {
                $desc['searchable'] = (bool) $extra['searchable'];
            }
            // 后续阶段（表单/校验/AI/CSV）会用到的语义原样透传，引擎不做解释
            foreach (['form', 'csv', 'hint', 'required', 'max', 'unique', 'rules'] as $k) {
                if (array_key_exists($k, $extra)) {
                    $desc[$k] = $extra[$k];
                }
            }
            $out[$name] = $desc;
        }
        return $out;
    }

    /**
     * 这张表的关键词搜索列。
     *
     * 优先取注册表里显式标了 searchable 的列（顺序=声明顺序）；一条都没标时
     * 退回自动推断（文本类列，剔除 id/编号/时间戳/布尔/金额/枚举），与历史
     * 行为完全一致 —— 给表加一个普通文本列，不声明也会被搜到。
     *
     * @param array<string,array<string,mixed>> $declared Model::$fields
     * @return string[]
     */
    public static function searchableColumns(string $table, array $declared = []): array
    {
        $flagged = [];
        foreach ($declared as $name => $meta) {
            if (!empty($meta['searchable'])) {
                $flagged[] = $name;
            }
        }
        if ($flagged !== []) {
            // 只保留真实存在的列，声明写错列名不报错，但也不会悄悄搜一个不存在的列
            $real = [];
            foreach (Schema::columns($table) as $c) {
                $real[(string) $c['name']] = true;
            }
            return array_values(array_filter($flagged, static fn($n) => isset($real[$n])));
        }
        return self::inferSearchableColumns($table);
    }

    /** 自动推断（原 Model::searchableColumns 的逻辑，原样搬来保证行为不变） */
    private static function inferSearchableColumns(string $table): array
    {
        $enums = Schema::enumsFor($table);
        $cols = [];
        foreach (Schema::columns($table) as $c) {
            $name = (string) ($c['name'] ?? '');
            if ($name === '' || $name === 'id' || (int) ($c['pk'] ?? 0) > 0) {
                continue;
            }
            if (preg_match('/(_at|_date|_time)$/', $name)) {
                continue;
            }
            if (in_array($name, ['public_code', 'order_number', 'draft_items'], true)) {
                continue;
            }
            if (isset($enums[$name])) {
                continue;
            }
            $type = strtolower((string) ($c['type'] ?? ''));
            if (str_contains($type, 'char') || str_contains($type, 'text')) {
                $cols[] = $name;
            }
        }
        return $cols;
    }

    /**
     * 列的类型：声明优先；其次 CHECK 枚举；再次按 SQL 类型猜。
     * 返回 string|text|int|number|bool|date|datetime|email|phone|enum|link 等语义类型，
     * 供后续表单控件/校验/CSV 复用同一份类型心智。
     */
    private static function resolveType(string $name, string $sqlType, array $enums, array $extra): string
    {
        if (isset($extra['type'])) {
            return (string) $extra['type'];
        }
        if (isset($enums[$name])) {
            return 'enum';
        }
        $t = strtolower(trim($sqlType));
        if (str_contains($t, 'int')) {
            return 'int';
        }
        if (in_array($t, ['real', 'double', 'float', 'decimal', 'numeric'], true)) {
            return 'number';
        }
        if (str_contains($t, 'bool')) {
            return 'bool';
        }
        return 'string';
    }

    // ------------------------------------------------------------ 统一清洗/校验

    /**
     * 需要自动渲染进表单的列：注册表里 meta['form'] 为真才参与（手动精修的列不标）。
     * 返回值含完整描述 + form 布局元信息，供 partials/_fields_auto.php 使用。
     */
    public static function autoFormFields(string $table, array $declared): array
    {
        $cols = self::columns($table, $declared);
        $out = [];
        foreach ($declared as $name => $meta) {
            if (empty($meta['form']) || !isset($cols[$name])) {
                continue;
            }
            if (isset($meta['writable']) && $meta['writable'] === false) {
                continue;
            }
            $f = $cols[$name];
            $f['form'] = is_array($meta['form']) ? $meta['form'] : [];
            $out[$name] = $f;
        }
        return $out;
    }

    /**
     * 渲染一个字段的完整 Bootstrap 块（col 宽度 + 必填星号 + 控件）。
     * 类型 → 控件：enum→select（DB/声明/模型钩子取可选值）、text→textarea、
     * bool→checkbox、其余→input（email/int/number/money/date/datetime 各自映射）。
     */
    public static function block(array $field, $value, array $ctx = []): string
    {
        $ctx += ['enumOptions' => null];
        $name = (string) ($field['name'] ?? '');
        $type = (string) ($field['type'] ?? 'string');
        $form = is_array($field['form'] ?? null) ? $field['form'] : [];
        $label = (string) ($field['label'] ?? $name);
        $required = !empty($form['required']) || !empty($field['required']);
        $width = (string) ($form['width'] ?? 'col-md-6');
        $val = is_array($value) ? ($value[$name] ?? null) : $value;
        $valStr = e($val === null ? '' : (string) $val);
        $reqAttr = $required ? ' required' : '';

        $out = '<div class="' . e($width) . '">';
        if ($type === 'bool') {
            $checked = !empty($val) ? ' checked' : '';
            $out .= '<div class="form-check mt-4">'
                 . '<input class="form-check-input" type="checkbox" name="' . e($name)
                 . '" id="field_' . e($name) . '" value="1"' . $checked . '>'
                 . '<label class="form-check-label" for="field_' . e($name) . '">' . e($label) . '</label></div>';
        } else {
            $out .= '<label class="form-label">' . e($label)
                  . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
            if ($type === 'enum') {
                $options = $field['options'] ?? null;
                if ($options === null && is_callable($ctx['enumOptions'])) {
                    $options = $ctx['enumOptions']($name);
                }
                $options = $options ? array_map('strval', (array) $options) : [];
                $sel = '<select name="' . e($name) . '" class="form-select"' . $reqAttr . '>';
                foreach ($options as $o) {
                    $o = (string) $o;
                    $picked = $val !== null && (string) $val === $o ? ' selected' : '';
                    $sel .= '<option value="' . e($o) . '"' . $picked . '>' . e($o) . '</option>';
                }
                $out .= $sel . '</select>';
            } elseif ($type === 'text') {
                $out .= '<textarea name="' . e($name) . '" class="form-control" rows="3"' . $reqAttr
                      . '>' . $valStr . '</textarea>';
            } else {
                $inputType = [
                    'email' => 'email', 'int' => 'number', 'number' => 'number',
                    'money' => 'number', 'date' => 'date', 'datetime' => 'datetime-local',
                ][$type] ?? 'text';
                $step = ($type === 'number' || $type === 'money') ? ' step="any"'
                      : (($type === 'int') ? ' step="1"' : '');
                $out .= '<input type="' . $inputType . '" name="' . e($name) . '"'
                      . ' class="form-control" value="' . $valStr . '"' . $step . $reqAttr . '>';
            }
        }
        return $out . '</div>';
    }

    /**
     * 把原始输入（表单 $_POST / CSV 一行）洗成可直接落库的 data，并给出 errors。
     *
     * 只处理注册表里声明的列（声明 = 这张表“应用允许写什么”的白名单），逐列按
     * type 做类型转换 + 必填/枚举/长度/范围校验。替代各 Controller 手写的 validate()。
     *
     * 字段可用的声明键（除 label/type/searchable 外）：
     *   required / requiredMsg    必填（消息可自定义）；
     *   default                   空值落库用的默认值（如 status 的 'active'）；
     *   defaultToday              空值落今天的日期（订单日期）；
     *   emailValidate             非空时校验邮箱格式；
     *   min / max                 数值列的范围上限（max 对文本列是字符长度上限）；
     *   strict                    枚举值不在可选值里时报错（否则静默回落 default）；
     *   unique                    唯一性由模型层查库执行（见 Model::sanitizeInput）；
     *   writable=>false           只描述不参与通用清洗（如 lost_reason 有专门流程）。
     *
     * @return array{0:array<string,mixed>, 1:array<int,string>} [data, errors]
     */
    public static function sanitize(string $table, array $declared, array $input, array $ctx = []): array
    {
        $ctx += ['enumOptions' => null];
        $describe = self::columns($table, $declared);
        $errors = [];
        $data = [];

        foreach ($declared as $name => $meta) {
            if (isset($meta['writable']) && $meta['writable'] === false) {
                continue;                    // 只描述不写：有专门流程维护的列
            }
            $col = $describe[$name] ?? null;
            if ($col === null) {
                continue;                    // 注册表写错列名由 FieldsTest 兜底，这里不猜
            }
            $type = (string) ($col['type'] ?? 'string');
            $label = (string) ($col['label'] ?? $name);
            $required = !empty($meta['required']);
            $hasDefault = array_key_exists('default', $meta);
            $requiredMsg = (string) ($meta['requiredMsg'] ?? ($label . '不能为空。'));

            // 布尔列永远产出 0/1（复选框没勾上 = 0，勾上才 1）
            if ($type === 'bool') {
                $data[$name] = self::truthy($input[$name] ?? null) ? 1 : 0;
                continue;
            }

            $raw = array_key_exists($name, $input) ? $input[$name] : null;
            $s = $raw === null ? '' : trim((string) $raw);

            if ($s === '') {
                if ($required) {
                    $errors[] = $requiredMsg;
                    $data[$name] = '';
                    continue;
                }
                if (!empty($meta['defaultToday'])) {
                    $data[$name] = date('Y-m-d');
                    continue;
                }
                if ($hasDefault) {
                    $data[$name] = self::castDefault($meta['default'], $type);
                    continue;
                }
                $data[$name] = null;         // 可空留空 = 清空，与各模块既有语义一致
                continue;
            }

            switch ($type) {
                case 'int':
                    $v = (int) $s;
                    if ($required && $v <= 0) {
                        $errors[] = $requiredMsg;
                        $data[$name] = $s;
                        break;
                    }
                    $data[$name] = $v <= 0 ? null : $v;      // 非必填 id 传 0 = 不关联（如订单的 deal_id）
                    break;

                case 'number':
                case 'money':
                    if (!is_numeric($s)) {
                        if ($required) {
                            $errors[] = $requiredMsg;
                            $data[$name] = $s;
                        } else {
                            $data[$name] = $hasDefault ? (float) $meta['default'] : null;   // 保持旧行为：填了垃圾按默认/null 处理
                        }
                        break;
                    }
                    $v = (float) $s;
                    if (array_key_exists('min', $meta) && $v < (float) $meta['min']) {
                        $errors[] = $label . '超出合理范围。';
                    }
                    if (array_key_exists('max', $meta) && $v > (float) $meta['max']) {
                        $errors[] = $label . '超出合理范围。';
                    }
                    $data[$name] = $v;
                    break;

                case 'enum':
                    $options = $col['options'] ?? null;
                    if ($options === null && is_callable($ctx['enumOptions'])) {
                        $options = $ctx['enumOptions']((string) $name);   // PHP 侧枚举（如商品单位）
                    }
                    $options = $options ? array_map('strval', (array) $options) : [];
                    if ($options !== [] && in_array($s, $options, true)) {
                        $data[$name] = $s;
                    } elseif (!empty($meta['strict'])) {
                        // 严格枚举：空值已在上面走了 default，能到这里说明是非法值，要报错
                        $errors[] = $label . '不在可选值里。';
                        $data[$name] = $s;
                    } elseif ($hasDefault) {
                        $data[$name] = $meta['default'];                  // 未知值静默回落默认（状态列既有行为）
                    } else {
                        $data[$name] = $s;
                    }
                    break;

                case 'email':
                    $data[$name] = $s;
                    if (!empty($meta['emailValidate']) && !filter_var($s, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = '请输入有效的邮箱地址。';
                    }
                    break;

                case 'text':
                case 'string':
                default:
                    if (!empty($meta['max']) && textLength($s) > (int) $meta['max']) {
                        $errors[] = $label . '最长 ' . (int) $meta['max'] . ' 字。';
                    }
                    $data[$name] = $s;                                   // date/datetime 等同透传
                    break;
            }
        }

        return [$data, $errors];
    }

    /** 复选框的“有值即真”：勾上发 value=1（也可能 1/on/true） */
    private static function truthy($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'on'
            || (is_string($v) && strtolower($v) === 'true');
    }

    /** 空值落默认时的类型转换（数值列默认要落成数字而不是字符串） */
    private static function castDefault($default, string $type)
    {
        if (($type === 'number' || $type === 'money') && is_numeric($default)) {
            return (float) $default;
        }
        return $default;
    }
}
