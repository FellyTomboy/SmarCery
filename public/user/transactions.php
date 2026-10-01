<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Repository\TransactionRepository;

Auth::requireAuth();
$txs = (new TransactionRepository())->listForUser(Auth::id());

$pageTitle = 'Riwayat Transaksi — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <h1>Riwayat Belanja</h1>
    </header>

    <?php if (empty($txs)): ?>
        <div class="empty-state"><p>Belum ada transaksi.</p></div>
    <?php else: ?>
        <div class="tx-list">
            <?php foreach ($txs as $tx): ?>
                <article class="card tx-card">
                    <header class="tx-head">
                        <div>
                            <strong>Transaksi #<?= (int)$tx['id'] ?></strong>
                            <span class="muted"> · <?= tanggal_id($tx['purchased_at']) ?></span>
                        </div>
                        <span class="big-price"><?= rupiah((float)$tx['total_amount']) ?></span>
                    </header>
                    <ul class="tx-items">
                        <?php foreach ($tx['items'] as $it): ?>
                            <li>
                                <?= e($it['product_name']) ?>
                                × <?= (int)$it['qty'] ?>
                                <span class="muted"><?= rupiah((float)$it['price']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>