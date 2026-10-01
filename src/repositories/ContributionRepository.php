<?php
declare(strict_types=1);

namespace App\Repository;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class ContributionRepository
{
    public function collection(): \MongoDB\Collection
    {
        return mongo_db()->selectCollection('contributions');
    }

    /**
     * Submit a new contribution. Returns the inserted ID (string).
     */
    public function submit(int $userId, string $productId, string $type, array $payload, array $attachments = []): string
    {
        $doc = [
            'product_id'   => new ObjectId($productId),
            'user_id'      => $userId,
            'type'         => $type,
            'payload'      => $payload,
            'attachments'  => $attachments,
            'submitted_at' => new UTCDateTime(),
        ];
        $result = $this->collection()->insertOne($doc);
        $id = (string)$result->getInsertedId();

        // Mirror status to MySQL (for fast filtering in admin)
        db_mysql()->prepare('INSERT IGNORE INTO contribution_status (contribution_id) VALUES (?)')
            ->execute([$id]);

        return $id;
    }

    /**
     * @return array<int,array>
     */
    public function listByStatus(string $status, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min(100, $perPage));
        $skip = max(0, ($page - 1) * $perPage);

        $filter = [];
        if ($status !== 'all') {
            $filter['status'] = $status;
        }

        // Status lives in MySQL, body lives in MongoDB. Join in PHP.
        $stmt = db_mysql()->prepare(
            "SELECT contribution_id, status, reviewed_by, reviewed_at
               FROM contribution_status
              WHERE status = :status
              ORDER BY reviewed_at DESC
              LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue('status', $status === 'all' ? 'pending' : $status);
        $stmt->bindValue('lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('off', $skip, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) return [];

        $ids = [];
        foreach ($rows as $r) $ids[] = new ObjectId($r['contribution_id']);
        $cursor = $this->collection()->find(['_id' => ['$in' => $ids]]);

        $byId = [];
        foreach ($cursor as $doc) {
            $arr = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($doc)), true) ?? [];
            if (isset($arr['_id']['$oid'])) $arr['_id'] = $arr['_id']['$oid'];
            $byId[$arr['_id']] = $arr;
        }

        $out = [];
        foreach ($rows as $r) {
            $cid = $r['contribution_id'];
            if (isset($byId[$cid])) {
                $out[] = array_merge($byId[$cid], ['status' => $r['status'], 'reviewed_by' => $r['reviewed_by']]);
            }
        }
        return $out;
    }

    public function setStatus(string $contributionId, string $status, int $reviewerId): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) return false;
        $stmt = db_mysql()->prepare(
            'UPDATE contribution_status SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE contribution_id=?'
        );
        return $stmt->execute([$status, $reviewerId, $contributionId]);
    }

    public function get(string $contributionId): ?array
    {
        try {
            $doc = $this->collection()->findOne(['_id' => new ObjectId($contributionId)]);
        } catch (\Throwable $e) { return null; }
        if (!$doc) return null;
        $arr = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($doc)), true) ?? [];
        if (isset($arr['_id']['$oid'])) $arr['_id'] = $arr['_id']['$oid'];
        return $arr;
    }

    public function countByStatus(string $status): int
    {
        $stmt = db_mysql()->prepare('SELECT COUNT(*) FROM contribution_status WHERE status = ?');
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }
}