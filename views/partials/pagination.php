<?php
/**
 * Pagination partial.
 * Expects:
 *   $page (int)
 *   $perPage (int)
 *   $total (int)
 *   $baseUrl (string) e.g. 'user/browse.php?category=2'
 */
$totalPages = max(1, (int)ceil($total / $perPage));
if ($totalPages <= 1) return;
$baseUrl = $baseUrl ?? '';
// Inject/replace page= query param
function page_url(string $base, int $p): string {
    $sep = strpos($base, '?') !== false ? '&' : '?';
    return $base . $sep . 'page=' . $p;
}
?>
<nav class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e(page_url($baseUrl, $page - 1)) ?>" class="page-link">‹ Sebelumnya</a>
    <?php endif; ?>
    <span class="page-info">Halaman <?= (int)$page ?> dari <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="<?= e(page_url($baseUrl, $page + 1)) ?>" class="page-link">Selanjutnya ›</a>
    <?php endif; ?>
</nav>