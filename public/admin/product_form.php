<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Repository\AllergenRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\ProductService;

Auth::requireAdmin();

$svc       = new ProductService(new ProductRepository(), new CategoryRepository(), new \App\Repository\GraphRepository());
$cats      = (new CategoryRepository())->all();
$allergens = (new AllergenRepository())->all();

$id = (string)($_GET['id'] ?? '');
$product = null;
if ($id) {
    $product = (new ProductRepository())->find($id);
    if (!$product) {
        Flash::error('Produk tidak ditemukan.');
        redirect('admin/products.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::checkOrFail();

    $data = [
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'name'        => $_POST['name'] ?? '',
        'brand'       => $_POST['brand'] ?? '',
        'barcode'     => $_POST['barcode'] ?? '',
        'price'       => (float)($_POST['price'] ?? 0),
        'stock'       => (int)($_POST['stock'] ?? 0),
        'image_url'   => $_POST['image_url'] ?? '',
        'allergens'   => array_filter((array)($_POST['allergens'] ?? [])),
        'attributes'  => [
            'type'             => $_POST['type'] ?? 'food',
            'halal_certified'  => !empty($_POST['halal_certified']),
            'vegan'            => !empty($_POST['vegan']),
            'ingredients'      => array_filter(array_map('trim', preg_split('/\n|,/', (string)($_POST['ingredients'] ?? '')))),
        ],
        'tags'        => array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($_POST['tags'] ?? '')))),
        'is_active'   => !empty($_POST['is_active']),
        'popularity'  => (int)($_POST['popularity'] ?? 0),
    ];

    if (!$data['name'] || !$data['category_id']) {
        Flash::error('Nama dan kategori wajib diisi.');
    } else {
        try {
            $newId = $svc->save($data, $id ?: null);
            Flash::success($id ? 'Produk diperbarui.' : 'Produk dibuat.');
            redirect('admin/product_form.php?id=' . $newId);
        } catch (\Throwable $e) {
            Flash::error('Gagal menyimpan: ' . $e->getMessage());
        }
    }
}

$pageTitle = ($id ? 'Edit' : 'Tambah') . ' Produk — Admin';
include VIEWS_PATH . '/layouts/header.php';
?>
<main class="container container-wide">
    <header class="page-head">
        <h1><?= $id ? 'Edit Produk' : 'Tambah Produk' ?></h1>
    </header>

    <form method="post" class="card form">
        <?= Csrf::field() ?>

        <div class="grid-2">
            <label class="field">
                <span class="field-label">Nama produk *</span>
                <input type="text" name="name" required value="<?= e($product['name'] ?? '') ?>">
            </label>
            <label class="field">
                <span class="field-label">Merek</span>
                <input type="text" name="brand" value="<?= e($product['brand'] ?? '') ?>">
            </label>
        </div>

        <div class="grid-3">
            <label class="field">
                <span class="field-label">Kategori *</span>
                <select name="category_id" required>
                    <option value="">— pilih —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ((int)($product['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= e(str_repeat('— ', max(0, $c['parent_id'] ? 1 : 0)) . $c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span class="field-label">Harga (Rp)</span>
                <input type="number" name="price" min="0" step="100" value="<?= e($product['price'] ?? 0) ?>">
            </label>
            <label class="field">
                <span class="field-label">Stok</span>
                <input type="number" name="stock" min="0" value="<?= (int)($product['stock'] ?? 0) ?>">
            </label>
        </div>

        <div class="grid-2">
            <label class="field">
                <span class="field-label">Barcode</span>
                <input type="text" name="barcode" value="<?= e($product['barcode'] ?? '') ?>">
            </label>
            <label class="field">
                <span class="field-label">Image URL (relatif ke /public)</span>
                <input type="text" name="image_url" value="<?= e($product['image_url'] ?? '') ?>" placeholder="/assets/img/products/xxx.jpg">
            </label>
        </div>

        <fieldset class="form-group">
            <legend>Tipe & atribut</legend>
            <div class="grid-3">
                <label class="field">
                    <span class="field-label">Tipe</span>
                    <select name="type">
                        <?php $t = $product['attributes']['type'] ?? 'food'; ?>
                        <option value="food" <?= $t === 'food' ? 'selected' : '' ?>>Makanan</option>
                        <option value="non-food" <?= $t === 'non-food' ? 'selected' : '' ?>>Non-makanan</option>
                    </select>
                </label>
                <label class="check-pill">
                    <input type="checkbox" name="halal_certified" value="1" <?= !empty($product['attributes']['halal_certified']) ? 'checked' : '' ?>>
                    <span>🕌 Halal</span>
                </label>
                <label class="check-pill">
                    <input type="checkbox" name="vegan" value="1" <?= !empty($product['attributes']['vegan']) ? 'checked' : '' ?>>
                    <span>🌱 Vegan</span>
                </label>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <legend>Alergen</legend>
            <div class="checkbox-grid">
                <?php $currentAlgs = $product['allergens'] ?? []; ?>
                <?php foreach ($allergens as $a): ?>
                    <label class="check-pill">
                        <input type="checkbox" name="allergens[]" value="<?= e($a['slug']) ?>" <?= in_array($a['slug'], $currentAlgs, true) ? 'checked' : '' ?>>
                        <span><?= e(($a['icon'] ?? '') . ' ' . $a['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="grid-2">
            <label class="field">
                <span class="field-label">Komposisi (satu per baris atau koma)</span>
                <textarea name="ingredients" rows="4"><?= e(implode("\n", $product['attributes']['ingredients'] ?? [])) ?></textarea>
            </label>
            <label class="field">
                <span class="field-label">Tag (dipisah koma)</span>
                <textarea name="tags" rows="4"><?= e(implode(', ', $product['tags'] ?? [])) ?></textarea>
            </label>
        </div>

        <div class="grid-3">
            <label class="field">
                <span class="field-label">Popularitas (0-100)</span>
                <input type="number" name="popularity" min="0" max="100" value="<?= (int)($product['popularity'] ?? 0) ?>">
            </label>
            <label class="check-pill">
                <input type="checkbox" name="is_active" value="1" <?= ($product['is_active'] ?? true) ? 'checked' : '' ?>>
                <span>Aktif di katalog</span>
            </label>
        </div>

        <div class="form-actions">
            <a href="<?= e(BASE_URL) ?>/admin/products.php" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</main>
<?php include VIEWS_PATH . '/layouts/footer.php'; ?>