<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/core/bootstrap.php';

use App\Core\Auth;

$user = Auth::check();
if ($user) {
    if ($user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
} else {
    redirect('login.php');
}
