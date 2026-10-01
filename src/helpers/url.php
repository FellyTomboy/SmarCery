<?php
/**
 * Helpers — utility functions used across the app.
 */

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void {
        // Allow both relative (e.g. 'login.php') and BASE_URL-prefixed paths
        if (strpos($path, '/') !== 0) {
            $path = BASE_URL . '/' . ltrim($path, '/');
        }
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        return BASE_URL . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('upload_url')) {
    function upload_url(string $relative): string {
        return UPLOAD_URL . '/' . ltrim($relative, '/');
    }
}

if (!function_exists('render')) {
    /**
     * Render a view template. Views live in /views.
     * Available in view: $data array keys as local variables.
     */
    function render(string $view, array $data = []): void {
        $file = APP_ROOT . '/views/' . ltrim($view, '/') . '.php';
        if (!file_exists($file)) {
            throw new RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}