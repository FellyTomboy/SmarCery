<?php
/**
 * Reusable: product card.
 * Expects:
 *   $product (array with keys: _id, name, brand, price, image_url, allergens, attributes)
 *   $category (array|null)
 *   $bookmarked (bool)
 *   $showSubs (bool) optional
 *   $subs (array) optional list of substitute products
 */
$product  = $product  ?? [];
$category = $category ?? null;
$bookmarked = !empty($bookmarked);
$showSubs   = !empty($showSubs);
$subs       = $subs ?? [];

$id    = $product['_id'] ?? '';
$name  = $product['name'] ?? '';
$brand = $product['brand'] ?? '';
$price = $product['price'] ?? 0;
$img   = $product['image_url'] ?? '';
$algs  = $product['allergens'] ?? [];
$attrs = $product['attributes'] ?? [];
?>
<article class="product-card" data-product-id="<?= e($id) ?>">
    <a href="<?= e(BASE_URL) ?>/user/product.php?id=<?= e($id) ?>" class="product-media">
        <?php if ($img): ?>
            <img src="<?= e(BASE_URL . $img) ?>" alt="<?= e($name) ?>" loading="lazy"
                 onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'product-media-placeholder',textContent:'<?= e($category['icon'] ?? '🛍️') ?>'}))">
        <?php else: ?>
            <div class="product-media-placeholder"><?= e($category['icon'] ?? '🛍️') ?></div>
        <?php endif; ?>
        <?php if (!empty($attrs['vegan'])): ?>
            <span class="product-badge badge-vegan">Vegan</span>
        <?php endif; ?>
        <?php if (!empty($attrs['halal_certified'])): ?>
            <span class="product-badge badge-halal">Halal</span>
        <?php endif; ?>
    </a>
    <button class="bookmark-btn <?= $bookmarked ? 'is-active' : '' ?>" data-id="<?= e($id) ?>" aria-label="Favorit">
        <?= $bookmarked ? '♥' : '♡' ?>
    </button>
    <div class="product-body">
        <?php if ($category): ?>
            <span class="product-cat"><?= e($category['name']) ?></span>
        <?php endif; ?>
        <a href="<?= e(BASE_URL) ?>/user/product.php?id=<?= e($id) ?>" class="product-name"><?= e($name) ?></a>
        <?php if ($brand): ?>
            <span class="product-brand"><?= e($brand) ?></span>
        <?php endif; ?>
        <?php if (!empty($algs)): ?>
            <div class="product-allergens">
                <?php foreach (array_slice($algs, 0, 3) as $a): ?>
                    <span class="allergen-chip"><?= e($a) ?></span>
                <?php endforeach; ?>
                <?php if (count($algs) > 3): ?>
                    <span class="allergen-chip muted">+<?= count($algs) - 3 ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="product-foot">
            <span class="product-price"><?= rupiah((float)$price) ?></span>
        </div>
        <?php if ($showSubs && !empty($subs)): ?>
            <details class="product-subs">
                <summary>Alternatif aman</summary>
                <ul>
                    <?php foreach ($subs as $sub): ?>
                        <li>
                            <a href="<?= e(BASE_URL) ?>/user/product.php?id=<?= e($sub['_id']) ?>"><?= e($sub['name']) ?></a>
                            <span class="muted"><?= rupiah((float)($sub['price'] ?? 0)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </div>
</article>