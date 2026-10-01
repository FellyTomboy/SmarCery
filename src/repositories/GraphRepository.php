<?php
declare(strict_types=1);

namespace App\Repository;

/**
 * GraphRepository — wraps Neo4j HTTP API.
 *
 * All public methods are guarded by try/catch — the app should still
 * function if Neo4j is unavailable (falls back to Mongo-only results).
 */
class GraphRepository
{
    /**
     * Ensure the User node exists in Neo4j with up-to-date name.
     */
    public function syncUser(int $userId, string $name): void
    {
        $this->run(
            'MERGE (u:User {id:$id}) SET u.name=$name RETURN u',
            ['id' => $userId, 'name' => $name]
        );
    }

    /**
     * Replace all HAS_ALLERGEN relationships for a user.
     *
     * @param string[] $slugs
     */
    public function syncUserAllergens(int $userId, array $slugs): void
    {
        $slugs = array_values($slugs);
        if (empty($slugs)) {
            // Still clean up old edges if list is empty
            $this->run(
                'MATCH (u:User {id:$id})-[r:HAS_ALLERGEN]->(:Allergen) DELETE r',
                ['id' => $userId]
            );
            return;
        }
        $this->run(
            'MATCH (u:User {id:$id})
             OPTIONAL MATCH (u)-[old:HAS_ALLERGEN]->(:Allergen)
             DELETE old
             WITH u
             UNWIND $slugs AS slug
             MATCH (a:Allergen {slug:slug})
             MERGE (u)-[:HAS_ALLERGEN {severity:"avoid"}]->(a)',
            ['id' => $userId, 'slugs' => $slugs]
        );
    }

    /**
     * Replace all FOLLOWS_DIET relationships.
     *
     * @param string[] $diets
     */
    public function syncUserDiets(int $userId, array $diets): void
    {
        $diets = array_values($diets);
        if (empty($diets)) {
            $this->run(
                'MATCH (u:User {id:$id})-[r:FOLLOWS_DIET]->(:DietTag) DELETE r',
                ['id' => $userId]
            );
            return;
        }
        $this->run(
            'MATCH (u:User {id:$id})
             OPTIONAL MATCH (u)-[old:FOLLOWS_DIET]->(:DietTag)
             DELETE old
             WITH u
             UNWIND $diets AS d
             MERGE (dt:DietTag {name:d})
             MERGE (u)-[:FOLLOWS_DIET]->(dt)',
            ['id' => $userId, 'diets' => $diets]
        );
    }

    /**
     * Sync a single product into the graph: node + allergen + diet edges.
     *
     * @param string   $productId
     * @param string[] $allergens
     * @param string[] $diets
     */
    public function syncProduct(string $productId, string $name, array $allergens, array $diets, int $popularity = 0): void
    {
        $allergens = array_values($allergens);
        $diets     = array_values($diets);

        // 1. Upsert product node with allergen list & popularity
        $this->run(
            'MERGE (p:Product {id:$id})
             SET p.name=$name, p.popularity=$pop, p.is_active=true, p.allergens=$allergens',
            [
                'id'        => $productId,
                'name'      => $name,
                'pop'       => $popularity,
                'allergens' => $allergens,
            ]
        );

        // 2. Clear old edges
        $this->run(
            'MATCH (p:Product {id:$id})-[r]-(:Allergen) WHERE type(r)="CONTAINS_ALLERGIEN" DELETE r
             WITH p
             MATCH (p)-[r2]-(:DietTag) WHERE type(r2)="COMPATIBLE_WITH" DELETE r2',
            ['id' => $productId]
        );

        // 3. Re-create allergen edges (only if allergen nodes exist)
        if (!empty($allergens)) {
            $this->run(
                'MATCH (p:Product {id:$id})
                 UNWIND $slugs AS slug
                 MATCH (a:Allergen {slug:slug})
                 MERGE (p)-[:CONTAINS_ALLERGIEN]->(a)',
                ['id' => $productId, 'slugs' => $allergens]
            );
        }

        // 4. Re-create diet edges
        if (!empty($diets)) {
            $this->run(
                'MATCH (p:Product {id:$id})
                 UNWIND $diets AS d
                 MERGE (dt:DietTag {name:d})
                 MERGE (p)-[:COMPATIBLE_WITH]->(dt)',
                ['id' => $productId, 'diets' => $diets]
            );
        }
    }

    public function deleteProduct(string $productId): void
    {
        $this->run(
            'MATCH (p:Product {id:$id}) DETACH DELETE p',
            ['id' => $productId]
        );
    }

    /**
     * Get top product recommendations.
     *
     * @param string[] $excludeAllergens
     * @param string[] $diets
     * @return array<int,array{id:string,name:string,popularity:int,substitutes:array}>
     */
    public function recommend(array $excludeAllergens, array $diets, int $limit = 24): array
    {
        $rows = $this->run(
            'MATCH (p:Product)
             WHERE p.is_active = true
               AND NONE(x IN p.allergens WHERE x IN $allergens)
             WITH p
             OPTIONAL MATCH (p)-[:COMPATIBLE_WITH]->(d:DietTag)
             WHERE d.name IN $diets
             WITH p, count(DISTINCT d) AS dietHits
             WHERE size($diets) = 0 OR dietHits > 0
             WITH p
             OPTIONAL MATCH (p)-[s:SUBSTITUTE_OF]-(sub:Product)
             WHERE NONE(x IN sub.allergens WHERE x IN $allergens)
               AND sub.is_active = true
             WITH p, collect(DISTINCT {id: sub.id, name: sub.name, score: s.score})[..3] AS substitutes
             RETURN p.id AS id, p.name AS name, p.popularity AS popularity, substitutes
             ORDER BY p.popularity DESC
             LIMIT $lim',
            [
                'allergens' => array_values($excludeAllergens),
                'diets'     => array_values($diets),
                'lim'       => $limit,
            ]
        );

        return $rows;
    }

    /**
     * Get substitutes for a product, filtered by user allergens.
     *
     * @param string[] $excludeAllergens
     * @return array<int,array{id:string,name:string,score:mixed}>
     */
    public function substitutes(string $productId, array $excludeAllergens, int $limit = 4): array
    {
        return $this->run(
            'MATCH (p:Product {id:$id})-[r:SUBSTITUTE_OF]-(sub:Product)
             WHERE sub.is_active = true
               AND NONE(x IN sub.allergens WHERE x IN $allergens)
             RETURN sub.id AS id, sub.name AS name, r.score AS score
             ORDER BY r.score DESC
             LIMIT $lim',
            [
                'id'        => $productId,
                'allergens' => array_values($excludeAllergens),
                'lim'       => $limit,
            ]
        );
    }

    /**
     * Run a Cypher statement via the HTTP client.
     *
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function run(string $cypher, array $params = []): array
    {
        $client = db_neo4j();
        return $client->run($cypher, $params);
    }
}