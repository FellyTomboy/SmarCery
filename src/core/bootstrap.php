<?php
/**
 * Bootstrap — loaded on every request via public/*.php entry points.
 *
 * Sets up:
 *  - autoload (Composer PSR-4 + a tiny manual loader for views/helpers)
 *  - session (with regeneration per session lifetime)
 *  - application constants
 *  - error handling
 *  - DB singletons
 */

declare(strict_types=1);

// 1. Composer autoload
$composer = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composer)) {
    require_once $composer;
}

// 2. App config (BASE_URL, etc.)
require_once __DIR__ . '/../config/app.php';

// 3. DB connection helpers
require_once __DIR__ . '/../config/db_mysql.php';
require_once __DIR__ . '/../config/db_mongo.php';
require_once __DIR__ . '/../config/db_neo4j.php';

// 4. Tiny PSR-0-ish autoloader for App\ namespace.
//    Maps App\Core\Auth → src/core/Auth.php
//        App\Repository\Foo → src/repositories/Foo.php  (note plural folder)
//        App\Service\Foo    → src/services/Foo.php     (note plural folder)
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $first = array_shift($parts);
    $tail  = implode('/', $parts);
    // Map namespace component → directory
    $dirMap = [
        'Core'        => 'core',
        'Repository'  => 'repositories',
        'Repositories'=> 'repositories',
        'Service'     => 'services',
        'Services'    => 'services',
    ];
    $dir = $dirMap[$first] ?? strtolower($first);
    $file = __DIR__ . '/../' . $dir . ($tail ? '/' . $tail : '') . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 5. Helpers
foreach (glob(__DIR__ . '/../helpers/*.php') as $helper) {
    require_once $helper;
}

// 6. Session
if (session_status() === PHP_SESSION_NONE) {
    session_name('SMARCERY_SESSID');
    session_start();
}

// 7. Generate CSRF token if absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}