<?php use App\Core\Router; ?>
<div class="card" style="max-width:700px;">
    <h2>Import Products from CSV</h2>
    <p class="text-muted" style="margin-top:0;">
        Upload a CSV file to bulk-create or update products. Only <strong>sku</strong> and
        <strong>name</strong> are required.
    </p>

    <form method="post" action="<?= Router::url('/products/import') ?>" enctype="multipart/form-data">
        <?= $this->csrfField() ?>

        <div class="form-group">
            <label>CSV File <span class="text-muted">(or ZIP archive with images)</span></label>
            <input type="file" name="import_file" accept=".csv,.zip,text/csv,application/zip" required>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="update_existing" value="1" style="width:auto;">
                Update existing products (by SKU) instead of skipping them
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
            <a href="<?= Router::url('/products') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
        <h3 style="margin:0 0 8px;">Importing product images</h3>
        <p class="text-muted" style="margin-top:0;">
            To attach images to products automatically, zip the CSV together with an
            <code>images</code> folder and upload the <code>.zip</code> file:
        </p>
        <pre style="background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius);padding:12px;font-size:0.85rem;overflow-x:auto;">your-import.zip
├── products.csv
└── images/
    ├── tqb0001-1.jpg
    ├── tqb0001(1).jpg
    ├── tqb0001-2.png
    └── tqb0002.jpg</pre>
        <p class="text-muted">
            Any image whose filename starts with a product's <strong>SKU</strong> (followed by
            <code>-</code>, <code>_</code>, <code>(</code>, <code>.</code>, a space, or nothing) is added to
            that product's gallery. Supported types: JPG, PNG, GIF, WebP, BMP.
        </p>
    </div>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
        <h3 style="margin:0 0 8px;">Expected CSV columns</h3>
        <p class="text-muted" style="margin-top:0;">The first row must be a header. Order doesn't matter, but column names must match (case-insensitive):</p>
        <table>
            <thead>
                <tr><th>Column</th><th>Required</th><th>Notes</th></tr>
            </thead>
            <tbody>
                <tr><td><code>sku</code></td><td>Yes</td><td>Unique product code</td></tr>
                <tr><td><code>name</code></td><td>Yes</td><td>Product name</td></tr>
                <tr><td><code>category</code></td><td>No</td><td>Category name — created automatically if it doesn't exist</td></tr>
                <tr><td><code>unit</code></td><td>No</td><td>Defaults to <code>pcs</code></td></tr>
                <tr><td><code>cost_price</code></td><td>No</td><td>Defaults to 0</td></tr>
                <tr><td><code>sale_price</code></td><td>No</td><td>Defaults to 0</td></tr>
                <tr><td><code>quantity</code></td><td>No</td><td>Opening stock, defaults to 0</td></tr>
                <tr><td><code>reorder_level</code></td><td>No</td><td>Defaults to 0</td></tr>
                <?php foreach ($customFields as $key => $def): ?>
                    <tr><td><code><?= htmlspecialchars($def['label']) ?></code></td><td>No</td><td>Custom field (also accepts column <code><?= htmlspecialchars($key) ?></code>)</td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="text-muted" style="margin-top:16px;">
            Tip: use <a href="<?= Router::url('/products/export') ?>">Export CSV</a> first to get a correctly
            formatted file, then edit it and re-import.
        </p>
    </div>
</div>
