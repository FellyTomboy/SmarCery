<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\ProductRepository;
use App\Service\ContributionService;

Auth::requireAuth();

$svc   = new ContributionService(new \App\Repository\ContributionRepository(), new ProductRepository());
$types = ContributionService::TYPES;
$preselectProduct = (string)($_GET['product'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();

    $productId = (string)($_POST['product_id'] ?? '');
    $type      = (string)($_POST['type'] ?? '');
    $note      = trim((string)($_POST['note'] ?? ''));
    $newValue  = trim((string)($_POST['new_value'] ?? ''));

    if (!isset($types[$type])) {
        Flash::error('Tipe kontribusi tidak valid.');
        redirect('user/contribute.php');
    }
    if (!$productId || !(new ProductRepository())->find($productId)) {
        Flash::error('Produk tidak ditemukan.');
        redirect('user/contribute.php');
    }

    $payload = ['note' => $note, 'new_value' => $newValue];

    // Handle file upload (only for packaging_photo)
    $attachments = [];
    if ($type === 'packaging_photo' && !empty($_FILES['attachment']['name'][0])) {
        $files = $_FILES['attachment'];
        if (!is_dir(PACKAGING_DIR) && !mkdir(PACKAGING_DIR, 0775, true) && !is_dir(PACKAGING_DIR)) {
            Flash::error('Gagal membuat folder upload.');
            redirect('user/contribute.php?product=' . $productId);
        }
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > 3 * 1024 * 1024) continue; // 3 MB
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;

            $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = PACKAGING_DIR . '/' . $safeName;
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $attachments[] = 'packaging/' . $safeName;
            }
        }
    }

    try {
        $svc->submit(Auth::id(), $productId, $type, $payload, $attachments);
        Flash::success('Kontribusi terkirim, menunggu moderasi admin.');
    } catch (\Throwable $e) {
        Flash::error('Gagal mengirim kontribusi: ' . $e->getMessage());
    }
    redirect('user/contribute.php?product=' . $productId);
}

$pageTitle = 'Kontribusi — SmarCery';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container container-narrow">
    <header class="page-head">
        <h1>Kontribusi & Koreksi</h1>
        <p class="muted">Bantu komunitas dengan mengoreksi info produk atau menambahkan klaim alergen.</p>
    </header>

    <form method="post" enctype="multipart/form-data" class="card form">
        <?= Csrf::field() ?>
        <label class="field">
            <span class="field-label">ID Produk (Mongo ObjectId)</span>
            <input type="text" name="product_id" required value="<?= e($preselectProduct) ?>" placeholder="contoh: 65f4e2a000000000000000a1">
            <span class="muted small">Tempel dari URL halaman produk, atau buka <a href="<?= e(BASE_URL) ?>/user/browse.php">katalog</a>.</span>
        </label>

        <label class="field">
            <span class="field-label">Tipe kontribusi</span>
            <select name="type" required>
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field-label">Nilai baru (untuk koreksi / klaim)</span>
            <textarea name="new_value" rows="3" placeholder="mis. Kacang tanah, Susu skim, ..."></textarea>
        </label>

        <label class="field">
            <span class="field-label">Catatan / penjelasan</span>
            <textarea name="note" rows="3" maxlength="500"></textarea>
        </label>

        <label class="field">
            <span class="field-label">Foto kemasan (opsional, maks 3MB per file)</span>
            <input type="file" name="attachment[]" accept="image/png,image/jpeg,image/webp" multiple>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Kirim kontribusi</button>
        </div>
    </form>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>