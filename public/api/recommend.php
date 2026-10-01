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

$svc = new RecommendationService(
    new \App\Repository\GraphRepository(),
    new ProductRepository(),
    new ProfileRepository(),
    new BookmarkRepository(),
    new CategoryRepository(),
);

try {
    $limit = min(24, max(1, (int)($_GET['limit'] ?? 12)));
    $recs  = $svc->getRecommendations(Auth::id(), $limit);

    $out = array_map(function ($r) {
        return [
            'id'         => $r['product']['_id'],
            'name'       => $r['product']['name'],
            'brand'      => $r['product']['brand'] ?? '',
            'price'      => $r['product']['price'] ?? 0,
            'image_url'  => $r['product']['image_url'] ?? '',
            'allergens'  => $r['product']['allergens'] ?? [],
            'category'   => $r['category']['name'] ?? null,
            'bookmarked' => $r['bookmarked'],
            'substitutes'=> array_map(static fn($s) => [
                'id' => $s['_id'], 'name' => $s['name'], 'price' => $s['price'] ?? 0
            ], $r['substitutes'] ?? []),
        ];
    }, $recs);

    Response::jsonOk(['recommendations' => $out]);
} catch (\Throwable $e) {
    Response::jsonError('Gagal memuat rekomendasi: ' . $e->getMessage(), 500);
}