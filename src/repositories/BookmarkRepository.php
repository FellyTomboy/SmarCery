<?php
declare(strict_types=1);

namespace App\Repository;

class BookmarkRepository
{
    public function isBookmarked(int $userId, string $productId): bool
    {
        $stmt = db_mysql()->prepare('SELECT 1 FROM bookmarks WHERE user_id = ? AND product_id = ? LIMIT 1');
        $stmt->execute([$userId, $productId]);
        return (bool)$stmt->fetchColumn();
    }

    public function add(int $userId, string $productId): void
    {
        db_mysql()->prepare('INSERT IGNORE INTO bookmarks (user_id, product_id) VALUES (?, ?)')
            ->execute([$userId, $productId]);
    }

    public function remove(int $userId, string $productId): void
    {
        db_mysql()->prepare('DELETE FROM bookmarks WHERE user_id = ? AND product_id = ?')
            ->execute([$userId, $productId]);
    }

    public function toggle(int $userId, string $productId): bool
    {
        if ($this->isBookmarked($userId, $productId)) {
            $this->remove($userId, $productId);
            return false;
        }
        $this->add($userId, $productId);
        return true;
    }

    /**
     * @return string[] list of product IDs
     */
    public function listForUser(int $userId): array
    {
        $stmt = db_mysql()->prepare('SELECT product_id FROM bookmarks WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'product_id');
    }
}
