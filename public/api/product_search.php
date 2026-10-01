<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Response;
use App\Repository\BookmarkRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProfileRepository;
use App\Service\RecommendationService;

Auth::requireAuth();
Response::noCache();

$productId = trim((string)($_GET['product'] ?? ''));
if ($productId === '') {
    Response::jsonError('product wajib diisi', 400);
}

$svc = new RecommendationService(
    new \App\Repository\GraphRepository(),
    new ProductRepository(),
    new ProfileRepository(),
    new BookmarkRepository(),
    new CategoryRepository(),
);

try {
    $alts = $svc->getAlternatives($productId, Auth::id(), 4);
    $out = array_map(static fn($a) => [
        'id'        => $a['_id'],
        'name'      => $a['name'],
        'brand'     => $a['brand'] ?? '',
        'price'     => $a['price'] ?? 0,
        'image_url' => $a['image_url'] ?? '',
    ], $alts);
    Response::jsonOk(['alternatives' => $out]);
} catch (\Throwable $e) {
    Response::jsonError('Gagal: ' . $e->getMessage(), 500);
}