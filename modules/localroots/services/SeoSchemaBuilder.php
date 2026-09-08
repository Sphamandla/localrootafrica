<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use nystudio107\seomatic\helpers\UrlHelper as SeomaticUrlHelper;
use nystudio107\seomatic\Seomatic;

class SeoSchemaBuilder extends Component
{
    /**
     * @param array<string, mixed> $context
     */
    public function apply(array $context): void
    {
        if (!Craft::$app->plugins->isPluginInstalled('seomatic') || !Seomatic::$plugin) {
            return;
        }

        $this->applyWebSiteSearchAction();
        $this->applyOrganization();

        match ($context['type'] ?? 'generic') {
            'product' => $this->applyProduct($context),
            'entry' => $this->applyEntry($context),
            'faq' => $this->applyFaq($context),
            'press' => $this->applyArticle($context),
            'shop', 'category' => $this->applyCollectionPage($context),
            default => null,
        };
    }

    private function applyWebSiteSearchAction(): void
    {
        $jsonLd = Seomatic::$plugin->jsonLd->get('identity');
        if ($jsonLd === null) {
            return;
        }

        $siteUrl = SeomaticUrlHelper::siteUrl('/');
        Seomatic::$plugin->jsonLd->create([
            'type' => 'WebSite',
            'id' => rtrim($siteUrl, '/') . '#website',
            'url' => $siteUrl,
            'name' => $this->siteName(),
            'publisher' => ['id' => rtrim($siteUrl, '/') . '#organization'],
            'potentialAction' => [
                'type' => 'SearchAction',
                'target' => [
                    'type' => 'EntryPoint',
                    'urlTemplate' => SeomaticUrlHelper::siteUrl('shop') . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    private function applyOrganization(): void
    {
        $siteUrl = SeomaticUrlHelper::siteUrl('/');
        $globals = $this->globals();
        $logo = $globals['logo'] ?? null;

        $sameAs = array_values(array_filter([
            $globals['facebook'] ?? null,
            $globals['instagram'] ?? null,
            $globals['twitter'] ?? null,
            $globals['pinterest'] ?? null,
        ]));

        $config = Craft::$app->getConfig()->getConfigFromFile('seo');
        $orgDefaults = $config['organization'] ?? [];

        Seomatic::$plugin->jsonLd->create([
            'type' => $orgDefaults['type'] ?? 'Organization',
            'id' => rtrim($siteUrl, '/') . '#organization',
            'name' => $this->siteName(),
            'url' => $siteUrl,
            'logo' => $logo,
            'sameAs' => $sameAs ?: null,
            'areaServed' => $orgDefaults['areaServed'] ?? 'ZA',
            'knowsAbout' => $orgDefaults['knowsAbout'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyProduct(array $context): void
    {
        /** @var Product|null $product */
        $product = $context['element'] ?? null;
        if (!$product instanceof Product) {
            return;
        }

        $variant = $product->getDefaultVariant() ?? $product->getVariants()->one();
        $images = $product->getFieldValue('productImages')?->all() ?? [];
        $imageUrls = array_map(fn(Asset $a) => $a->getUrl(), $images);
        $description = $this->plainText($product->productShortDescription ?? $product->title);
        $url = $product->getUrl();
        $currency = Craft::$app->getConfig()->getGeneral()->currency ?? 'ZAR';

        $offer = [
            'type' => 'Offer',
            'url' => $url,
            'priceCurrency' => $currency,
            'price' => $variant ? number_format((float) $variant->salePrice, 2, '.', '') : '0.00',
            'availability' => ($variant && $variant->getIsAvailable()) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        $schema = [
            'type' => 'Product',
            'name' => $product->title,
            'description' => $description,
            'url' => $url,
            'sku' => $variant?->sku ?? $product->defaultSku,
            'image' => $imageUrls ?: null,
            'brand' => [
                'type' => 'Brand',
                'name' => $this->siteName(),
            ],
            'offers' => $offer,
        ];

        $stats = $context['reviewStats'] ?? null;
        if (is_array($stats) && ($stats['count'] ?? 0) > 0) {
            $schema['aggregateRating'] = [
                'type' => 'AggregateRating',
                'ratingValue' => number_format((float) $stats['average'], 1, '.', ''),
                'reviewCount' => (int) $stats['count'],
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        Seomatic::$plugin->jsonLd->create($schema);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyEntry(array $context): void
    {
        /** @var Entry|null $entry */
        $entry = $context['element'] ?? null;
        if (!$entry instanceof Entry) {
            return;
        }

        $slug = $entry->slug;
        if ($slug === 'faq') {
            $this->applyFaq($context);
            return;
        }

        $type = match ($slug) {
            'about' => 'AboutPage',
            'contact' => 'ContactPage',
            default => 'WebPage',
        };

        Seomatic::$plugin->jsonLd->create([
            'type' => $type,
            'name' => $entry->title,
            'description' => $context['description'] ?? $this->entryDescription($entry),
            'url' => $entry->getUrl(),
            'dateModified' => $entry->dateUpdated?->format('c'),
            'isPartOf' => ['id' => rtrim(SeomaticUrlHelper::siteUrl('/'), '/') . '#website'],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyFaq(array $context): void
    {
        $items = $context['faqItems'] ?? [];
        if ($items === []) {
            $entry = $context['element'] ?? null;
            if ($entry instanceof Entry && isset($entry->faqItems)) {
                foreach ($entry->faqItems as $row) {
                    $q = trim(strip_tags($row['question'] ?? ''));
                    $a = trim(strip_tags($row['answer'] ?? ''));
                    if ($q !== '' && $a !== '') {
                        $items[] = ['question' => $q, 'answer' => $a];
                    }
                }
            }
        }

        if ($items === []) {
            return;
        }

        $mainEntity = [];
        foreach ($items as $item) {
            $mainEntity[] = [
                'type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    'type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        Seomatic::$plugin->jsonLd->create([
            'type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyArticle(array $context): void
    {
        /** @var Entry|null $entry */
        $entry = $context['element'] ?? null;
        if (!$entry instanceof Entry) {
            return;
        }

        $image = null;
        if (isset($entry->pressHeroImage) && $entry->pressHeroImage->one()) {
            $image = $entry->pressHeroImage->one()->getUrl();
        }

        Seomatic::$plugin->jsonLd->create([
            'type' => 'NewsArticle',
            'headline' => $entry->title,
            'description' => $context['description'] ?? $this->entryDescription($entry),
            'url' => $entry->getUrl(),
            'datePublished' => $entry->postDate?->format('c'),
            'dateModified' => $entry->dateUpdated?->format('c'),
            'image' => $image,
            'author' => [
                'type' => 'Organization',
                'name' => $this->siteName(),
            ],
            'publisher' => [
                'type' => 'Organization',
                'name' => $this->siteName(),
                'logo' => [
                    'type' => 'ImageObject',
                    'url' => $this->globals()['logo'] ?? null,
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyCollectionPage(array $context): void
    {
        Seomatic::$plugin->jsonLd->create([
            'type' => 'CollectionPage',
            'name' => $context['title'] ?? 'Shop',
            'description' => $context['description'] ?? null,
            'url' => $context['url'] ?? SeomaticUrlHelper::siteUrl('shop'),
        ]);
    }

    private function siteName(): string
    {
        $globals = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        return $globals?->siteName ?? Craft::$app->sites->getCurrentSite()->name ?? 'Local Roots Africa';
    }

    /**
     * @return array{logo: ?string, facebook: ?string, instagram: ?string, twitter: ?string, pinterest: ?string}
     */
    private function globals(): array
    {
        $site = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        $footer = Craft::$app->getGlobals()->getSetByHandle('footerSettings');
        $logoAsset = $site?->siteLogo?->one();

        return [
            'logo' => $logoAsset ? $logoAsset->getUrl() : null,
            'facebook' => $footer?->socialFacebook ?? null,
            'instagram' => $footer?->socialInstagram ?? null,
            'twitter' => $footer?->socialTwitter ?? null,
            'pinterest' => $footer?->socialPinterest ?? null,
        ];
    }

    private function entryDescription(Entry $entry): string
    {
        if (isset($entry->pageBody) && $entry->pageBody) {
            return $this->plainText($entry->pageBody, 300);
        }

        return $entry->title;
    }

    private function plainText(mixed $value, int $limit = 160): string
    {
        $text = trim(strip_tags((string) $value));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return StringHelper::safeTruncate($text, $limit);
    }
}
