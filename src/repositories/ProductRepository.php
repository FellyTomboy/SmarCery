<?php
declare(strict_types=1);

namespace App\Repository;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class ProductRepository
{
    /**
     * Get products collection.
     */
    public function collection(): \MongoDB\Collection
    {
        return mongo_db()->selectCollection('products');
    }

    public function find(string $id): ?array
    {
        try {
            $doc = $this->collection()->findOne(['_id' => new ObjectId($id)]);
        } catch (\Throwable $e) {
            return null;
        }
        return $doc ? $this->bsonToArray($doc) : null;
    }

    /**
     * @param array<int,string> $ids
     * @return array<int,array> indexed by id
     */
    public function findMany(array $ids): array
    {
        if (empty($ids)) return [];
        $objectIds = [];
        foreach ($ids as $id) {
            try { $objectIds[] = new ObjectId((string)$id); }
            catch (\Throwable $e) {}
        }
        if (empty($objectIds)) return [];

        $cursor = $this->collection()->find(['_id' => ['$in' => $objectIds]]);
        $out = [];
        foreach ($cursor as $doc) {
            $arr = $this->bsonToArray($doc);
            $out[$arr['_id']] = $arr;
        }
        return $out;
    }

    /**
     * @return array{items:array<int,array>, total:int}
     */
    public function listByCategory(int $categoryId, int $page = 1, int $perPage = 20, array $excludeAllergens = []): array
    {
        $filter = ['category_id' => $categoryId, 'is_active' => true];
        if (!empty($excludeAllergens)) {
            $filter['allergens'] = ['$nin' => array_values($excludeAllergens)];
        }
        return $this->paginate($filter, $page, $perPage);
    }

    public function search(string $query, int $page = 1, int $perPage = 20, array $excludeAllergens = []): array
    {
        $query = trim($query);
        if ($query === '') return ['items' => [], 'total' => 0];

        $regex = new \MongoDB\BSON\Regex(preg_quote($query, '/'), 'i');
        $filter = [
            'is_active' => true,
            '$or' => [
                ['name'  => $regex],
                ['brand' => $regex],
                ['tags'  => $regex],
            ],
        ];
        if (!empty($excludeAllergens)) {
            $filter['allergens'] = ['$nin' => array_values($excludeAllergens)];
        }
        return $this->paginate($filter, $page, $perPage);
    }

    /**
     * @return array{items:array<int,array>, total:int}
     */
    public function paginate(array $filter, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total   = $this->collection()->countDocuments($filter);

        $cursor = $this->collection()->find($filter, [
            'sort'  => ['popularity' => -1, 'name' => 1],
            'skip'  => ($page - 1) * $perPage,
            'limit' => $perPage,
        ]);

        $items = [];
        foreach ($cursor as $doc) {
            $items[] = $this->bsonToArray($doc);
        }
        return ['items' => $items, 'total' => $total];
    }

    public function create(array $data): string
    {
        $data['created_at'] = new UTCDateTime();
        $data['is_active']  = $data['is_active'] ?? true;
        $data['popularity'] = $data['popularity'] ?? 0;
        $result = $this->collection()->insertOne($data);
        return (string)$result->getInsertedId();
    }

    public function update(string $id, array $data): bool
    {
        try {
            $oid = new ObjectId($id);
        } catch (\Throwable $e) {
            return false;
        }
        $data['updated_at'] = new UTCDateTime();
        $res = $this->collection()->updateOne(['_id' => $oid], ['$set' => $data]);
        return $res->getModifiedCount() > 0 || $res->getMatchedCount() > 0;
    }

    public function delete(string $id): bool
    {
        try { $oid = new ObjectId($id); }
        catch (\Throwable $e) { return false; }
        $res = $this->collection()->deleteOne(['_id' => $oid]);
        return $res->getDeletedCount() > 0;
    }

    public function countAll(): int
    {
        return $this->collection()->countDocuments();
    }

    public function countActive(): int
    {
        return $this->collection()->countDocuments(['is_active' => true]);
    }

    /**
     * Convert a BSON document to a plain PHP array with string _id and ISO date.
     */
    public function bsonToArray($doc): array
    {
        $arr = json_decode(\MongoDB\BSON\toJSON(\MongoDB\BSON\fromPHP($doc)), true) ?? [];
        if (isset($arr['_id']) && is_array($arr['_id']) && isset($arr['_id']['$oid'])) {
            $arr['_id'] = $arr['_id']['$oid'];
        }
        foreach (['created_at', 'updated_at'] as $f) {
            if (isset($arr[$f]) && is_array($arr[$f]) && isset($arr[$f]['$date']['$numberLong'])) {
                $ms = (int)$arr[$f]['$date']['$numberLong'];
                $arr[$f] = date('c', $ms / 1000);
            }
        }
        return $arr;
    }
}
