<?php
/**
 * Pagination partial. Expects $pagination = ['page'=>int, 'pages'=>int, 'total'=>int, 'perPage'=>int]
 * and $paginationUrl (optional) — base URL without page param. Defaults to current URL.
 */
use App\Core\Router;

$p = $pagination ?? null;
if (!$p || $p['pages'] <= 1) return;

$currentPage = $p['page'];
$totalPages  = $p['pages'];
$total       = $p['total'];
$perPage     = $p['perPage'];

// Build base URL preserving current query params except 'page'
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
if (!empty($queryParams)) {
    $baseUrl .= '?' . http_build_query($queryParams) . '&';
} else {
    $baseUrl .= '?';
}

// Calculate visible page range (show up to 7 page buttons)
$range = 2;
$start = max(1, $currentPage - $range);
$end   = min($totalPages, $currentPage + $range);
if ($start > 1) $start = max(1, min($start, $end - 4));
if ($end < $totalPages) $end = min($totalPages, max($end, $start + 4));

$from = ($currentPage - 1) * $perPage + 1;
$to   = min($currentPage * $perPage, $total);
?>
<div class="pagination-wrap">
    <span class="pagination-info">Showing <?= $from ?>–<?= $to ?> of <?= $total ?></span>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a href="<?= $baseUrl ?>page=<?= $currentPage - 1 ?>" class="page-link">&laquo;</a>
        <?php else: ?>
            <span class="page-link disabled">&laquo;</span>
        <?php endif; ?>

        <?php if ($start > 1): ?>
            <a href="<?= $baseUrl ?>page=1" class="page-link">1</a>
            <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $currentPage): ?>
                <span class="page-link active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= $baseUrl ?>page=<?= $i ?>" class="page-link"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
            <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="page-link"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= $baseUrl ?>page=<?= $currentPage + 1 ?>" class="page-link">&raquo;</a>
        <?php else: ?>
            <span class="page-link disabled">&raquo;</span>
        <?php endif; ?>
    </div>
</div>
