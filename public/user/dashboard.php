<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Repository\ProfileRepository;
use App\Repository\GraphRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BookmarkRepository;
use App\Service\RecommendationService;

Auth::requireAuth();

$uid = Auth::id();

$profiles = new ProfileRepository();
$allergens = $profiles->getAllergens($uid);
$diets     = $profiles->getDietTags($uid);

$svc = new RecommendationService(
    new GraphRepository(),
    new ProductRepository(),
    $profiles,
    new BookmarkRepository(),
    new CategoryRepository(),
);

try {
    $recs = $svc->getRecommendations($uid, 12);
} catch (\Throwable $e) {
    $recs = [];
    error_log('Recommendation error: ' . $e->getMessage());
}

$pageTitle = 'Beranda — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <section class="hero hero-recommend">
        <div>
            <p class="eyebrow">Direkomendasikan untuk Anda</p>
            <h1>Halo, <?= e(Auth::check()['name']) ?> 👋</h1>
            <p class="muted">
                <?php if (!empty($allergens)): ?>
                    Menghindari <strong><?= count($allergens) ?></strong> alergen
                    (<?= e(implode(', ', array_column($allergens, 'name'))) ?>).
                <?php else: ?>
                    Anda belum menambahkan alergen.
                <?php endif; ?>
                <?php if (!empty($diets)): ?>
                    Diet: <strong><?= e(implode(', ', $diets)) ?></strong>.
                <?php endif; ?>
            </p>
            <a href="<?= e(BASE_URL) ?>/user/profile.php" class="btn btn-outline btn-sm">⚙️ Atur profil kesehatan</a>
        </div>
        <?php if (empty($allergens) && empty($diets)): ?>
            <div class="hero-hint">
                💡 Lengkapi profil agar rekomendasi lebih akurat.
            </div>
        <?php endif; ?>
    </section>

    <?php if (empty($recs)): ?>
        <section class="empty-state">
            <p>Belum ada rekomendasi. Coba lengkapi profil Anda atau tambahkan data produk.</p>
        </section>
    <?php else: ?>
        <section class="product-grid">
            <?php foreach ($recs as $r): ?>
                <?php include VIEWS_PATH . '/partials/product_card.php'; ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php
$pageScripts = ['js/bookmark.js'];
include VIEWS_PATH . '/layouts/footer.php';
?>