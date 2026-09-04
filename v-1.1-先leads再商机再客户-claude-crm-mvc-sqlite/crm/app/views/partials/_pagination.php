<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<!-- Pagination partial — expects $page, $totalPages, and a $baseUrl variable -->
<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-between align-items-center mt-3 px-1">
    <small class="text-muted">
        第 <?= $page ?> / <?= $totalPages ?> 页，共 <?= $total ?? '' ?> 条
    </small>
    <ul class="pagination pagination-sm mb-0">
        <!-- Prev -->
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($baseUrl . ($page - 1)) ?>">&laquo;</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e($baseUrl . '1') ?>">1</a></li>
            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($baseUrl . $i) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= e($baseUrl . $totalPages) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>
        <!-- Next -->
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($baseUrl . ($page + 1)) ?>">&raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
