<?php
namespace App\Core;

/**
 * Reusable CSV import/export for simple master-data controllers
 * (Supplier, Customer, ...). The using controller defines five tiny
 * methods describing its entity, and gets exportCsv() / importForm() /
 * importCsv() for free.
 */
trait CsvImportExport
{
    /** Fully-qualified model class, e.g. \App\Models\Supplier::class. */
    abstract protected function csvModelClass(): string;

    /** CSV columns in order. Header label == db field. e.g. ['name','phone','email','address']. */
    abstract protected function csvColumns(): array;

    /** Field used to detect existing records (also the required column), e.g. 'name'. */
    abstract protected function csvMatchField(): string;

    /** Route prefix for redirects, e.g. '/suppliers'. */
    abstract protected function csvBasePath(): string;

    /** Singular entity label for messages, e.g. 'supplier'. */
    abstract protected function csvEntityLabel(): string;

    /** Custom fields supported by the model (empty when the model has none). */
    protected function csvCustomFields(string $model): array
    {
        return method_exists($model, 'customFields') ? $model::customFields() : [];
    }

    public function exportCsv(): void
    {
        $model        = $this->csvModelClass();
        $columns      = $this->csvColumns();
        $customFields = $this->csvCustomFields($model);
        $rows         = $model::all($this->csvMatchField());

        $slug = trim($this->csvBasePath(), '/');

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $slug . '_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

        $header = $columns;
        foreach ($customFields as $def) {
            $header[] = $def['label'];
        }
        fputcsv($out, $header);

        foreach ($rows as $row) {
            $attrs = json_decode($row['attributes'] ?? '{}', true) ?: [];
            $line = [];
            foreach ($columns as $col) {
                $line[] = $row[$col] ?? '';
            }
            foreach ($customFields as $key => $def) {
                $line[] = $attrs[$key] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }

    public function importForm(): void
    {
        $slug = trim($this->csvBasePath(), '/');
        $this->view($slug . '/import', ['title' => 'Import ' . ucfirst($slug)]);
    }

    public function importCsv(): void
    {
        $this->verifyCsrf();

        $basePath   = $this->csvBasePath();
        $importPath = $basePath . '/import';

        if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please choose a CSV file to import.');
            $this->redirect($importPath);
        }

        $tmpName        = $_FILES['import_file']['tmp_name'];
        $updateExisting = !empty($this->input('update_existing'));

        $handle = @fopen($tmpName, 'r');
        if (!$handle) {
            $this->flash('error', 'Could not read the uploaded file.');
            $this->redirect($importPath);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->flash('error', 'The CSV file is empty.');
            $this->redirect($importPath);
        }

        $header = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip BOM
            return strtolower(trim($h));
        }, $header);
        $map = array_flip($header);

        $matchField = $this->csvMatchField();
        if (!isset($map[$matchField])) {
            fclose($handle);
            $this->flash('error', 'CSV is missing required column: "' . $matchField . '". Expected: ' . implode(', ', $this->csvColumns()) . '.');
            $this->redirect($importPath);
        }

        $model        = $this->csvModelClass();
        $columns      = $this->csvColumns();
        $customFields = $this->csvCustomFields($model);

        // Map custom fields to CSV columns (match by key or label)
        $customCols = [];
        foreach ($customFields as $key => $def) {
            if (isset($map[$key])) {
                $customCols[$key] = $map[$key];
            } else {
                $label = strtolower(trim($def['label']));
                if ($label !== '' && isset($map[$label])) {
                    $customCols[$key] = $map[$label];
                }
            }
        }

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

            $data = [];
            foreach ($columns as $col) {
                $data[$col] = trim((string)($row[$map[$col] ?? -1] ?? ''));
            }

            $attrs = [];
            foreach ($customCols as $key => $col) {
                $v = trim((string)($row[$col] ?? ''));
                if ($v !== '') $attrs[$key] = $v;
            }
            if (!empty($customFields)) {
                $data['attributes'] = json_encode($attrs, JSON_UNESCAPED_UNICODE);
            }

            $matchValue = $data[$matchField] ?? '';
            if ($matchValue === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: missing {$matchField}.";
                continue;
            }

            $existing = $model::findBy($matchField, $matchValue);
            if ($existing) {
                if (!$updateExisting) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: {$this->csvEntityLabel()} \"{$matchValue}\" already exists (skipped).";
                    continue;
                }
                if (!empty($customFields)) {
                    $existingAttrs = json_decode($existing['attributes'] ?? '{}', true) ?: [];
                    $data['attributes'] = json_encode(array_merge($existingAttrs, $attrs), JSON_UNESCAPED_UNICODE);
                }
                $model::update($existing['id'], $data);
                $updated++;
                continue;
            }

            $model::create($data);
            $imported++;
        }

        fclose($handle);

        $parts = [];
        if ($imported > 0) $parts[] = "{$imported} imported";
        if ($updated > 0)  $parts[] = "{$updated} updated";
        if ($skipped > 0)  $parts[] = "{$skipped} skipped";

        $this->flash('success', 'CSV import complete: ' . (implode(', ', $parts) ?: 'no changes') . '.');

        if (!empty($errors)) {
            $this->flash('error', implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' …' : ''));
        }

        $this->redirect($basePath);
    }
}
