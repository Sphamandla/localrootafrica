<?php

namespace modules\localroots\services;

use craft\base\Component;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\Category;
use craft\elements\Tag;
use Craft;

class ShopFilterService extends Component
{
    private const COLOR_PATTERNS = [
        'Black', 'Navy', 'Brown', 'Sand', 'Grey', 'Gray', 'Green', 'Blue', 'White',
        'Red', 'Gold', 'Ice', 'Pistachio', 'Midnight', 'Sesame', 'Indigrey', 'Natural-Navy',
        'Light Blue', 'Dark Blue', 'Light Grey', 'Dark grey', 'Pearl Grey', 'Heritage Blue',
        'Multi', 'Indigrey Wash', 'Contrast Wash', 'Light Blue Denim',
    ];

    private const TYPE_KEYWORDS = [
        'Blazer' => 'Blazers & Sport Coats',
        'Jacket' => 'Jackets & Coats',
        'Dress' => 'Dresses',
        'Jean' => 'Jeans',
        'Jeans' => 'Jeans',
        'Bag' => 'Bags',
        'Shirt' => 'Shirts',
        'Overshirt' => 'Shirts',
        'Gilet' => 'Jackets & Coats',
        'T-shirt' => 'T-Shirts',
        'T-Shirt' => 'T-Shirts',
        'Shorts' => 'Trousers',
        'Skirt' => 'Skirts',
        'Sunglasses' => 'Accessories',
        'Cardigan' => 'Knitwear',
        'Sweater' => 'Knitwear',
        'Bermuda' => 'Trousers',
        'Polo' => 'Polos',
        'Trousers' => 'Trousers',
        'Gown' => 'Gowns',
        'Suit' => 'Suits',
    ];

    public function getParams(): array
    {
        $request = Craft::$app->getRequest();
        $page = max(1, (int)($request->getParam('page') ?? 1));

        return [
            'categories' => $this->normalizeArray($request->getQueryParam('categories')),
            'sizes' => $this->normalizeArray($request->getQueryParam('sizes')),
            'colors' => $this->normalizeArray($request->getQueryParam('colors')),
            'brands' => $this->normalizeArray($request->getQueryParam('brands')),
            'types' => $this->normalizeArray($request->getQueryParam('types')),
            'minPrice' => (float)($request->getQueryParam('minPrice') ?? 0),
            'maxPrice' => (float)($request->getQueryParam('maxPrice') ?? 0),
            'inStock' => (bool)$request->getQueryParam('inStock'),
            'sort' => (string)($request->getQueryParam('sort') ?? 'date-desc'),
            'page' => $page,
            'limit' => max(1, min(48, (int)($request->getQueryParam('limit') ?? 12))),
        ];
    }

    public function getProducts(array $params): array
    {
        $all = Product::find()->type('default')->status(null)->all();
        $filtered = [];

        foreach ($all as $product) {
            if (!$this->matchesFilters($product, $params)) {
                continue;
            }
            $filtered[] = $product;
        }

        $filtered = $this->sortProducts($filtered, $params['sort']);
        $total = count($filtered);
        $offset = ($params['page'] - 1) * $params['limit'];
        $pageProducts = array_slice($filtered, $offset, $params['limit']);
        $totalPages = $total > 0 ? (int)ceil($total / $params['limit']) : 1;

        return [
            'products' => $pageProducts,
            'total' => $total,
            'page' => $params['page'],
            'limit' => $params['limit'],
            'totalPages' => $totalPages,
        ];
    }

    public function getFacets(): array
    {
        $products = Product::find()->type('default')->status(null)->all();
        $categories = [];
        $sizes = [];
        $colors = [];
        $brands = [];
        $types = [];
        $minPrice = null;
        $maxPrice = null;

        foreach ($products as $product) {
            foreach ($product->productCategories->all() as $category) {
                $categories[$category->slug] = [
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'count' => ($categories[$category->slug]['count'] ?? 0) + 1,
                ];
            }

            foreach ($product->productTags->all() as $tag) {
                $brands[$tag->title] = [
                    'title' => $tag->title,
                    'count' => ($brands[$tag->title]['count'] ?? 0) + 1,
                ];
            }

            foreach ($product->getVariants()->all() as $variant) {
                $size = trim($variant->title);
                if ($size !== '') {
                    $sizes[$size] = [
                        'title' => $size,
                        'count' => ($sizes[$size]['count'] ?? 0) + 1,
                    ];
                }

                $price = (float)$variant->salePrice;
                if ($price > 0) {
                    $minPrice = $minPrice === null ? $price : min($minPrice, $price);
                    $maxPrice = $maxPrice === null ? $price : max($maxPrice, $price);
                }
            }

            $color = $this->extractColor($product->title);
            if ($color) {
                $colors[$color] = [
                    'title' => $color,
                    'count' => ($colors[$color]['count'] ?? 0) + 1,
                ];
            }

            $type = $this->extractProductType($product->title);
            if ($type) {
                $types[$type] = [
                    'title' => $type,
                    'count' => ($types[$type]['count'] ?? 0) + 1,
                ];
            }
        }

        if ($categories === []) {
            foreach (Category::find()->group('productCategories')->all() as $category) {
                $count = Product::find()->relatedTo($category)->count();
                if ($count > 0) {
                    $categories[$category->slug] = [
                        'title' => $category->title,
                        'slug' => $category->slug,
                        'count' => $count,
                    ];
                }
            }
        }

        $this->sortFacet($categories, 'title');
        $this->sortFacet($sizes, 'title');
        $this->sortFacet($colors, 'title');
        $this->sortFacet($brands, 'title');
        $this->sortFacet($types, 'title');

        return [
            'categories' => array_values($categories),
            'sizes' => array_values($sizes),
            'colors' => array_values($colors),
            'brands' => array_values($brands),
            'types' => array_values($types),
            'minPrice' => $minPrice ?? 0,
            'maxPrice' => $maxPrice ?? 10000,
        ];
    }

