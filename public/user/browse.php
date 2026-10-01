<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProfileRepository;

Auth::requireAuth();

$catRepo = new CategoryRepository();
$tree    = $catRepo->tree();

$selectedCat = (int)($_GET['category'] ?? 0);
$query       = trim((string)($_GET['q'] ?? ''));
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 12;

$productRepo = new ProductRepository();
$allergens   = (new ProfileRepository())->getAllergenSlugs(Auth::id());

if ($query !== '') {
    $result = $productRepo->search($query, $page, $perPage, $allergens);
    $title  = 'Hasil pencarian: "' . e($query) . '"';
} elseif ($selectedCat > 0) {
    $result = $productRepo->listByCategory($selectedCat, $page, $perPage, $allergens);
    $cat    = $catRepo->find($selectedCat);
    $title  = $cat ? $cat['name'] : 'Kategori';
} else {
    $result = $productRepo->paginate(['is_active' => true, 'allergens' => ['$nin' => $allergens]], $page, $perPage);
    $title  = 'Semua Produk';
}

$pageTitle = 'Katalog — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <h1><?= e($title) ?></h1>
        <form method="get" class="search-bar">
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Cari produk, merek, atau tag...">
            <button type="submit">Cari</button>
        </form>
    </header>

    <div class="browse-layout">
        <aside class="browse-side">
            <h3>Kategori</h3>
            <ul class="category-tree">
                <?php foreach ($tree as $top): ?>
                    <li>
                        <a href="?category=<?= (int)$top['id'] ?>" class="<?= $selectedCat === (int)$top['id'] ? 'active' : '' ?>">
                            <?= e(($top['icon'] ?? '') . ' ' . $top['name']) ?>
                        </a>
                        <?php if (!empty($top['children'])): ?>
                            <ul>
                                <?php foreach ($top['children'] as $child): ?>
                                    <li>
                                        <a href="?category=<?= (int)$child['id'] ?>" class="<?= $selectedCat === (int)$child['id'] ? 'active' : '' ?>">
                                            <?= e($child['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($allergens)): ?>
                <div class="side-note">
                    🔒 Menyembunyikan produk dengan alergen:
                    <?php foreach ($allergens as $a): ?>
                        <span class="allergen-chip"><?= e($a) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <section class="browse-main">
            <?php if (empty($result['items'])): ?>
                <div class="empty-state">
                    <p>Tidak ada produk ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($result['items'] as $p): ?>
                        <?php
                        $product = $p;
                        $category = $catRepo->find((int)($p['category_id'] ?? 0));
                        $bookmarked = false;
                        include VIEWS_PATH . '/partials/product_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
                <?php
                $total = $result['total'];
                $baseUrl = 'browse.php?' . http_build_query(array_filter([
                    'category' => $selectedCat ?: null,
                    'q'        => $query !== '' ? $query : null,
                ]));
                include VIEWS_PATH . '/partials/pagination.php';
                ?>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php
$pageScripts = ['js/bookmark.js'];
include VIEWS_PATH . '/layouts/footer.php';
?>