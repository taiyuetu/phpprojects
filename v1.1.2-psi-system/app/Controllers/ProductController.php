<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryTransaction;

class ProductController extends Controller
{
    private string $uploadDir;

    public function __construct()
    {
        $this->uploadDir = dirname(__DIR__, 2) . '/public/uploads/products/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function index(): void
    {
        $filters = [
            'q'           => trim($this->input('q', '')),
            'category_id' => trim($this->input('category_id', '')),
            'status'      => trim($this->input('status', '')),
        ];
        foreach (Product::customFields() as $key => $def) {
            if (!empty($def['filterable'])) {
                $filters['cf_' . $key] = trim($this->input('cf_' . $key, ''));
            }
        }

        $page = max(1, (int) $this->input('page', 1));
        $perPage = 20;

        $result = Product::filterPaginated($filters, $page, $perPage);

        $this->view('products/index', [
            'title'        => 'Products',
            'products'     => $result['rows'],
            'categories'   => Category::all('name'),
            'customFields' => Product::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('products/form', [
            'title'        => 'Add Product',
            'product'      => null,
            'categories'   => Category::all('name'),
            'customFields' => Product::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->productData();

        if ($data['sku'] === '' || $data['name'] === '') {
            $this->flash('error', 'SKU and product name are required.');
            $this->redirect('/products/create');
        }
        if (Product::exists('sku', $data['sku'])) {
            $this->flash('error', 'A product with SKU "' . htmlspecialchars($data['sku']) . '" already exists.');
            $this->redirect('/products/create');
        }

        $data['gallery'] = json_encode($this->handleGalleryUploads([]));
        $id = Product::create($data);

        if ((int)$data['quantity'] > 0) {
            InventoryTransaction::create([
                'product_id'    => $id,
                'type'          => 'adjustment',
                'qty_change'    => (int)$data['quantity'],
                'balance_after' => (int)$data['quantity'],
                'reference'     => 'Initial stock',
                'notes'         => 'Opening balance on product creation',
            ]);
        }

        $this->flash('success', 'Product added.');
        $this->redirect('/products');
    }

    public function edit(string $id): void
    {
        $product = Product::find($id);
        if (!$product) { $this->flash('error', 'Product not found.'); $this->redirect('/products'); }
        $this->view('products/form', [
            'title'        => 'Edit Product',
            'product'      => $product,
            'categories'   => Category::all('name'),
            'customFields' => Product::customFields(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $product = Product::find($id);
        if (!$product) { $this->flash('error', 'Product not found.'); $this->redirect('/products'); }
        $data = $this->productData(Product::parseCustomFields($product['attributes'] ?? '{}'));

        if ($data['sku'] === '' || $data['name'] === '') {
            $this->flash('error', 'SKU and product name are required.');
            $this->redirect('/products/' . $id . '/edit');
        }
        if (Product::exists('sku', $data['sku'], (int)$id)) {
            $this->flash('error', 'A product with SKU "' . htmlspecialchars($data['sku']) . '" already exists.');
            $this->redirect('/products/' . $id . '/edit');
        }

        // Merge new uploads with existing gallery
        $existingGallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
        $keepImages = $_POST['existing_gallery'] ?? [];
        // Remove images user unchecked
        $existingGallery = array_values(array_filter($existingGallery, fn($img) => in_array($img, $keepImages)));
        // Delete removed files from disk
        $allExisting = json_decode($product['gallery'] ?? '[]', true) ?: [];
        foreach ($allExisting as $img) {
            if (!in_array($img, $keepImages)) {
                $path = $this->uploadDir . $img;
                if (file_exists($path)) unlink($path);
            }
        }

        $newUploads = $this->handleGalleryUploads($existingGallery);
        $data['gallery'] = json_encode($newUploads);

        // Any manual quantity edit here is logged as an adjustment.
        $diff = (int)$data['quantity'] - (int)$product['quantity'];
        Product::update($id, $data);

        if ($diff !== 0) {
            InventoryTransaction::create([
                'product_id'    => $id,
                'type'          => 'adjustment',
                'qty_change'    => $diff,
                'balance_after' => (int)$data['quantity'],
                'reference'     => 'Manual edit',
                'notes'         => 'Quantity corrected via product edit form',
            ]);
        }

        $this->flash('success', 'Product updated.');
        $this->redirect('/products');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        $product = Product::find($id);
        if (!$product) { $this->flash('error', 'Product not found.'); $this->redirect('/products'); }

        // Check if product is referenced by any purchase or sale items
        $purchaseCount = Model::raw('SELECT COUNT(*) AS cnt FROM purchase_items WHERE product_id = ?', [$id])[0]['cnt'] ?? 0;
        $saleCount     = Model::raw('SELECT COUNT(*) AS cnt FROM sale_items WHERE product_id = ?', [$id])[0]['cnt'] ?? 0;
        if ($purchaseCount > 0 || $saleCount > 0) {
            $this->flash('error', 'Cannot delete this product because it is referenced by existing purchase or sale records. Consider archiving it instead.');
            $this->redirect('/products');
        }

        // Clean up gallery files
        $gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
        foreach ($gallery as $img) {
            $path = $this->uploadDir . $img;
            if (file_exists($path)) unlink($path);
        }

        // Remove related child records, then the product itself
        Model::raw('DELETE FROM inventory_transactions WHERE product_id = ?', [$id]);
        Model::raw("DELETE FROM change_logs WHERE table_name = 'products' AND record_id = ?", [$id]);
        Product::delete($id);

        $this->flash('success', 'Product deleted.');
        $this->redirect('/products');
    }

    public function show(string $id): void
    {
        $product = Product::find($id);
        if (!$product) { $this->flash('error', 'Product not found.'); $this->redirect('/products'); }

        $this->view('products/show', [
            'title'        => 'Product Detail',
            'product'      => $product,
            'transactions' => InventoryTransaction::forProduct((int)$id),
            'customFields' => Product::customFields(),
        ]);
    }

    /**
     * Export all products to a downloadable CSV file.
     * Columns: sku, name, category, unit, cost_price, sale_price, quantity, reorder_level
     */
    public function exportCsv(): void
    {
        $filters = [
            'q'           => trim($this->input('q', '')),
            'category_id' => trim($this->input('category_id', '')),
            'status'      => trim($this->input('status', '')),
        ];
        foreach (Product::customFields() as $key => $def) {
            if (!empty($def['filterable'])) {
                $filters['cf_' . $key] = trim($this->input('cf_' . $key, ''));
            }
        }
        $products = Product::filter($filters);

        $isFiltered = count(array_filter($filters, fn($v) => $v !== '')) > 0;
        $filename = 'products' . ($isFiltered ? '_filtered' : '') . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens it correctly
        fwrite($out, "\xEF\xBB\xBF");

        $header = ['sku', 'name', 'category', 'unit', 'cost_price', 'sale_price', 'quantity', 'reorder_level'];
        foreach (Product::customFields() as $def) {
            $header[] = $def['label'];
        }
        fputcsv($out, $header);

        foreach ($products as $p) {
            $attrs = json_decode($p['attributes'] ?? '{}', true) ?: [];
            $line = [
                $p['sku'],
                $p['name'],
                $p['category_name'] ?? '',
                $p['unit'],
                $p['cost_price'],
                $p['sale_price'],
                $p['quantity'],
                $p['reorder_level'],
            ];
            foreach (Product::customFields() as $key => $def) {
                $line[] = $attrs[$key] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }

    /** Show the CSV import form. */
    public function importForm(): void
    {
        $this->view('products/import', [
            'title'        => 'Import Products',
            'customFields' => Product::customFields(),
        ]);
    }

    /**
     * Handle the file upload and import products.
     * Accepts either a plain CSV, or a ZIP archive containing a CSV plus an
     * "images" folder whose images are named by SKU (e.g. tqb0001-1.jpg,
     * tqb0001(1).jpg, tqb0001-2.jpg).
     * CSV columns (header row): sku, name, category, unit, cost_price, sale_price, quantity, reorder_level
     * Only sku and name are required; other columns are optional.
     */
    public function importCsv(): void
    {
        $this->verifyCsrf();

        if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please choose a CSV or ZIP file to import.');
            $this->redirect('/products/import');
        }

        $tmpName        = $_FILES['import_file']['tmp_name'];
        $origName       = strtolower($_FILES['import_file']['name']);
        $updateExisting = !empty($this->input('update_existing'));

        $extractDir = null;
        $imageFiles = [];
        $csvPath    = $tmpName;

        // ZIP archive: extract it and locate the CSV + product images
        if (preg_match('/\.zip$/i', $origName)) {
            $extractDir = sys_get_temp_dir() . '/psi_import_' . uniqid();
            if (!mkdir($extractDir, 0755, true)) {
                $this->flash('error', 'Could not create a temporary folder for extraction.');
                $this->redirect('/products/import');
            }

            $zip = new \ZipArchive();
            if ($zip->open($tmpName) !== true) {
                $this->removeDirectory($extractDir);
                $this->flash('error', 'Could not open the ZIP archive.');
                $this->redirect('/products/import');
            }
            $zip->extractTo($extractDir);
            $zip->close();

            $csvFiles = $this->findFilesByExtension($extractDir, ['csv']);
            if (empty($csvFiles)) {
                $this->removeDirectory($extractDir);
                $this->flash('error', 'No CSV file found inside the ZIP archive.');
                $this->redirect('/products/import');
            }
            $csvPath = $csvFiles[0];

            $imageFiles = $this->findFilesByExtension($extractDir, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        }

        $handle = @fopen($csvPath, 'r');
        if (!$handle) {
            if ($extractDir) $this->removeDirectory($extractDir);
            $this->flash('error', 'Could not read the CSV file.');
            $this->redirect('/products/import');
        }

        // Read and normalize the header row
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->flash('error', 'The CSV file is empty.');
            $this->redirect('/products/import');
        }

        $header = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip BOM
            return strtolower(trim($h));
        }, $header);
        $map = array_flip($header);

        // Map custom fields to CSV columns (match by key or label, case-insensitive)
        $customCols = [];
        foreach (Product::customFields() as $key => $def) {
            if (isset($map[$key])) {
                $customCols[$key] = $map[$key];
            } else {
                $label = strtolower(trim($def['label']));
                if ($label !== '' && isset($map[$label])) {
                    $customCols[$key] = $map[$label];
                }
            }
        }

        $required = ['sku', 'name'];
        foreach ($required as $col) {
            if (!isset($map[$col])) {
                fclose($handle);
                $this->flash('error', "CSV is missing required column: \"{$col}\". Expected: sku, name, category, unit, cost_price, sale_price, quantity, reorder_level.");
                $this->redirect('/products/import');
            }
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip fully-empty rows
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

            $sku = trim((string)($row[$map['sku']] ?? ''));
            $name = trim((string)($row[$map['name']] ?? ''));

            if ($sku === '' || $name === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: missing sku or name.";
                continue;
            }

            // Resolve category by name (create if missing)
            $categoryId = null;
            $categoryName = trim((string)($row[$map['category'] ?? -1] ?? ''));
            if ($categoryName !== '') {
                $category = Category::findBy('name', $categoryName);
                if ($category) {
                    $categoryId = $category['id'];
                } else {
                    $categoryId = Category::create(['name' => $categoryName]);
                }
            }

            // Collect custom field values from the row
            $attrs = [];
            foreach ($customCols as $key => $col) {
                $v = trim((string)($row[$col] ?? ''));
                if ($v !== '') $attrs[$key] = $v;
            }

            $data = [
                'sku'           => $sku,
                'name'          => $name,
                'category_id'   => $categoryId,
                'unit'          => trim((string)($row[$map['unit'] ?? -1] ?? 'pcs')) ?: 'pcs',
                'cost_price'    => (float)($row[$map['cost_price'] ?? -1] ?? 0),
                'sale_price'    => (float)($row[$map['sale_price'] ?? -1] ?? 0),
                'quantity'      => (int)($row[$map['quantity'] ?? -1] ?? 0),
                'reorder_level' => (int)($row[$map['reorder_level'] ?? -1] ?? 0),
                'attributes'    => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            ];

            $existing = Product::findBy('sku', $sku);

            if ($existing) {
                if (!$updateExisting) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: SKU \"{$sku}\" already exists (skipped).";
                    continue;
                }
                // Preserve gallery + merge custom fields when updating
                $data['gallery'] = $existing['gallery'] ?? '[]';
                $existingAttrs = json_decode($existing['attributes'] ?? '{}', true) ?: [];
                $data['attributes'] = json_encode(array_merge($existingAttrs, $attrs), JSON_UNESCAPED_UNICODE);
                $diff = (int)$data['quantity'] - (int)$existing['quantity'];
                Product::update($existing['id'], $data);
                if ($diff !== 0) {
                    InventoryTransaction::create([
                        'product_id'    => $existing['id'],
                        'type'          => 'adjustment',
                        'qty_change'    => $diff,
                        'balance_after' => (int)$data['quantity'],
                        'reference'     => 'CSV import',
                        'notes'         => 'Quantity corrected via CSV import',
                    ]);
                }
                $updated++;
                continue;
            }

            $id = Product::create($data);
            if ((int)$data['quantity'] > 0) {
                InventoryTransaction::create([
                    'product_id'    => $id,
                    'type'          => 'adjustment',
                    'qty_change'    => (int)$data['quantity'],
                    'balance_after' => (int)$data['quantity'],
                    'reference'     => 'CSV import',
                    'notes'         => 'Opening balance via CSV import',
                ]);
            }
            $imported++;
        }

        fclose($handle);

        // Attach gallery images matched by SKU (ZIP uploads only)
        $imagesAdded = 0;
        $imagesUnmatched = 0;
        if (!empty($imageFiles)) {
            $skuMap = $this->buildSkuMap();
            foreach ($imageFiles as $imagePath) {
                $productId = $this->matchImageToSku($imagePath, $skuMap);
                if ($productId !== null && $this->assignGalleryImage($productId, $imagePath)) {
                    $imagesAdded++;
                } else {
                    $imagesUnmatched++;
                }
            }
        }

        if ($extractDir) $this->removeDirectory($extractDir);

        $parts = [];
        if ($imported > 0)      $parts[] = "{$imported} imported";
        if ($updated > 0)       $parts[] = "{$updated} updated";
        if ($skipped > 0)       $parts[] = "{$skipped} skipped";
        if ($imagesAdded > 0)   $parts[] = "{$imagesAdded} images attached";
        if ($imagesUnmatched > 0) $parts[] = "{$imagesUnmatched} images unmatched";

        $this->flash('success', 'CSV import complete: ' . (implode(', ', $parts) ?: 'no changes') . '.');

        if (!empty($errors)) {
            $this->flash('error', implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' …' : ''));
        }

        $this->redirect('/products');
    }

    /** Recursively find files with the given extensions under a directory. */
    private function findFilesByExtension(string $dir, array $extensions): array
    {
        $results = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $results[] = $file->getPathname();
            }
        }
        sort($results);
        return $results;
    }

    /** Recursively delete a directory and everything inside it. */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    /** Build a lowercase-sku => product id map for image matching. */
    private function buildSkuMap(): array
    {
        $map = [];
        foreach (Product::all() as $p) {
            $sku = strtolower(trim((string)$p['sku']));
            if ($sku !== '') $map[$sku] = (int)$p['id'];
        }
        return $map;
    }

    /**
     * Match an image filename to a product SKU.
     * Supports tqb0001.jpg, tqb0001-1.jpg, tqb0001(1).jpg, tqb0001_2.jpg, etc.
     */
    private function matchImageToSku(string $imagePath, array $skuMap): ?int
    {
        $base = strtolower(trim(pathinfo($imagePath, PATHINFO_FILENAME)));
        if ($base === '') return null;

        $skus = array_keys($skuMap);
        usort($skus, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($skus as $sku) {
            if ($base === $sku) return $skuMap[$sku];
            if (str_starts_with($base, $sku)) {
                $next = substr($base, strlen($sku), 1);
                if ($next === '' || in_array($next, ['-', '_', '(', '.', ' '], true)) {
                    return $skuMap[$sku];
                }
            }
        }
        return null;
    }

    /** Copy an image into the uploads dir and append it to a product's gallery. */
    private function assignGalleryImage(int $productId, string $sourcePath): bool
    {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        if (!in_array($ext, $allowed, true)) return false;
        if ($ext === 'jpeg') $ext = 'jpg';

        $filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!copy($sourcePath, $this->uploadDir . $filename)) return false;

        $product = Product::find($productId);
        if (!$product) {
            @unlink($this->uploadDir . $filename);
            return false;
        }

        $gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
        $gallery[] = $filename;
        Product::update($productId, ['gallery' => json_encode($gallery)]);
        return true;
    }

    /**
     * Handle gallery file uploads. Returns merged array of filenames.
     */
    private function handleGalleryUploads(array $existing): array
    {
        if (empty($_FILES['gallery_images']['name'][0])) {
            return $existing;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        foreach ($_FILES['gallery_images']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['gallery_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['gallery_images']['size'][$i] > $maxSize) continue;

            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($tmpName);
            } else {
                // Fallback: detect MIME from file extension
                $extMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
                $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION));
                $mime = $extMap[$ext] ?? 'application/octet-stream';
            }

            if (!in_array($mime, $allowed)) continue;

            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
            $filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            if (move_uploaded_file($tmpName, $this->uploadDir . $filename)) {
                $existing[] = $filename;
            }
        }

        return $existing;
    }

    private function productData(array $existingAttrs = []): array
    {
        $data = [
            'sku'           => trim($this->input('sku')),
            'name'          => trim($this->input('name')),
            'category_id'   => $this->input('category_id') ?: null,
            'unit'          => trim($this->input('unit', 'pcs')),
            'cost_price'    => (float) $this->input('cost_price', 0),
            'sale_price'    => (float) $this->input('sale_price', 0),
            'quantity'      => (int) $this->input('quantity', 0),
            'reorder_level' => (int) $this->input('reorder_level', 0),
        ];

        $attrs = $existingAttrs;
        foreach (Product::customFields() as $key => $def) {
            if (($def['type'] ?? 'text') === 'upload') continue; // handled below
            $v = trim($this->input('cf_' . $key, ''));
            if ($v !== '') $attrs[$key] = $v;
        }
        // Merge upload-type custom fields (preserves existing files when no new upload)
        $attrs = Product::handleCustomFieldUploads($attrs);
        $data['attributes'] = json_encode($attrs, JSON_UNESCAPED_UNICODE);

        return $data;
    }
}
