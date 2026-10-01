<?php
/**
 * Application configuration constants.
 */

// Base URL (path) — adjust if hosting under subdirectory
define('BASE_URL', '/SmarCery/public');
define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', APP_ROOT . '/public');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (development)
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session cookie security
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Upload directories
define('UPLOAD_DIR', PUBLIC_ROOT . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('PACKAGING_DIR', UPLOAD_DIR . '/packaging');
define('PACKAGING_URL', UPLOAD_URL . '/packaging');

// Views directory (for require_once in pages)
define('VIEWS_PATH', APP_ROOT . '/views');