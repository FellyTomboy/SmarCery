<?php
declare(strict_types=1);

namespace App\Core;

/**
 * One-shot flash messages stored in session.
 */
class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void { self::set('success', $message); }
    public static function error(string $message): void   { self::set('error', $message);   }
    public static function info(string $message): void    { self::set('info', $message);    }

    /**
     * Pull all flash messages and clear them.
     * @return array<int,array{type:string,message:string}>
     */
    public static function pullAll(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $items;
    }

    public static function has(): bool
    {
        return !empty($_SESSION['_flash']);
    }
}