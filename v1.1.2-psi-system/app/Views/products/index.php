<?php
use App\Core\Router;

$filters = $filters ?? [];
$customFields = $customFields ?? [];
$q = $filters['q'] ?? '';
$categoryId = $filters['category_id'] ?? '';
$status = $filters['status'] ?? '';

$exportParams = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$exportUrl = Router::url('/products/export') . ($exportParams !== '' ? '?' . $exportParams : '');
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Products</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= Router::url('/products/import') ?>" class="btn btn-secondary">Import CSV</a>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary">Export CSV</a>
            <a href="<?= Router::url('/products/create') ?>" class="btn btn-primary">+ Add Product</a>
        </div>
    </div>

    <form method="get" action="<?= Router::url('/products') ?>" class="filter-form">
        <input type="search" name="q" placeholder="Search products..." value="<?= htmlspecialchars($q ?? '') ?>">
        <select name="category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($categoryId ?? '') == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Stock</option>
            <option value="in" <?= (($status ?? '') === 'in') ? 'selected' : '' ?>>In Stock</option>
            <option value="low" <?= (($status ?? '') === 'low') ? 'selected' : '' ?>>Low Stock</option>
        </select>
        <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($hasFilter): ?>
            <a href="<?= Router::url('/products') ?>" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($products)): ?>
        <p class="empty-state"><?= $hasFilter ? 'No products match your filters.' : 'No products yet. Add your first one to get started.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>SKU</th><th>Name</th><th>Gallery</th><th>Category</th><?php include __DIR__ . '/../partials/custom_fields_headers.php'; ?><th>Cost</th><th>Price</th><th>Qty</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <?php $gallery = json_decode($p['gallery'] ?? '[]', true) ?: []; ?>
            <?php $attrs = json_decode($p['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($p['sku']) ?></td>
                <td><a href="<?= Router::url('/products/' . $p['id']) ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                <td class="gallery-cell">
                    <?php if (!empty($gallery)): ?>
                        <div class="gallery-thumbs">
                            <?php foreach (array_slice($gallery, 0, 3) as $img): ?>
                                <img src="<?= Router::url('/uploads/products/' . $img) ?>"
                                     alt="<?= htmlspecialchars($p['name']) ?>"
                                     class="gallery-thumb-img lightbox-trigger"
                                     data-full="<?= Router::url('/uploads/products/' . $img) ?>">
                            <?php endforeach; ?>
                            <?php if (count($gallery) > 3): ?>
                                <span class="gallery-more">+<?= count($gallery) - 3 ?></span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <?php include __DIR__ . '/../partials/custom_fields_cells.php'; ?>
                <td>$<?= number_format($p['cost_price'], 2) ?></td>
                <td>$<?= number_format($p['sale_price'], 2) ?></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td>
                    <?php if ($p['quantity'] <= $p['reorder_level']): ?>
                        <span class="badge badge-red">Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-green">In Stock</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="<?= Router::url('/products/' . $p['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php $deleteUrl = Router::url('/products/' . $p['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>

<!-- Lightbox Overlay -->
<div class="lightbox-overlay" id="lightboxOverlay">
    <button class="lightbox-close" id="lightboxClose">&times;</button>
    <button class="lightbox-prev" id="lightboxPrev">&#8249;</button>
    <button class="lightbox-next" id="lightboxNext">&#8250;</button>
    <div class="lightbox-content">
        <img src="" alt="" id="lightboxImage">
    </div>
    <div class="lightbox-counter" id="lightboxCounter"></div>
</div>

<script>
(function() {
    const overlay = document.getElementById('lightboxOverlay');
    const img = document.getElementById('lightboxImage');
    const counter = document.getElementById('lightboxCounter');
    const closeBtn = document.getElementById('lightboxClose');
    const prevBtn = document.getElementById('lightboxPrev');
    const nextBtn = document.getElementById('lightboxNext');

    let currentImages = [];
    let currentIndex = 0;

    function openLightbox(sources, index) {
        currentImages = sources;
        currentIndex = index;
        showImage();
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function showImage() {
        img.src = currentImages[currentIndex];
        counter.textContent = (currentIndex + 1) + ' / ' + currentImages.length;
        prevBtn.style.display = currentImages.length > 1 ? 'block' : 'none';
        nextBtn.style.display = currentImages.length > 1 ? 'block' : 'none';
    }

    function prev() {
        currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
        showImage();
    }

    function next() {
        currentIndex = (currentIndex + 1) % currentImages.length;
        showImage();
    }

    // Click on any thumbnail in a row opens all images from that row
    document.querySelectorAll('.gallery-thumbs').forEach(container => {
        const thumbs = container.querySelectorAll('.lightbox-trigger');
        const sources = Array.from(thumbs).map(t => t.dataset.full);
        thumbs.forEach((thumb, i) => {
            thumb.addEventListener('click', () => openLightbox(sources, i));
        });
    });

    closeBtn.addEventListener('click', closeLightbox);
    prevBtn.addEventListener('click', prev);
    nextBtn.addEventListener('click', next);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeLightbox();
    });

    document.addEventListener('keydown', function(e) {
        if (!overlay.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') prev();
        if (e.key === 'ArrowRight') next();
    });
})();
</script>
