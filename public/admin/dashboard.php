<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Repository\ContributionRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;

Auth::requireAdmin();

$productCount = (new ProductRepository())->countActive();
$pendingCount = (new ContributionRepository())->countByStatus('pending');
$userCount    = (new UserRepository())->countAll();

$pageTitle = 'Admin Dashboard — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <h1>Admin Dashboard</h1>
        <p class="muted">Pantau data master dan moderasi kontribusi.</p>
    </header>

    <section class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Produk aktif</span>
            <span class="kpi-value"><?= short_number($productCount) ?></span>
            <a href="<?= e(BASE_URL) ?>/admin/products.php" class="kpi-action">Kelola →</a>
        </div>
        <div class="kpi-card">
            <span class="kpi-label">Review pending</span>
            <span class="kpi-value <?= $pendingCount > 0 ? 'warn' : '' ?>"><?= short_number($pendingCount) ?></span>
            <a href="<?= e(BASE_URL) ?>/admin/reviews.php" class="kpi-action">Moderasi →</a>
        </div>
        <div class="kpi-card">
            <span class="kpi-label">User terdaftar</span>
            <span class="kpi-value"><?= short_number($userCount) ?></span>
            <a href="<?= e(BASE_URL) ?>/admin/users.php" class="kpi-action">Lihat →</a>
        </div>
    </section>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>