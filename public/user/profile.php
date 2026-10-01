<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Repository\AllergenRepository;
use App\Repository\GraphRepository;
use App\Repository\ProfileRepository;

Auth::requireAuth();

$uid = Auth::id();
$allergenRepo = new AllergenRepository();
$allAllergens = $allergenRepo->all();
$profileRepo  = new ProfileRepository();
$profile      = $profileRepo->getProfile($uid);
$myAllergens  = $profileRepo->getAllergens($uid);
$mySlugs      = array_column($myAllergens, 'slug');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();

    $diets = $_POST['diet'] ?? [];
    if (!is_array($diets)) $diets = [];
    $diets = array_values(array_intersect($diets, ['vegan', 'vegetarian', 'halal', 'keto']));

    $selected = $_POST['allergens'] ?? [];
    if (!is_array($selected)) $selected = [];
    $selected = array_values(array_filter($selected, fn($s) => in_array($s, array_column($allAllergens, 'slug'), true)));

    $notes = trim((string)($_POST['other_notes'] ?? ''));
    if (mb_strlen($notes) > 500) $notes = mb_substr($notes, 0, 500);

    $graph = new GraphRepository();
    $profileRepo->updateProfile($uid, $diets, $selected, $notes, $graph);

    // Sync user node (idempotent)
    try {
        $graph->syncUser($uid, Auth::check()['name']);
    } catch (\Throwable $e) { error_log($e->getMessage()); }

    \App\Core\Flash::success('Profil kesehatan tersimpan.');
    redirect('user/profile.php');
}

$pageTitle = 'Profil Kesehatan — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container container-narrow">
    <header class="page-head">
        <h1>Profil Kesehatan</h1>
        <p class="muted">Atur diet dan alergen Anda untuk rekomendasi yang akurat.</p>
    </header>

    <form method="post" class="card form">
        <?= Csrf::field() ?>

        <fieldset class="form-group">
            <legend>Preferensi Diet</legend>
            <div class="checkbox-grid">
                <?php $currentDiets = $profile['diet_tags'] ?? []; ?>
                <?php foreach (['vegan' => '🌱 Vegan', 'vegetarian' => '🥗 Vegetarian', 'halal' => '🕌 Halal', 'keto' => '🥩 Keto'] as $key => $label): ?>
                    <label class="check-pill">
                        <input type="checkbox" name="diet[]" value="<?= e($key) ?>" <?= in_array($key, $currentDiets, true) ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <legend>Alergen yang Dihindari</legend>
            <p class="muted small">Centang alergen yang ingin Anda hindari. Sistem akan menyembunyikan produk yang mengandung alergen ini.</p>
            <div class="checkbox-grid">
                <?php foreach ($allAllergens as $a): ?>
                    <label class="check-pill">
                        <input type="checkbox" name="allergens[]" value="<?= e($a['slug']) ?>" <?= in_array($a['slug'], $mySlugs, true) ? 'checked' : '' ?>>
                        <span><?= e(($a['icon'] ?? '') . ' ' . $a['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <label class="field">
            <span class="field-label">Catatan tambahan</span>
            <textarea name="other_notes" rows="3" maxlength="500" placeholder="mis. Tidak suka pedas, preferensi rendah gula, dll."><?= e($profile['other_notes'] ?? '') ?></textarea>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan profil</button>
        </div>
    </form>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>