<?php
declare(strict_types=1);

namespace App\Repository;

class UserRepository
{
    public function find(int $id): ?array
    {
        $stmt = db_mysql()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = db_mysql()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function countAll(): int
    {
        return (int)db_mysql()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * @return array<int,array{id:int,name:string,email:string,role:string,created_at:string}>
     */
    public function listAll(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = db_mysql()->prepare(
            'SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setRole(int $id, string $role): bool
    {
        if (!in_array($role, ['user', 'admin'], true)) return false;
        $stmt = db_mysql()->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }
}