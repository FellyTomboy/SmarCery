<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\UserRepository;

Auth::requireAdmin();

$repo = new UserRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();
    $id   = (int)$_POST['id'];
    $role = $_POST['role'] ?? 'user';
    if ($repo->setRole($id, $role)) {
        Flash::success('Role diperbarui.');
    } else {
        Flash::error('Gagal memperbarui role.');
    }
    redirect('admin/users.php');
}

$users = $repo->listAll(1, 100);

$pageTitle = 'User — Admin';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container">
    <header class="page-head"><h1>Pengguna</h1></header>
    <table class="data-table">
        <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Bergabung</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= e($u['role']) ?></span></td>
                    <td><?= tanggal_id($u['created_at'], false) ?></td>
                    <td>
                        <form method="post" class="inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <select name="role">
                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Ubah</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>