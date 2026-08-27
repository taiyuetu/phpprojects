<?php
use App\Core\Router;
$gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
$attrs = json_decode($product['attributes'] ?? '{}', true) ?: [];
?>
<div class="card" style="max-width:700px;">
    <h2><?= $product ? 'Edit Product' : 'Add Product' ?></h2>
    <form method="post" action="<?= $product ? Router::url('/products/' . $product['id']) : Router::url('/products') ?>" enctype="multipart/form-data">
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

        <!-- Custom fields (auto-generated from Product::customFields()) -->
        <?php include __DIR__ . '/../partials/custom_fields_form.php'; ?>

        <!-- Gallery Images -->
        <div class="form-group">
            <label>Product Gallery</label>
            <?php if (!empty($gallery)): ?>
            <div class="gallery-preview" id="existingGallery">
                <?php foreach ($gallery as $img): ?>
                <div class="gallery-thumb" data-filename="<?= htmlspecialchars($img) ?>">
                    <img src="<?= Router::url('/uploads/products/' . $img) ?>" alt="Gallery image">
                    <button type="button" class="gallery-remove" title="Remove image">&times;</button>
                    <input type="hidden" name="existing_gallery[]" value="<?= htmlspecialchars($img) ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="gallery-upload-area" id="uploadArea">
                <input type="file" name="gallery_images[]" id="galleryInput" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="upload-placeholder" id="uploadPlaceholder">
                    <span class="upload-icon">📷</span>
                    <span>Click or drag images here to upload</span>
                    <span class="upload-hint">JPG, PNG, GIF, WebP — Max 5MB each</span>
                </div>
                <div class="upload-preview" id="uploadPreview"></div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="<?= Router::url('/products') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
(function() {
    const input = document.getElementById('galleryInput');
    const preview = document.getElementById('uploadPreview');
    const placeholder = document.getElementById('uploadPlaceholder');
    const uploadArea = document.getElementById('uploadArea');

    // Handle new file selection preview
    input.addEventListener('change', function() {
        preview.innerHTML = '';
        if (this.files.length > 0) {
            placeholder.style.display = 'none';
            preview.style.display = 'flex';
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const thumb = document.createElement('div');
                    thumb.className = 'gallery-thumb new-upload';
                    thumb.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                    preview.appendChild(thumb);
                };
                reader.readAsDataURL(file);
            });
        } else {
            placeholder.style.display = 'flex';
            preview.style.display = 'none';
        }
    });

    // Drag & drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    uploadArea.addEventListener('dragleave', function() {
        this.classList.remove('drag-over');
    });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
    });

    // Remove existing gallery image
    document.querySelectorAll('#existingGallery .gallery-remove').forEach(btn => {
        btn.addEventListener('click', function() {
            const thumb = this.closest('.gallery-thumb');
            thumb.style.opacity = '0.3';
            thumb.querySelector('input[type=hidden]').disabled = true;
            this.textContent = '↩';
            this.title = 'Restore image';
            this.onclick = function() {
                thumb.style.opacity = '1';
                thumb.querySelector('input[type=hidden]').disabled = false;
                this.textContent = '×';
                this.title = 'Remove image';
                this.onclick = arguments.callee;
            };
        });
    });
})();
</script>
