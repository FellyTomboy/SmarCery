<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Flash;

Auth::logout();
Flash::info('Anda telah keluar.');
redirect('login.php');
