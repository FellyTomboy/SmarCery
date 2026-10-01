<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonError(string $message, int $status = 400): void
    {
        self::json(['ok' => false, 'error' => $message], $status);
    }

    public static function jsonOk(array $payload = []): void
    {
        self::json(array_merge(['ok' => true], $payload), 200);
    }

    /** Send 400 with field-level errors */
    public static function jsonValidation(array $errors): void
    {
        self::json(['ok' => false, 'errors' => $errors], 422);
    }

    public static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
}
