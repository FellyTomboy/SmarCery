<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Repository\BookmarkRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;

Auth::requireAuth();

$bm = new BookmarkRepository();
$ids = $bm->listForUser(Auth::id());

$products = (new ProductRepository())->findMany($ids);
$catRepo  = new CategoryRepository();

$pageTitle = 'Favorit — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <h1>Favorit Saya</h1>
        <p class="muted"><?= count($ids) ?> produk tersimpan</p>
    </header>

    <?php if (empty($ids)): ?>
        <div class="empty-state">
            <p>Anda belum menyimpan produk favorit. Klik ♡ pada produk untuk menambahkannya.</p>
            <a href="<?= e(BASE_URL) ?>/user/browse.php" class="btn btn-primary">Jelajahi katalog</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($ids as $pid): ?>
                <?php if (!isset($products[$pid])) continue; ?>
                <?php
                $p = $products[$pid];
                $product = $p;
                $category = $catRepo->find((int)($p['category_id'] ?? 0));
                $bookmarked = true;
                include VIEWS_PATH . '/partials/product_card.php';
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php
$pageScripts = ['js/bookmark.js'];
include VIEWS_PATH . '/layouts/footer.php';
?>