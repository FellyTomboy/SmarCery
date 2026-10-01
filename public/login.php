<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Flash;

$user = Auth::check();
if ($user) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';

    if (!Auth::login($email, $pass)) {
        $error = 'Email atau password salah.';
    } else {
        Flash::success('Selamat datang kembali!');
        $logged = Auth::check();
        redirect($logged['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php');
    }
}

$pageTitle = 'Login — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <a href="<?= e(BASE_URL) ?>/" class="auth-brand">
            <span class="brand-mark">🛒</span>
            <span>Smar<span class="brand-accent">Cery</span></span>
        </a>
        <h1>Masuk ke akun Anda</h1>
        <p class="muted">Lanjut belanja pintar sesuai profil kesehatan Anda.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <label class="field">
                <span class="field-label">Email</span>
                <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
            </label>
            <label class="field">
                <span class="field-label">Password</span>
                <input type="password" name="password" required minlength="8">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>

        <p class="auth-foot">
            Belum punya akun? <a href="<?= e(BASE_URL) ?>/register.php">Daftar</a>
        </p>
    </div>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>
