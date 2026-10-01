<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\CategoryRepository;
use App\Repository\ContributionRepository;
use App\Repository\GraphRepository;
use App\Repository\ProductRepository;
use MongoDB\BSON\ObjectId;

class ProductService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private GraphRepository $graph,
    ) {}

    public function getDetail(string $id): ?array
    {
        $p = $this->products->find($id);
        if (!$p) return null;
        $p['category'] = $this->categories->find((int)($p['category_id'] ?? 0));
        return $p;
    }

    /**
     * Create or update a product (admin) and sync to graph.
     *
     * @return string product id
     */
    public function save(array $data, ?string $id = null): string
    {
        $doc = [
            'category_id' => (int)$data['category_id'],
            'name'        => trim($data['name']),
            'brand'       => trim($data['brand'] ?? ''),
            'barcode'     => trim($data['barcode'] ?? ''),
            'price'       => (float)$data['price'],
            'stock'       => (int)($data['stock'] ?? 0),
            'image_url'   => trim($data['image_url'] ?? ''),
            'allergens'   => array_values(array_filter($data['allergens'] ?? [])),
            'attributes'  => $data['attributes'] ?? ['type' => 'food'],
            'tags'        => array_values(array_filter($data['tags'] ?? [])),
            'is_active'   => (bool)($data['is_active'] ?? true),
            'popularity'  => (int)($data['popularity'] ?? 0),
        ];

        if ($id) {
            $this->products->update($id, $doc);
            $newId = $id;
        } else {
            $newId = $this->products->create($doc);
        }

        // Mirror to graph
        try {
            $this->graph->syncProduct(
                $newId,
                $doc['name'],
                $doc['allergens'],
                $doc['attributes']['diets'] ?? [],
                $doc['popularity']
            );
        } catch (\Throwable $e) {
            error_log('Graph sync product failed: ' . $e->getMessage());
        }

        return $newId;
    }

    public function delete(string $id): bool
    {
        $ok = $this->products->delete($id);
        try { $this->graph->deleteProduct($id); } catch (\Throwable $e) {}
        return $ok;
    }
}
