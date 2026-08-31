<?php
namespace App\Core;

/**
 * Adds JSON "custom fields" support to a model.
 *
 * A model overrides customFieldDefinitions() to declare its fields; values
 * are stored in the `attributes` JSON column. This powers auto-generated
 * form inputs, list columns, filter controls, CSV round-trip, and
 * json_extract filtering — all from the one definition array.
 *
 * Field definition shape:
 *   'key' => [
 *       'label'      => 'Human label',
 *       'type'       => 'text' | 'textarea' | 'select' | 'date' | 'upload',
 *       'filterable' => true,                 // show in list filter form
 *       'required'   => false,                // validate on save (server-side)
 *       'options'    => ['a', 'b'],           // for select only
 *   ]
 */
trait HasCustomFields
{
    /** Override in each model to declare its custom fields. */
    protected static function customFieldDefinitions(): array
    {
        return [];
    }

    /** Public accessor for the field definitions. */
    public static function customFields(): array
    {
        return static::customFieldDefinitions();
    }

    /** Decode the JSON custom-field values into an associative array. */
    public static function parseCustomFields(?string $json): array
    {
        $decoded = json_decode($json ?? '{}', true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validate custom field values against their definitions.
     * Returns an array of error messages (empty = valid).
     *
     * @param array $values  Key => value pairs submitted (cf_* collected values).
     * @return string[]      List of human-readable error messages.
     */
    public static function validateCustomFields(array $values): array
    {
        $errors = [];
        foreach (static::customFields() as $key => $def) {
            $type = $def['type'] ?? 'text';
            $label = $def['label'] ?? $key;

            // Required check (skip upload fields — they're validated separately)
            if (!empty($def['required']) && $type !== 'upload') {
                $v = $values[$key] ?? '';
                if (is_string($v) && trim($v) === '') {
                    $errors[] = "{$label} is required.";
                }
            }

            // Date format check
            if ($type === 'date' && !empty($values[$key])) {
                $v = trim((string) $values[$key]);
                if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    $errors[] = "{$label} must be a valid date (YYYY-MM-DD).";
                }
            }

            // Select: must be one of the options (when required or non-empty)
            if ($type === 'select' && !empty($values[$key]) && !empty($def['options'])) {
                if (!in_array($values[$key], $def['options'], true)) {
                    $errors[] = "{$label} must be one of: " . implode(', ', $def['options']) . '.';
                }
            }
        }

        // Upload required check: look for existing value or new upload
        if (!empty($_FILES)) {
            foreach (static::customFields() as $key => $def) {
                if (($def['type'] ?? 'text') === 'upload' && !empty($def['required'])) {
                    $hasExisting = !empty($values[$key]);
                    $hasUpload = !empty($_FILES['cf_' . $key]) && $_FILES['cf_' . $key]['error'] === UPLOAD_ERR_OK;
                    $deleting = !empty($_POST['cf_' . $key . '_delete']);
                    if (!$hasExisting && !$hasUpload && !$deleting) {
                        $errors[] = ($def['label'] ?? $key) . ' is required.';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Handle file uploads for 'upload' type custom fields.
     * Returns an array of key => filepath for successfully uploaded files.
     * Existing values in $currentAttrs are preserved when no new file is uploaded.
     * To clear an upload, the caller should pass an empty string.
     */
    public static function handleCustomFieldUploads(array $currentAttrs = []): array
    {
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/custom/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $result = $currentAttrs;

        foreach (static::customFields() as $key => $def) {
            if (($def['type'] ?? 'text') !== 'upload') continue;

            // Check if user wants to delete the existing file
            if (!empty($_POST['cf_' . $key . '_delete'])) {
                $oldFile = $result[$key] ?? '';
                if ($oldFile !== '' && file_exists(dirname(__DIR__, 2) . '/public/' . $oldFile)) {
                    @unlink(dirname(__DIR__, 2) . '/public/' . $oldFile);
                }
                $result[$key] = '';
                continue;
            }

            // Check if a new file was uploaded
            if (empty($_FILES['cf_' . $key]) || $_FILES['cf_' . $key]['error'] !== UPLOAD_ERR_OK) {
                continue; // keep existing value
            }

            $file = $_FILES['cf_' . $key];
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) continue;

            // Detect MIME type
            $mime = null;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($file['tmp_name']);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg','jpeg','png','gif','webp','bmp','pdf','doc','docx','xls','xlsx','csv','txt','zip','rar'];
            if (!in_array($ext, $allowedExts, true)) continue;
            if ($ext === 'jpeg') $ext = 'jpg';

            $filename = 'cf_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                // Delete old file if replacing
                $oldFile = $result[$key] ?? '';
                if ($oldFile !== '' && file_exists(dirname(__DIR__, 2) . '/public/' . $oldFile)) {
                    @unlink(dirname(__DIR__, 2) . '/public/' . $oldFile);
                }
                $result[$key] = 'uploads/custom/' . $filename;
            }
        }

        return $result;
    }

    /**
     * Build a SQL fragment + params that filter rows by custom field values.
     * Returns ['sql' => string, 'params' => array]. $alias prefixes the
     * attributes column (e.g. 'p.attributes') for joined queries.
     */
    public static function customFieldFilterSql(array $filters, string $alias = ''): array
    {
        $column = ($alias !== '' ? $alias . '.' : '') . 'attributes';
        $sql = '';
        $params = [];

        foreach (static::customFields() as $key => $def) {
            if (empty($def['filterable'])) continue;
            $value = $filters['cf_' . $key] ?? '';
            if ($value === '') continue;

            $path = '$.' . $key;
            $pKey = 'cfp_' . $key;
            $vKey = 'cfv_' . $key;

            if (($def['type'] ?? 'text') === 'select') {
                $sql .= " AND json_extract({$column}, :{$pKey}) = :{$vKey}";
                $params[$vKey] = $value;
            } else {
                $sql .= " AND json_extract({$column}, :{$pKey}) LIKE :{$vKey}";
                $params[$vKey] = '%' . $value . '%';
            }
            $params[$pKey] = $path;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * List query with text search + custom-field filtering, for tables
     * without joins (Supplier, Customer, Category).
     */
    public static function filterWithCustomFields(array $filters = [], array $searchColumns = [], ?string $orderBy = null): array
    {
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE 1 = 1';
        $params = [];

        $q = trim($filters['q'] ?? '');
        if ($q !== '' && !empty($searchColumns)) {
            $conds = [];
            foreach ($searchColumns as $i => $col) {
                $p = 'qs_' . $i;
                $conds[] = "{$col} LIKE :{$p}";
                $params[$p] = '%' . $q . '%';
            }
            $sql .= ' AND (' . implode(' OR ', $conds) . ')';
        }

        $cf = static::customFieldFilterSql($filters);
        $sql .= $cf['sql'];
        $params = array_merge($params, $cf['params']);

        if ($orderBy) $sql .= ' ORDER BY ' . $orderBy;

        return static::raw($sql, $params);
    }

    /**
     * Paginated version of filterWithCustomFields().
     * Returns ['rows'=>array, 'total'=>int, 'page'=>int, 'perPage'=>int, 'pages'=>int]
     */
    public static function filterWithCustomFieldsPaginated(array $filters = [], array $searchColumns = [], ?string $orderBy = null, int $page = 1, int $perPage = 20): array
    {
        $where = ' WHERE 1 = 1';
        $params = [];

        $q = trim($filters['q'] ?? '');
        if ($q !== '' && !empty($searchColumns)) {
            $conds = [];
            foreach ($searchColumns as $i => $col) {
                $p = 'qs_' . $i;
                $conds[] = "{$col} LIKE :{$p}";
                $params[$p] = '%' . $q . '%';
            }
            $where .= ' AND (' . implode(' OR ', $conds) . ')';
        }

        $cf = static::customFieldFilterSql($filters);
        $where .= $cf['sql'];
        $params = array_merge($params, $cf['params']);

        // Count
        $countSql = 'SELECT COUNT(*) FROM ' . static::$table . $where;
        $countStmt = static::db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $orderClause = $orderBy ? ' ORDER BY ' . $orderBy : '';
        $dataSql = 'SELECT * FROM ' . static::$table . $where . $orderClause . ' LIMIT :limit OFFSET :offset';
        $dataStmt = static::db()->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue(':' . $k, $v);
        }
        $dataStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        return [
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => $pages,
        ];
    }
}
