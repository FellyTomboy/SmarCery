<?php
declare(strict_types=1);

namespace App\Repository;

class CategoryRepository
{
    public function find(int $id): ?array
    {
        $stmt = db_mysql()->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = db_mysql()->prepare('SELECT * FROM categories WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<int,array{id:int,parent_id:?int,name:string,slug:string,icon:?string}>
     */
    public function all(): array
    {
        return db_mysql()->query('SELECT * FROM categories ORDER BY parent_id, sort_order, name')->fetchAll();
    }

    /**
     * @return array<int,array> nested: top-level with 'children' sub-array
     */
    public function tree(): array
    {
        $flat = $this->all();
        $byId = [];
        foreach ($flat as $c) $byId[$c['id']] = $c + ['children' => []];
        $tree = [];
        foreach ($byId as $id => &$c) {
            if ($c['parent_id'] && isset($byId[$c['parent_id']])) {
                $byId[$c['parent_id']]['children'][] =& $c;
            } else {
                $tree[] =& $c;
            }
        }
        return $tree;
    }

    public function create(array $data): int
    {
        $stmt = db_mysql()->prepare(
            'INSERT INTO categories (parent_id, name, slug, icon, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['parent_id'] ?: null,
            $data['name'],
            $data['slug'],
            $data['icon'] ?? null,
            $data['sort_order'] ?? 0,
        ]);
        return (int)db_mysql()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = db_mysql()->prepare(
            'UPDATE categories SET parent_id=?, name=?, slug=?, icon=?, sort_order=? WHERE id=?'
        );
        return $stmt->execute([
            $data['parent_id'] ?: null,
            $data['name'],
            $data['slug'],
            $data['icon'] ?? null,
            $data['sort_order'] ?? 0,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = db_mysql()->prepare('DELETE FROM categories WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
