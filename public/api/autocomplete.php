<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Response;
use App\Repository\ProductRepository;
use App\Repository\ProfileRepository;

Auth::requireAuth();
Response::noCache();

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(10, max(1, (int)($_GET['limit'] ?? 8)));

if ($q === '') {
    Response::jsonOk(['suggestions' => []]);
}

$allergens = (new ProfileRepository())->getAllergenSlugs(Auth::id());
$result = (new ProductRepository())->search($q, 1, $limit, $allergens);

$suggestions = array_map(static function ($p) {
    return [
        'id'    => $p['_id'],
        'name'  => $p['name'],
        'brand' => $p['brand'] ?? '',
        'price' => $p['price'] ?? 0,
    ];
}, $result['items']);

Response::jsonOk(['suggestions' => $suggestions]);