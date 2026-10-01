<?php
/**
 * Common <head> + opening tags.
 * Expects $pageTitle to be set by caller.
 */
if (!isset($pageTitle)) $pageTitle = 'SmarCery';
$_csrf = \App\Core\Csrf::token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/reset.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/pages.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🛒%3C/text%3E%3C/svg%3E">
    <meta name="csrf-token" content="<?= e($_csrf) ?>">
    <script>
      window.SMARCERY = {
        baseUrl: '<?= e(BASE_URL) ?>',
        csrf: '<?= e($_csrf) ?>'
      };
    </script>
</head>
<body>
<?php
// Top navbar (only when not on auth pages)
$authPage = in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['login.php', 'register.php']);
$navUser  = \App\Core\Auth::check();
if (!$authPage && $navUser):
?>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="<?= e(BASE_URL) ?>/<?= $navUser['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php' ?>" class="brand">
                <span class="brand-mark">🛒</span>
                <span>Smar<span class="brand-accent">Cery</span></span>
            </a>
            <nav class="topnav">
                <?php if ($navUser['role'] === 'admin'): ?>
                    <a href="<?= e(BASE_URL) ?>/admin/dashboard.php">Dashboard</a>
                    <a href="<?= e(BASE_URL) ?>/admin/products.php">Produk</a>
                    <a href="<?= e(BASE_URL) ?>/admin/categories.php">Kategori</a>
                    <a href="<?= e(BASE_URL) ?>/admin/allergens.php">Alergen</a>
                    <a href="<?= e(BASE_URL) ?>/admin/reviews.php">Review</a>
                    <a href="<?= e(BASE_URL) ?>/admin/users.php">User</a>
                <?php else: ?>
                    <a href="<?= e(BASE_URL) ?>/user/dashboard.php">Beranda</a>
                    <a href="<?= e(BASE_URL) ?>/user/browse.php">Katalog</a>
                    <a href="<?= e(BASE_URL) ?>/user/bookmarks.php">Favorit</a>
                    <a href="<?= e(BASE_URL) ?>/user/transactions.php">Riwayat</a>
                <?php endif; ?>
            </nav>
            <div class="topbar-right">
                <?php if ($navUser['role'] !== 'admin'): ?>
                    <a href="<?= e(BASE_URL) ?>/user/profile.php" class="profile-chip" title="Profil">
                        <span class="avatar"><?= e(initials($navUser['name'])) ?></span>
                        <span class="profile-name"><?= e($navUser['name']) ?></span>
                    </a>
                <?php endif; ?>
                <a href="<?= e(BASE_URL) ?>/logout.php" class="btn btn-ghost btn-sm">Keluar</a>
            </div>
        </div>
    </header>
<?php endif; ?>

<?php
// Flash messages
$flashes = \App\Core\Flash::pullAll();
if (!empty($flashes)):
?>
<div class="flash-stack">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
</div>
<script>
  setTimeout(() => document.querySelectorAll('.flash').forEach(el => el.remove()), 4000);
</script>
<?php endif; ?>
