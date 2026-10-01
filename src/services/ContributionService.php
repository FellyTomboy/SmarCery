<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\ContributionRepository;
use App\Repository\ProductRepository;

class ContributionService
{
    public const TYPES = [
        'ingredient_correction' => 'Koreksi komposisi',
        'allergen_claim'         => 'Klaim alergen tambahan',
        'packaging_photo'        => 'Foto kemasan terbaru',
    ];

    public function __construct(
        private ContributionRepository $contributions,
        private ProductRepository $products,
    ) {}

    public function submit(int $userId, string $productId, string $type, array $payload, array $attachmentPaths = []): string
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Tipe kontribusi tidak valid.');
        }
        return $this->contributions->submit($userId, $productId, $type, $payload, $attachmentPaths);
    }

    public function approve(string $contributionId, int $reviewerId): bool
    {
        if (!$this->contributions->setStatus($contributionId, 'approved', $reviewerId)) {
            return false;
        }

        // If the contribution corrects allergen list or composition, apply to product.
        $doc = $this->contributions->get($contributionId);
        if (!$doc) return true;

        $productId = (string)($doc['product_id']['$oid'] ?? $doc['product_id'] ?? '');
        if (!$productId) return true;

        $patch = [];
        if (($doc['type'] ?? '') === 'allergen_claim') {
            $extra = $doc['payload']['new_value'] ?? null;
            if (is_string($extra) && $extra !== '') {
                $slugs = preg_split('/[,\s]+/', strtolower($extra));
                $slugs = array_filter(array_map('trim', $slugs));
                $product = $this->products->find($productId);
                $existing = $product['allergens'] ?? [];
                $merged = array_values(array_unique(array_merge($existing, $slugs)));
                $patch['allergens'] = $merged;
            }
        } elseif (($doc['type'] ?? '') === 'ingredient_correction') {
            $newIngs = $doc['payload']['new_value'] ?? null;
            if (is_string($newIngs) && $newIngs !== '') {
                $list = preg_split('/[,\n]+/', $newIngs);
                $patch['attributes'] = array_merge(
                    $this->products->find($productId)['attributes'] ?? [],
                    ['ingredients' => array_values(array_filter(array_map('trim', $list)))]
                );
            }
        }
        if (!empty($patch)) {
            $this->products->update($productId, $patch);
        }
        return true;
    }

    public function reject(string $contributionId, int $reviewerId): bool
    {
        return $this->contributions->setStatus($contributionId, 'rejected', $reviewerId);
    }
}