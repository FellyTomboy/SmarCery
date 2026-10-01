<?php
declare(strict_types=1);

namespace App\Repository;

class ProfileRepository
{
    /**
     * Get profile (raw array). diet_tags comes back as JSON-encoded string.
     */
    public function getProfile(int $userId): ?array
    {
        $stmt = db_mysql()->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        if (isset($row['diet_tags'])) {
            $decoded = json_decode($row['diet_tags'], true);
            $row['diet_tags'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['diet_tags'] = [];
        }
        return $row;
    }

    /**
     * @return string[] e.g. ["vegan","halal"]
     */
    public function getDietTags(int $userId): array
    {
        $p = $this->getProfile($userId);
        return $p['diet_tags'] ?? [];
    }

    /**
     * @return array<int,array{id:int,name:string,slug:string,severity:string}>
     */
    public function getAllergens(int $userId): array
    {
        $stmt = db_mysql()->prepare(
            'SELECT a.id, a.name, a.slug, ua.severity
               FROM user_allergens ua
               JOIN allergens a ON a.id = ua.allergen_id
              WHERE ua.user_id = ?
              ORDER BY a.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * @return string[] slugs only
     */
    public function getAllergenSlugs(int $userId): array
    {
        return array_column($this->getAllergens($userId), 'slug');
    }

    /**
     * Update profile diet tags, allergens, and notes. Mirrors to Neo4j.
     *
     * @param string[] $dietTags
     * @param string[] $allergenSlugs
     */
    public function updateProfile(int $userId, array $dietTags, array $allergenSlugs, ?string $notes, GraphRepository $graph): void
    {
        $pdo = db_mysql();
        $pdo->beginTransaction();
        try {
            // Upsert profile
            $stmt = $pdo->prepare('SELECT user_id FROM user_profiles WHERE user_id = ?');
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                $pdo->prepare('UPDATE user_profiles SET diet_tags = ?, other_notes = ? WHERE user_id = ?')
                    ->execute([json_encode(array_values(array_unique($dietTags))), $notes, $userId]);
            } else {
                $pdo->prepare('INSERT INTO user_profiles (user_id, diet_tags, other_notes) VALUES (?, ?, ?)')
                    ->execute([$userId, json_encode(array_values(array_unique($dietTags))), $notes]);
            }

            // Replace allergens
            $pdo->prepare('DELETE FROM user_allergens WHERE user_id = ?')->execute([$userId]);
            if (!empty($allergenSlugs)) {
                $place = implode(',', array_fill(0, count($allergenSlugs), '?'));
                $sql = "SELECT id FROM allergens WHERE slug IN ({$place})";
                $st  = $pdo->prepare($sql);
                $st->execute($allergenSlugs);
                $ids = array_column($st->fetchAll(), 'id');

                $ins = $pdo->prepare('INSERT INTO user_allergens (user_id, allergen_id, severity) VALUES (?, ?, "avoid")');
                foreach ($ids as $aid) {
                    $ins->execute([$userId, $aid]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Sync graph (best-effort, do not abort if Neo4j is down)
        try {
            $graph->syncUserAllergens($userId, $allergenSlugs);
            $graph->syncUserDiets($userId, $dietTags);
        } catch (\Throwable $e) {
            // log but don't fail user request
            error_log('Graph sync failed: ' . $e->getMessage());
        }
    }
}
