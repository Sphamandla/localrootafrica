<?php

namespace modules\localroots\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;

class ProductRecommendationsService extends Component
{
    /**
     * @return Product[]
     */
    public function getRelatedProducts(Product $product, int $limit = 5): array
    {
        $excludeIds = [$product->id];
        $results = [];

        $categoryIds = $product->productCategories->ids();
        if ($categoryIds !== []) {
            $results = $this->_mergeProducts(
                $results,
                Product::find()
                    ->relatedTo(['targetElement' => $categoryIds, 'field' => 'productCategories'])
                    ->id(array_merge(['not'], $excludeIds))
                    ->limit($limit)
                    ->all(),
                $excludeIds,
                $limit,
            );
        }

        if (count($results) < $limit) {
            $tagIds = $product->productTags->ids();
            if ($tagIds !== []) {
                $results = $this->_mergeProducts(
                    $results,
                    Product::find()
                        ->relatedTo(['targetElement' => $tagIds, 'field' => 'productTags'])
                        ->id(array_merge(['not'], $excludeIds))
                        ->limit($limit - count($results))
                        ->all(),
                    $excludeIds,
                    $limit,
                );
            }
        }

        if (count($results) < $limit) {
            $results = $this->_mergeProducts(
                $results,
                Product::find()
                    ->id(array_merge(['not'], $excludeIds))
                    ->orderBy('dateUpdated desc')
                    ->limit($limit - count($results))
                    ->all(),
                $excludeIds,
                $limit,
            );
        }

        return $results;
    }

    /**
     * @return Product[]
     */
    public function getFrequentlyBoughtTogether(Product $product, int $limit = 5): array
    {
        $productId = $product->id;
        $counts = [];

        foreach (Order::find()->isCompleted(true)->orderBy('dateOrdered desc')->limit(250)->all() as $order) {
            $productIdsInOrder = $this->_productIdsInOrder($order);
            if (!in_array($productId, $productIdsInOrder, true)) {
                continue;
            }

            foreach ($productIdsInOrder as $otherId) {
                if ($otherId === $productId) {
                    continue;
                }
                $counts[$otherId] = ($counts[$otherId] ?? 0) + 1;
            }
        }

        arsort($counts);
        $topIds = array_slice(array_keys($counts), 0, $limit);

        if ($topIds !== []) {
            return Product::find()->id($topIds)->fixedOrder()->all();
        }

        $relatedIds = array_map(static fn (Product $p) => $p->id, $this->getRelatedProducts($product, $limit));

        return Product::find()
            ->id(array_merge(['not'], [$productId], $relatedIds))
            ->orderBy('dateUpdated desc')
            ->limit($limit)
            ->all();
    }

    /**
     * @param Product[] $existing
     * @param Product[] $candidates
     * @param int[] $excludeIds
     * @return Product[]
     */
    private function _mergeProducts(array $existing, array $candidates, array &$excludeIds, int $limit): array
    {
        $seen = [];
        foreach ($existing as $product) {
            $seen[$product->id] = true;
        }

        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate->id])) {
                continue;
            }
            $existing[] = $candidate;
            $seen[$candidate->id] = true;
            $excludeIds[] = $candidate->id;
            if (count($existing) >= $limit) {
                break;
            }
        }

        return $existing;
    }

    /**
     * @return int[]
     */
    private function _productIdsInOrder(Order $order): array
    {
        $ids = [];
        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            if ($purchasable instanceof Variant) {
                $ids[] = (int) $purchasable->productId;
            }
        }

        return array_values(array_unique($ids));
    }
}
