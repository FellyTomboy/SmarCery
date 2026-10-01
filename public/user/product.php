<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Repository\BookmarkRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProfileRepository;
use App\Service\ProductService;
use App\Service\RecommendationService;

Auth::requireAuth();

$id = (string)($_GET['id'] ?? '');
if (!$id) {
    \App\Core\Flash::error('Produk tidak ditemukan.');
    redirect('user/browse.php');
}

$svc = new ProductService(
    new \App\Repository\ProductRepository(),
    new CategoryRepository(),
    new \App\Repository\GraphRepository(),
);

$product = $svc->getDetail($id);
if (!$product) {
    \App\Core\Flash::error('Produk tidak ditemukan.');
    redirect('user/browse.php');
}

$uid = Auth::id();
$bookmarked = (new BookmarkRepository())->isBookmarked($uid, $id);

$recommendationSvc = new RecommendationService(
    new \App\Repository\GraphRepository(),
    new \App\Repository\ProductRepository(),
    new ProfileRepository(),
    new BookmarkRepository(),
    new CategoryRepository(),
);
$alternatives = $recommendationSvc->getAlternatives($id, $uid, 3);

$attrs   = $product['attributes'] ?? [];
$nutri   = $attrs['nutrition_per_100g'] ?? $attrs['nutrition_per_100ml'] ?? null;
$ings    = $attrs['ingredients'] ?? [];
$algs    = $product['allergens'] ?? [];

$pageTitle = e($product['name']) . ' — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container container-narrow">
    <article class="product-detail">
        <a href="<?= e(BASE_URL) ?>/user/browse.php" class="back-link">← Kembali ke katalog</a>

        <header class="product-head">
            <div class="product-head-media">
                <?php if (!empty($product['image_url'])): ?>
                    <img src="<?= e(BASE_URL . $product['image_url']) ?>" alt="<?= e($product['name']) ?>"
                         onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'product-media-placeholder big',textContent:'<?= e($product['category']['icon'] ?? '🛍️') ?>'}))">
                <?php else: ?>
                    <div class="product-media-placeholder big"><?= e($product['category']['icon'] ?? '🛍️') ?></div>
                <?php endif; ?>
            </div>
            <div class="product-head-info">
                <?php if (!empty($product['category'])): ?>
                    <span class="product-cat"><?= e($product['category']['name']) ?></span>
                <?php endif; ?>
                <h1><?= e($product['name']) ?></h1>
                <?php if (!empty($product['brand'])): ?>
                    <p class="muted">Merek: <?= e($product['brand']) ?></p>
                <?php endif; ?>

                <div class="badges">
                    <?php if (!empty($attrs['vegan'])): ?>
                        <span class="product-badge badge-vegan">🌱 Vegan</span>
                    <?php endif; ?>
                    <?php if (!empty($attrs['halal_certified'])): ?>
                        <span class="product-badge badge-halal">🕌 Halal</span>
                    <?php endif; ?>
                    <?php if (!empty($product['barcode'])): ?>
                        <span class="product-badge badge-muted">EAN: <?= e($product['barcode']) ?></span>
                    <?php endif; ?>
                </div>

                <p class="big-price"><?= rupiah((float)($product['price'] ?? 0)) ?></p>

                <button type="button" class="bookmark-toggle btn <?= $bookmarked ? 'btn-primary' : 'btn-outline' ?>"
                        data-id="<?= e($id) ?>" data-bookmarked="<?= $bookmarked ? '1' : '0' ?>">
                    <?= $bookmarked ? '♥ Favorit' : '♡ Tambah ke favorit' ?>
                </button>

                <a href="<?= e(BASE_URL) ?>/user/contribute.php?product=<?= e($id) ?>" class="btn btn-ghost btn-sm">
                    📝 Koreksi info / klaim alergen
                </a>
            </div>
        </header>

        <section class="card">
            <h2>Informasi Nutrisi</h2>
            <?php if ($nutri): ?>
                <table class="nutrition-table">
                    <thead>
                        <tr><th>Zat</th><th>Jumlah</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nutri as $key => $val): ?>
                            <tr>
                                <td><?= e(ucwords(str_replace('_', ' ', (string)$key))) ?></td>
                                <td><?= e((string)$val) ?> <?= str_contains((string)$key, 'g') ? 'g' : (str_contains((string)$key, 'mg') ? 'mg' : '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="muted small">* Per 100<?= !empty($attrs['nutrition_per_100ml']) ? 'ml' : 'g' ?></p>
            <?php else: ?>
                <p class="muted">Informasi nutrisi belum tersedia untuk produk ini.</p>
            <?php endif; ?>
        </section>

        <?php if (!empty($ings)): ?>
            <section class="card">
                <h2>Komposisi / Bahan</h2>
                <ul class="ingredient-list">
                    <?php foreach ($ings as $ing): ?>
                        <li><?= e($ing) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Alergen</h2>
            <?php if (!empty($algs)): ?>
                <div class="allergen-row">
                    <?php foreach ($algs as $a): ?>
                        <span class="allergen-chip"><?= e($a) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Produk ini tidak mengandung alergen umum. ⚠️ Tetap cek komposisi untuk alergen pribadi Anda.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Alternatif Aman untuk Anda</h2>
            <?php if (empty($alternatives)): ?>
                <p class="muted">Belum ada alternatif yang cocok dengan profil Anda.</p>
            <?php else: ?>
                <div class="product-grid small">
                    <?php foreach ($alternatives as $alt): ?>
                        <?php
                        $product = $alt;
                        $category = $product['category'] ?? $product['category_id'] ?? null;
                        if (is_int($category)) {
                            $category = (new CategoryRepository())->find($category);
                        }
                        $bookmarked = false;
                        include VIEWS_PATH . '/partials/product_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </article>
</main>
<?php
$pageScripts = ['js/bookmark.js'];
include VIEWS_PATH . '/layouts/footer.php';
?>