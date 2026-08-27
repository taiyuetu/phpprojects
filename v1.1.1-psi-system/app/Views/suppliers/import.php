<?php use App\Core\Router; ?>
<div class="card" style="max-width:700px;">
    <h2>Import Suppliers from CSV</h2>
    <p class="text-muted" style="margin-top:0;">
        Upload a CSV file to bulk-create or update suppliers. Only <strong>name</strong> is required.
    </p>

    <form method="post" action="<?= Router::url('/suppliers/import') ?>" enctype="multipart/form-data">
        <?= $this->csrfField() ?>

        <div class="form-group">
            <label>CSV File</label>
            <input type="file" name="import_file" accept=".csv,text/csv" required>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="update_existing" value="1" style="width:auto;">
                Update existing suppliers (by name) instead of skipping them
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
            <a href="<?= Router::url('/suppliers') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
        <h3 style="margin:0 0 8px;">Expected CSV columns</h3>
        <p class="text-muted" style="margin-top:0;">The first row must be a header. Order doesn't matter, but column names must match (case-insensitive):</p>
        <table>
            <thead><tr><th>Column</th><th>Required</th><th>Notes</th></tr></thead>
            <tbody>
                <tr><td><code>name</code></td><td>Yes</td><td>Supplier name</td></tr>
                <tr><td><code>phone</code></td><td>No</td><td></td></tr>
                <tr><td><code>email</code></td><td>No</td><td></td></tr>
                <tr><td><code>address</code></td><td>No</td><td></td></tr>
            </tbody>
        </table>

        <p class="text-muted" style="margin-top:16px;">
            Tip: use <a href="<?= Router::url('/suppliers/export') ?>">Export CSV</a> first to get a correctly
            formatted file, then edit it and re-import.
        </p>
    </div>
</div>
