<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Repository\BookmarkRepository;

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('Method not allowed', 405);
}
Csrf::checkOrFail();

$productId = (string)($_POST['product_id'] ?? '');
if (!$productId) {
    Response::jsonError('product_id wajib diisi', 400);
}

$bm = new BookmarkRepository();
$state = $bm->toggle(Auth::id(), $productId);

Response::jsonOk([
    'bookmarked' => $state,
    'product_id' => $productId,
]);