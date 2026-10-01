<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\AllergenRepository;

Auth::requireAdmin();

$repo = new AllergenRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'icon' => $_POST['icon'] ?? '',
        ];
        if ($data['name'] === '' || $data['slug'] === '') {
            Flash::error('Nama dan slug wajib diisi.');
        } else {
            try {
                if ($action === 'create') $repo->create($data);
                else $repo->update((int)$_POST['id'], $data);
                Flash::success('Tersimpan.');
            } catch (\Throwable $e) {
                Flash::error('Gagal: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        try {
            $repo->delete((int)$_POST['id']);
            Flash::success('Dihapus.');
        } catch (\Throwable $e) {
            Flash::error('Gagal: ' . $e->getMessage());
        }
    }
    redirect('admin/allergens.php');
}

$list = $repo->all();

$pageTitle = 'Alergen — Admin';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head"><h1>Master Alergen</h1></header>

    <details class="card" open>
        <summary><strong>Tambah Alergen</strong></summary>
        <form method="post" class="form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <div class="grid-3">
                <label class="field"><span class="field-label">Nama</span><input type="text" name="name" required></label>
                <label class="field"><span class="field-label">Slug</span><input type="text" name="slug" required></label>
                <label class="field"><span class="field-label">Icon</span><input type="text" name="icon" maxlength="4"></label>
            </div>
            <button type="submit" class="btn btn-primary">Tambah</button>
        </form>
    </details>

    <table class="data-table">
        <thead><tr><th>ID</th><th>Nama</th><th>Slug</th><th>Icon</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($list as $a): ?>
                <tr>
                    <td><?= (int)$a['id'] ?></td>
                    <td><?= e($a['name']) ?></td>
                    <td><code><?= e($a['slug']) ?></code></td>
                    <td><?= e($a['icon'] ?? '') ?></td>
                    <td class="row-actions">
                        <details>
                            <summary class="btn btn-outline btn-sm">Edit</summary>
                            <form method="post" class="form-inline card">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <input type="text" name="name" value="<?= e($a['name']) ?>" required>
                                <input type="text" name="slug" value="<?= e($a['slug']) ?>" required>
                                <input type="text" name="icon" value="<?= e($a['icon'] ?? '') ?>" maxlength="4">
                                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                            </form>
                        </details>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus alergen?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>