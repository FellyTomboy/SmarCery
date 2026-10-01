<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\GraphRepository;
use App\Repository\ProductRepository;
use App\Repository\ProfileRepository;
use App\Repository\BookmarkRepository;
use App\Repository\CategoryRepository;

/**
 * RecommendationService — orchestrates recommendation logic across
 * Neo4j (graph traversal) + MongoDB (product detail hydration).
 */
class RecommendationService
{
    public function __construct(
        private GraphRepository $graph,
        private ProductRepository $products,
        private ProfileRepository $profiles,
        private BookmarkRepository $bookmarks,
        private CategoryRepository $categories,
    ) {}

    /**
     * Get recommendations for a user (homepage feed).
     *
     * @return array<int,array{
     *   product: array,
     *   category: ?array,
     *   bookmarked: bool,
     *   substitutes: array<int, array>
     * }>
     */
    public function getRecommendations(int $userId, int $limit = 12): array
    {
        $allergens = $this->profiles->getAllergenSlugs($userId);
        $diets     = $this->profiles->getDietTags($userId);

        // 1. Kandidat via graph traversal (menghindari produk alergen user)
        try {
            $candidates = $this->graph->recommend($allergens, $diets, $limit * 2);
        } catch (\Throwable $e) {
            error_log('Recommendation graph error: ' . $e->getMessage());
            $candidates = [];
        }

        // 2. Hydrate via MongoDB (jika graph gagal, fallback ke Mongo filter langsung)
        if (empty($candidates)) {
            $search = $this->products->paginate(
                ['is_active' => true, 'allergens' => ['$nin' => $allergens]],
                1, $limit
            );
            $candidates = array_map(static function ($p) {
                return ['id' => $p['_id'], 'name' => $p['name'], 'popularity' => $p['popularity'] ?? 0];
            }, $search['items']);
        }

        $ids = array_column($candidates, 'id');
        $products = $this->products->findMany($ids);

        // 3. Bangun output + substitusi per produk
        $bookmarkedIds = $this->bookmarks->listForUser($userId);
        $bookmarkedSet = array_flip($bookmarkedIds);

        $out = [];
        foreach ($candidates as $c) {
            $p = $products[$c['id']] ?? null;
            if (!$p) continue;

            $subs = [];
            if (!empty($c['substitutes']) && is_array($c['substitutes'])) {
                $subIds = array_filter(array_column($c['substitutes'], 'id'));
                if (!empty($subIds)) {
                    $subs = array_values($this->products->findMany($subIds));
                }
            }

            $out[] = [
                'product'    => $p,
                'category'   => $this->categories->find((int)($p['category_id'] ?? 0)),
                'bookmarked' => isset($bookmarkedSet[$p['_id']]),
                'substitutes'=> $subs,
            ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /**
     * Get safe alternatives for a single product (used on product detail).
     *
     * @return array<int,array>
     */
    public function getAlternatives(string $productId, int $userId, int $limit = 4): array
    {
        $allergens = $this->profiles->getAllergenSlugs($userId);
        try {
            $subIds = $this->graph->substitutes($productId, $allergens, $limit);
        } catch (\Throwable $e) {
            error_log('Substitutes graph error: ' . $e->getMessage());
            $subIds = [];
        }
        $found = $this->products->findMany(array_column($subIds, 'id'));
        $hydrated = [];
        foreach ($subIds as $r) {
            if (isset($found[$r['id']])) {
                $hydrated[] = $found[$r['id']];
            }
        }
        if (count($hydrated) < $limit) {
            // Fallback: cari di MongoDB
            $catId = 0;
            $current = $this->products->find($productId);
            if ($current) $catId = (int)$current['category_id'];
            $filter = ['is_active' => true, 'allergens' => ['$nin' => $allergens]];
            if ($catId) $filter['category_id'] = $catId;
            $extra = $this->products->paginate($filter, 1, $limit);
            foreach ($extra['items'] as $it) {
                if ($it['_id'] === $productId) continue;
                $hydrated[] = $it;
                if (count($hydrated) >= $limit) break;
            }
        }
        return $hydrated;
    }
}
