<?php
declare(strict_types=1);

namespace App\Repository;

class TransactionRepository
{
    public function createWithItems(int $userId, array $items): int
    {
        $pdo = db_mysql();
        $total = 0.0;
        foreach ($items as $it) {
            $total += (float)$it['price'] * (int)$it['qty'];
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO transactions (user_id, total_amount) VALUES (?, ?)')
                ->execute([$userId, $total]);
            $txId = (int)$pdo->lastInsertId();
            $ins  = $pdo->prepare(
                'INSERT INTO transaction_items (transaction_id, product_id, product_name, qty, price) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($items as $it) {
                $ins->execute([$txId, $it['product_id'], $it['product_name'], (int)$it['qty'], (float)$it['price']]);
            }
            $pdo->commit();
            return $txId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<int,array{id:int,total_amount:string,purchased_at:string,items:array}>
     */
    public function listForUser(int $userId, int $limit = 20): array
    {
        $stmt = db_mysql()->prepare(
            'SELECT id, total_amount, purchased_at FROM transactions WHERE user_id = ? ORDER BY purchased_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        $txs = $stmt->fetchAll();

        if (empty($txs)) return [];

        $ids = array_column($txs, 'id');
        $place = implode(',', array_fill(0, count($ids), '?'));
        $itStmt = db_mysql()->prepare(
            "SELECT * FROM transaction_items WHERE transaction_id IN ({$place}) ORDER BY id"
        );
        $itStmt->execute($ids);
        $items = [];
        foreach ($itStmt->fetchAll() as $row) {
            $items[$row['transaction_id']][] = $row;
        }
        foreach ($txs as &$t) {
            $t['items'] = $items[$t['id']] ?? [];
        }
        return $txs;
    }
}