    public function extractColor(string $title): ?string
    {
        if (preg_match('/\sIn\s+(.+)$/i', $title, $matches)) {
            return trim($matches[1]);
        }

        foreach (self::COLOR_PATTERNS as $color) {
            if (stripos($title, $color) !== false) {
                return $color;
            }
        }

        return null;
    }

    public function extractProductType(string $title): ?string
    {
        foreach (self::TYPE_KEYWORDS as $keyword => $label) {
            if (stripos($title, $keyword) !== false) {
                return $label;
            }
        }

        return null;
    }

    public function buildFilterUrl(array $params, array $overrides = []): string
    {
        $merged = array_merge($params, $overrides);
        $query = [];

        foreach (['categories', 'sizes', 'colors', 'brands', 'types'] as $key) {
            foreach ($merged[$key] ?? [] as $value) {
                $query[] = $key . '[]=' . rawurlencode($value);
            }
        }

        if (($merged['minPrice'] ?? 0) > 0) {
            $query[] = 'minPrice=' . rawurlencode((string)$merged['minPrice']);
        }
        if (($merged['maxPrice'] ?? 0) > 0) {
            $query[] = 'maxPrice=' . rawurlencode((string)$merged['maxPrice']);
        }
        if (!empty($merged['inStock'])) {
            $query[] = 'inStock=1';
        }
        if (($merged['sort'] ?? 'date-desc') !== 'date-desc') {
            $query[] = 'sort=' . rawurlencode((string)$merged['sort']);
        }
        if (($merged['page'] ?? 1) > 1) {
            $query[] = 'page=' . (int)$merged['page'];
        }

        $base = Craft::$app->getSites()->getCurrentSite()->getBaseUrl() . 'shop';
        return $query ? $base . '?' . implode('&', $query) : $base;
    }

    private function matchesFilters(Product $product, array $params): bool
    {
        if ($params['categories']) {
            $slugs = array_map(fn(Category $c) => $c->slug, $product->productCategories->all());
            if (!array_intersect($params['categories'], $slugs)) {
                return false;
            }
        }

        if ($params['brands']) {
            $tags = array_map(fn(Tag $t) => $t->title, $product->productTags->all());
            if (!array_intersect($params['brands'], $tags)) {
                return false;
            }
        }

        if ($params['colors']) {
            $color = $this->extractColor($product->title);
            if (!$color || !in_array($color, $params['colors'], true)) {
                return false;
            }
        }

        if ($params['types']) {
            $type = $this->extractProductType($product->title);
            if (!$type || !in_array($type, $params['types'], true)) {
                return false;
            }
        }

        if ($params['sizes']) {
            $productSizes = array_map(fn(Variant $v) => $v->title, $product->getVariants()->all());
            if (!array_intersect($params['sizes'], $productSizes)) {
                return false;
            }
        }

        $variant = $product->getDefaultVariant();
        $price = $variant ? (float)$variant->salePrice : 0;

        if ($params['minPrice'] > 0 && $price < $params['minPrice']) {
            return false;
        }
        if ($params['maxPrice'] > 0 && $price > $params['maxPrice']) {
            return false;
        }

        if ($params['inStock'] && $variant && !$variant->getIsAvailable()) {
            return false;
        }

        return true;
    }

    private function sortProducts(array $products, string $sort): array
    {
        usort($products, function (Product $a, Product $b) use ($sort) {
            $va = $a->getDefaultVariant();
            $vb = $b->getDefaultVariant();
            $priceA = $va ? (float)$va->salePrice : 0;
            $priceB = $vb ? (float)$vb->salePrice : 0;

            return match ($sort) {
                'price-asc' => $priceA <=> $priceB,
                'price-desc' => $priceB <=> $priceA,
                'title-asc' => strcasecmp($a->title, $b->title),
                'title-desc' => strcasecmp($b->title, $a->title),
                default => $b->dateCreated <=> $a->dateCreated,
            };
        });

        return $products;
    }

    private function normalizeArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter((array)$value, fn($v) => $v !== '' && $v !== null));
    }

    private function sortFacet(array &$facets, string $key): void
    {
        usort($facets, fn($a, $b) => strcasecmp($a[$key], $b[$key]));
    }
}
