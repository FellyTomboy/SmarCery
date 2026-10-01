<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\ProductService;

Auth::requireAdmin();

$repo    = new ProductRepository();
$cats    = (new CategoryRepository())->all();
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$query   = trim((string)($_GET['q'] ?? ''));
$catId    = (int)($_GET['category'] ?? 0);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::checkOrFail();
    $pid = (string)($_POST['product_id'] ?? '');
    $svc = new ProductService($repo, new CategoryRepository(), new \App\Repository\GraphRepository());
    if ($svc->delete($pid)) {
        Flash::success('Produk dihapus.');
    } else {
        Flash::error('Produk tidak ditemukan.');
    }
    redirect('admin/products.php');
}

$result = $query !== ''
    ? $repo->search($query, $page, $perPage)
    : $repo->paginate(array_merge(['is_active' => true], $catId > 0 ? ['category_id' => $catId] : []), $page, $perPage);

$pageTitle = 'Kelola Produk — Admin';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <div>
            <h1>Produk</h1>
            <p class="muted">CRUD produk dan sinkronisasi otomatis ke graf Neo4j.</p>
        </div>
        <a href="<?= e(BASE_URL) ?>/admin/product_form.php" class="btn btn-primary">+ Tambah Produk</a>
    </header>

    <form method="get" class="search-bar">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="Cari produk...">
        <select name="category">
            <option value="0">Semua kategori</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e(str_repeat('— ', max(0, $c['parent_id'] ? 1 : 0)) . $c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Cari</button>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Alergen</th><th>Aktif</th><th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($result['items'])): ?>
                <tr><td colspan="7" class="empty-state">Belum ada produk.</td></tr>
            <?php else: ?>
                <?php foreach ($result['items'] as $p): ?>
                    <?php $cat = array_filter($cats, fn($c) => (int)$c['id'] === (int)($p['category_id'] ?? 0)); $cat = reset($cat) ?: null; ?>
                    <tr>
                        <td>
                            <strong><?= e($p['name']) ?></strong><br>
                            <span class="muted small"><?= e($p['brand'] ?? '') ?></span>
                        </td>
                        <td><?= e($cat['name'] ?? '-') ?></td>
                        <td><?= rupiah((float)($p['price'] ?? 0)) ?></td>
                        <td><?= (int)($p['stock'] ?? 0) ?></td>
                        <td>
                            <?php foreach (($p['allergens'] ?? []) as $a): ?>
                                <span class="allergen-chip"><?= e($a) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?= !empty($p['is_active']) ? '✓' : '—' ?></td>
                        <td class="row-actions">
                            <a href="<?= e(BASE_URL) ?>/admin/product_form.php?id=<?= e($p['_id']) ?>" class="btn btn-outline btn-sm">Edit</a>
                            <a href="<?= e(BASE_URL) ?>/user/product.php?id=<?= e($p['_id']) ?>" class="btn btn-ghost btn-sm">Lihat</a>
                            <form method="post" class="inline" onsubmit="return confirm('Hapus produk ini?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?= e($p['_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $total = $result['total'];
    $baseUrl = 'products.php?' . http_build_query(array_filter(['q' => $query ?: null, 'category' => $catId ?: null]));
    include VIEWS_PATH . '/partials/pagination.php';
    ?>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>