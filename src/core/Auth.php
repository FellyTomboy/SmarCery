<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    /**
     * Register a new user (regular role only — admin creation is via CLI tool).
     * @return array{id:int}|array{error:string}
     */
    public static function register(string $name, string $email, string $password): array
    {
        $pdo = db_mysql();

        $name  = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || mb_strlen($name) < 2) {
            return ['error' => 'Nama minimal 2 karakter.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Email tidak valid.'];
        }
        if (strlen($password) < 8) {
            return ['error' => 'Password minimal 8 karakter.'];
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['error' => 'Email sudah terdaftar.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "user")')
                ->execute([$name, $email, $hash]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_profiles (user_id, diet_tags) VALUES (?, JSON_ARRAY())')
                ->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['error' => 'Pendaftaran gagal: ' . $e->getMessage()];
        }

        return ['id' => $id];
    }

    /**
     * Attempt login. On success populates $_SESSION and returns user row.
     */
    public static function login(string $email, string $password): ?array
    {
        $pdo = db_mysql();
        $email = strtolower(trim($email));

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Rehash if algorithm/cost changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([$newHash, $user['id']]);
        }

        self::startSessionFor((int)$user['id'], $user['name'], $user['role']);
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function startSessionFor(int $uid, string $name, string $role): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['uid']        = $uid;
        $_SESSION['name']       = $name;
        $_SESSION['role']       = $role;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['login_at']   = time();
    }

    public static function check(): ?array
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }
        return [
            'uid'  => (int)$_SESSION['uid'],
            'name' => $_SESSION['name'] ?? '',
            'role' => $_SESSION['role'] ?? 'user',
        ];
    }

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    public static function requireAuth(): void
    {
        if (self::id() === null) {
            Flash::set('error', 'Silakan login terlebih dahulu.');
            redirect('login.php');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            Flash::set('error', 'Akses ditolak. Hanya untuk admin.');
            redirect('user/dashboard.php');
        }
    }
}
