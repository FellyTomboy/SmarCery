<?php
declare(strict_types=1);

namespace App\Repository;

class AllergenRepository
{
    public function all(): array
    {
        return db_mysql()->query('SELECT * FROM allergens ORDER BY name')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db_mysql()->prepare('SELECT * FROM allergens WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = db_mysql()->prepare('SELECT * FROM allergens WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = db_mysql()->prepare('INSERT INTO allergens (name, slug, icon) VALUES (?, ?, ?)');
        $stmt->execute([$data['name'], $data['slug'], $data['icon'] ?? null]);
        return (int)db_mysql()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = db_mysql()->prepare('UPDATE allergens SET name=?, slug=?, icon=? WHERE id=?');
        return $stmt->execute([$data['name'], $data['slug'], $data['icon'] ?? null, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = db_mysql()->prepare('DELETE FROM allergens WHERE id = ?');
        return $stmt->execute([$id]);
    }
}