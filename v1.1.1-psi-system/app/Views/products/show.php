<?php
use App\Core\Router;
$gallery = json_decode($product['gallery'] ?? '[]', true) ?: [];
$attrs = json_decode($product['attributes'] ?? '{}', true) ?: [];
$customFields = $customFields ?? [];
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
        <div>
            <h2 style="margin-bottom:4px;"><?= htmlspecialchars($product['name']) ?></h2>
            <p class="text-muted" style="margin-top:0;">SKU: <?= htmlspecialchars($product['sku']) ?></p>
        </div>
        <a href="<?= Router::url('/products/' . $product['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit Product</a>
    </div>

    <div class="stat-grid" style="margin-top:16px;">
        <div class="stat-card"><div class="label">Current Stock</div><div class="value"><?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></div></div>
        <div class="stat-card"><div class="label">Cost Price</div><div class="value">$<?= number_format($product['cost_price'], 2) ?></div></div>
        <div class="stat-card"><div class="label">Sale Price</div><div class="value">$<?= number_format($product['sale_price'], 2) ?></div></div>
        <div class="stat-card"><div class="label">Reorder Level</div><div class="value"><?= $product['reorder_level'] ?></div></div>
    </div>

    <?php if (!empty($customFields)): ?>
    <div style="margin-top:20px;">
        <h3>Details</h3>
        <table style="max-width:480px;">
            <tbody>
            <?php foreach ($customFields as $key => $def): ?>
                <tr>
                    <td class="text-muted" style="width:150px;"><?= htmlspecialchars($def['label']) ?></td>
                    <td><?= htmlspecialchars($attrs[$key] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($gallery)): ?>
    <div class="product-gallery">
        <h3>Gallery</h3>
        <div class="gallery-grid">
            <?php foreach ($gallery as $i => $img): ?>
                <img src="<?= Router::url('/uploads/products/' . $img) ?>"
                     alt="<?= htmlspecialchars($product['name']) ?> <?= $i + 1 ?>"
                     class="gallery-grid-img lightbox-trigger"
                     data-full="<?= Router::url('/uploads/products/' . $img) ?>">
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Stock Movement History</h3>
    <?php if (empty($transactions)): ?>
        <p class="empty-state">No stock movements recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Change</th><th>Balance After</th><th>Reference</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                <td>
                    <?php $badge = ['purchase' => 'badge-blue', 'sale' => 'badge-green', 'adjustment' => 'badge-amber'][$t['type']] ?? 'badge-gray'; ?>
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($t['type']) ?></span>
                </td>
                <td><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : $t['qty_change'] ?></td>
                <td><?= $t['balance_after'] ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['reference']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
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

    const thumbs = document.querySelectorAll('.gallery-grid .lightbox-trigger');
    const sources = Array.from(thumbs).map(t => t.dataset.full);
    thumbs.forEach((thumb, i) => {
        thumb.addEventListener('click', () => openLightbox(sources, i));
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
