<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\ContributionRepository;
use App\Service\ContributionService;

Auth::requireAdmin();

$repo = new ContributionRepository();
$svc  = new ContributionService($repo, new \App\Repository\ProductRepository());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();
    $action = $_POST['action'] ?? '';
    $cid    = (string)($_POST['contribution_id'] ?? '');
    if ($action === 'approve') {
        $svc->approve($cid, Auth::id());
        Flash::success('Disetujui.');
    } elseif ($action === 'reject') {
        $svc->reject($cid, Auth::id());
        Flash::success('Ditolak.');
    }
    redirect('admin/reviews.php?status=' . urlencode($_GET['status'] ?? 'pending'));
}

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) $status = 'pending';
$list = $repo->listByStatus($status, max(1, (int)($_GET['page'] ?? 1)), 20);

$pageTitle = 'Review — Admin';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <h1>Moderasi Kontribusi</h1>
        <nav class="tabbar">
            <a href="?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?status=approved" class="<?= $status === 'approved' ? 'active' : '' ?>">Disetujui</a>
            <a href="?status=rejected" class="<?= $status === 'rejected' ? 'active' : '' ?>">Ditolak</a>
            <a href="?status=all" class="<?= $status === 'all' ? 'active' : '' ?>">Semua</a>
        </nav>
    </header>

    <?php if (empty($list)): ?>
        <div class="empty-state"><p>Tidak ada kontribusi dengan status <strong><?= e($status) ?></strong>.</p></div>
    <?php else: ?>
        <div class="contribution-list">
            <?php foreach ($list as $c): ?>
                <?php
                    $pid = $c['product_id']['$oid'] ?? ($c['product_id'] ?? '');
                    $typeLabel = ContributionService::TYPES[$c['type']] ?? $c['type'];
                ?>
                <article class="card">
                    <header>
                        <strong><?= e($typeLabel) ?></strong>
                        <span class="muted">— dari user #<?= (int)($c['user_id'] ?? 0) ?> · <?= tanggal_id($c['submitted_at']['$date']['$numberLong'] ? date('c', (int)$c['submitted_at']['$date']['$numberLong']/1000) : null) ?></span>
                    </header>
                    <p><strong>Produk:</strong> <a href="<?= e(BASE_URL) ?>/user/product.php?id=<?= e($pid) ?>" target="_blank"><?= e($pid) ?></a></p>
                    <?php if (!empty($c['payload']['new_value'])): ?>
                        <p><strong>Nilai:</strong> <?= e($c['payload']['new_value']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($c['payload']['note'])): ?>
                        <p><?= e($c['payload']['note']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($c['attachments'])): ?>
                        <div class="attachments">
                            <?php foreach ($c['attachments'] as $a): ?>
                                <a href="<?= e(upload_url($a)) ?>" target="_blank">📎 <?= e(basename($a)) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($status === 'pending'): ?>
                        <div class="row-actions">
                            <form method="post" class="inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="contribution_id" value="<?= e($c['_id']) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Setujui</button>
                            </form>
                            <form method="post" class="inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="contribution_id" value="<?= e($c['_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span class="badge badge-<?= e($c['status']) ?>"><?= e($c['status']) ?></span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>