<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;

$user = Auth::check();
if ($user) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if ($pass !== $pass2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $res = Auth::register($name, $email, $pass);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            // Auto-login after register
            Auth::login($email, $pass);
            Flash::success('Selamat datang! Lengkapi profil kesehatan Anda untuk rekomendasi yang akurat.');
            redirect('user/profile.php');
        }
    }
}

$pageTitle = 'Daftar — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <a href="<?= e(BASE_URL) ?>/" class="auth-brand">
            <span class="brand-mark">🛒</span>
            <span>Smar<span class="brand-accent">Cery</span></span>
        </a>
        <h1>Buat akun baru</h1>
        <p class="muted">Daftar gratis dan dapatkan rekomendasi produk sesuai diet & alergen Anda.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <label class="field">
                <span class="field-label">Nama lengkap</span>
                <input type="text" name="name" required minlength="2" value="<?= e($_POST['name'] ?? '') ?>">
            </label>
            <label class="field">
                <span class="field-label">Email</span>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
            </label>
            <label class="field">
                <span class="field-label">Password (min. 8 karakter)</span>
                <input type="password" name="password" required minlength="8">
            </label>
            <label class="field">
                <span class="field-label">Konfirmasi password</span>
                <input type="password" name="password_confirm" required minlength="8">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Daftar</button>
        </form>

        <p class="auth-foot">
            Sudah punya akun? <a href="<?= e(BASE_URL) ?>/login.php">Masuk</a>
        </p>
    </div>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>
