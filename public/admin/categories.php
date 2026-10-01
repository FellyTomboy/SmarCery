<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\CategoryRepository;

Auth::requireAdmin();

$repo = new CategoryRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $data = [
            'parent_id'  => (int)($_POST['parent_id'] ?? 0),
            'name'       => trim($_POST['name'] ?? ''),
            'slug'       => trim($_POST['slug'] ?? ''),
            'icon'       => $_POST['icon'] ?? '',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
        ];
        if ($data['name'] === '' || $data['slug'] === '') {
            Flash::error('Nama dan slug wajib diisi.');
        } else {
            try {
                if ($action === 'create') {
                    $repo->create($data);
                    Flash::success('Kategori dibuat.');
                } else {
                    $repo->update((int)$_POST['id'], $data);
                    Flash::success('Kategori diperbarui.');
                }
            } catch (\Throwable $e) {
                Flash::error('Gagal: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        try {
            $repo->delete((int)$_POST['id']);
            Flash::success('Kategori dihapus.');
        } catch (\Throwable $e) {
            Flash::error('Gagal menghapus: ' . $e->getMessage());
        }
    }
    redirect('admin/categories.php');
}

$cats = $repo->all();

$pageTitle = 'Kategori — Admin';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head">
        <h1>Kategori</h1>
    </header>

    <details class="card" open>
        <summary><strong>Tambah Kategori</strong></summary>
        <form method="post" class="form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <div class="grid-4">
                <label class="field"><span class="field-label">Nama</span><input type="text" name="name" required></label>
                <label class="field"><span class="field-label">Slug</span><input type="text" name="slug" required></label>
                <label class="field"><span class="field-label">Icon (emoji)</span><input type="text" name="icon" maxlength="4"></label>
                <label class="field"><span class="field-label">Urutan</span><input type="number" name="sort_order" value="0"></label>
            </div>
            <label class="field">
                <span class="field-label">Induk</span>
                <select name="parent_id">
                    <option value="0">— Tidak ada —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-primary">Tambah</button>
        </form>
    </details>

    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Nama</th><th>Slug</th><th>Induk</th><th>Icon</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><code><?= e($c['slug']) ?></code></td>
                    <td><?= e($c['parent_id'] ? '#' . $c['parent_id'] : '-') ?></td>
                    <td><?= e($c['icon'] ?? '') ?></td>
                    <td class="row-actions">
                        <details>
                            <summary class="btn btn-outline btn-sm">Edit</summary>
                            <form method="post" class="form-inline card">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="text" name="name" value="<?= e($c['name']) ?>" required>
                                <input type="text" name="slug" value="<?= e($c['slug']) ?>" required>
                                <input type="text" name="icon" value="<?= e($c['icon'] ?? '') ?>" maxlength="4">
                                <input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>">
                                <select name="parent_id">
                                    <option value="0">— Tidak ada —</option>
                                    <?php foreach ($cats as $other): if ((int)$other['id'] === (int)$c['id']) continue; ?>
                                        <option value="<?= (int)$other['id'] ?>" <?= (int)$other['id'] === (int)$c['parent_id'] ? 'selected' : '' ?>><?= e($other['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                            </form>
                        </details>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus kategori ini?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>