<?php
declare(strict_types=1);

namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], (string)$sent);
    }

    public static function checkOrFail(): void
    {
        if (!self::verify()) {
            http_response_code(419);
            Flash::set('error', 'Token keamanan tidak valid, silakan coba lagi.');
            redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
        }
    }
}
