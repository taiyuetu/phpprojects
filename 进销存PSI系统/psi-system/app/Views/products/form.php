<?php use App\Core\Router; ?>
<div class="card" style="max-width:700px;">
    <h2><?= $product ? 'Edit Product' : 'Add Product' ?></h2>
    <form method="post" action="<?= $product ? Router::url('/products/' . $product['id']) : Router::url('/products') ?>">
        <?= $this->csrfField() ?>
        <div class="form-row">
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" required value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id">
                    <option value="">— None —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (isset($product['category_id']) && $product['category_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" value="<?= htmlspecialchars($product['unit'] ?? 'pcs') ?>" placeholder="pcs, box, kg...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Cost Price</label>
                <input type="number" step="0.01" min="0" name="cost_price" value="<?= htmlspecialchars($product['cost_price'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label>Sale Price</label>
                <input type="number" step="0.01" min="0" name="sale_price" value="<?= htmlspecialchars($product['sale_price'] ?? '0') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= $product ? 'Quantity in Stock' : 'Opening Quantity' ?></label>
                <input type="number" min="0" name="quantity" value="<?= htmlspecialchars($product['quantity'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label>Reorder Level</label>
                <input type="number" min="0" name="reorder_level" value="<?= htmlspecialchars($product['reorder_level'] ?? '0') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="<?= Router::url('/products') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
