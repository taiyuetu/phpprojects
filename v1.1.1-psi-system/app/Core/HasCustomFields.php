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
 *       'type'       => 'text' | 'select',   // select requires 'options'
 *       'filterable' => true,                 // show in list filter form
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
}
