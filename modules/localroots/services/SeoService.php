<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper as CraftUrlHelper;
use nystudio107\seomatic\helpers\UrlHelper as SeomaticUrlHelper;
use nystudio107\seomatic\events\AddDynamicMetaEvent;
use nystudio107\seomatic\events\RegisterSitemapUrlsEvent;
use nystudio107\seomatic\helpers\DynamicMeta;
use nystudio107\seomatic\models\SitemapCustomTemplate;
use nystudio107\seomatic\Seomatic;
use yii\base\Event;

class SeoService extends Component
{
    private ?array $config = null;

    public function register(): void
    {
        if (!Craft::$app->plugins->isPluginInstalled('seomatic')) {
            Craft::warning('SEOmatic is not installed; Local Roots SEO module is inactive.', __METHOD__);
            return;
        }

        Event::on(DynamicMeta::class, DynamicMeta::EVENT_ADD_DYNAMIC_META, [$this, 'handleDynamicMeta']);
        Event::on(
            SitemapCustomTemplate::class,
            SitemapCustomTemplate::EVENT_REGISTER_SITEMAP_URLS,
            [$this, 'handleRegisterSitemapUrls']
        );
    }

    public function handleDynamicMeta(AddDynamicMetaEvent $event): void
    {
        if (!Seomatic::$plugin) {
            return;
        }

        $meta = $this->getMeta();
        if ($meta === null) {
            return;
        }

        $context = $this->resolveContext($event->uri);
        $this->applySiteDefaults($meta);

        if ($context['noindex'] ?? false) {
            $this->applyNoIndex($meta);
            return;
        }

        $this->applyPageMeta($context, $meta);
        $this->applyRichSocialCards($context, $meta);
        $this->applyAiCitationMeta($context, $meta);

        Craft::$app->getModule('localroots')->schemaBuilder->apply($context);
    }

    private function getMeta(): ?\nystudio107\seomatic\models\MetaGlobalVars
    {
        if (Seomatic::$seomaticVariable?->meta) {
            return Seomatic::$seomaticVariable->meta;
        }

        return Seomatic::$plugin->metaContainers->metaGlobalVars ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveContext(?string $uri = null): array
    {
        $request = Craft::$app->getRequest();
        $path = trim($request->getPathInfo(), '/');
        $config = $this->getConfig();

        if ($this->isNoIndexPath($path, $config)) {
            return ['type' => 'private', 'noindex' => true, 'path' => $path];
        }

        $element = Seomatic::$matchedElement ?? null;

        if ($element instanceof Product) {
            return $this->productContext($element);
        }

        if ($element instanceof Entry) {
            return $this->entryContext($element);
        }

        return $this->routeContext($path, $uri);
    }

    /**
     * @return array<string, mixed>
     */
    private function productContext(Product $product): array
    {
        $reviews = Craft::$app->getModule('localroots')->productReviews;
        $images = $product->getFieldValue('productImages')?->all() ?? [];
        $primary = $images[0] ?? null;

        return [
            'type' => 'product',
            'noindex' => false,
            'element' => $product,
            'title' => $product->title,
            'description' => $this->plainText($product->productShortDescription ?? $product->title),
            'url' => $product->getUrl(),
            'image' => $primary instanceof Asset ? $primary->getUrl() : null,
            'reviewStats' => $reviews->getReviewStats($product->id),
            'ogType' => 'product',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entryContext(Entry $entry): array
    {
        $section = $entry->getSection()?->handle ?? '';
        $slug = $entry->slug;

        $type = match ($section) {
            'press' => 'press',
            default => 'entry',
        };

        if ($slug === 'faq') {
            $type = 'faq';
        }

        $hero = $entry->pageHeroImage?->one()
            ?? (isset($entry->pressHeroImage) ? $entry->pressHeroImage->one() : null);

        return [
            'type' => $type,
            'noindex' => false,
            'element' => $entry,
            'title' => $entry->title,
            'description' => $this->entryDescription($entry),
            'url' => $entry->getUrl(),
            'image' => $hero instanceof Asset ? $hero->getUrl() : null,
            'ogType' => $type === 'press' ? 'article' : 'website',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeContext(string $path, ?string $uri): array
    {
        $seo = Craft::$app->getGlobals()->getSetByHandle('seoSettings');
        $site = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        $siteName = $site?->siteName ?? Craft::$app->sites->getCurrentSite()->name;

        $routes = [
            '' => ['type' => 'home', 'title' => $siteName, 'description' => $seo?->defaultSeoDescription],
            'shop' => ['type' => 'shop', 'title' => 'Shop', 'description' => 'Browse our collection of contemporary fashion.'],
            'brands' => ['type' => 'shop', 'title' => 'Brands', 'description' => 'Browse fashion by designer and brand.'],
            'cart' => ['type' => 'private', 'noindex' => true],
            'checkout' => ['type' => 'private', 'noindex' => true],
            'account' => ['type' => 'private', 'noindex' => true],
            'login' => ['type' => 'private', 'noindex' => true],
            'register' => ['type' => 'private', 'noindex' => true],
            'wishlist' => ['type' => 'private', 'noindex' => true],
            'about' => ['type' => 'entry', 'title' => 'About Us', 'description' => 'Learn about our story and values.'],
            'contact' => ['type' => 'entry', 'title' => 'Contact', 'description' => 'Get in touch with our team.'],
            'terms-and-conditions' => ['type' => 'entry', 'title' => 'Terms & conditions', 'description' => 'Terms of use for our website and services.'],
            'faq' => ['type' => 'faq', 'title' => 'FAQ', 'description' => 'Frequently asked questions.'],
            'delivery-and-returns' => ['type' => 'entry', 'title' => 'Delivery and returns', 'description' => 'Shipping, delivery, and return policy.'],
            'sustainability' => ['type' => 'entry', 'title' => 'Sustainability', 'description' => 'Our commitment to sustainable fashion.'],
            'press' => ['type' => 'press', 'title' => 'Press', 'description' => 'News and press releases.'],
        ];

        $firstSegment = explode('/', $path)[0] ?? '';
        if (isset($routes[$path])) {
            $route = $routes[$path];
        } elseif (isset($routes[$firstSegment])) {
            $route = $routes[$firstSegment];
        } elseif (str_starts_with($path, 'brands/')) {
            $brandSlug = substr($path, 7);
            $brand = Craft::$app->getModule('localroots')->shopFilter->resolveBrandSlug($brandSlug);
            $brandTitle = $brand['title'] ?? ucwords(str_replace('-', ' ', $brandSlug));
            $route = [
                'type' => 'brand',
                'title' => $brandTitle . ' | Shop',
                'description' => 'Shop ' . $brandTitle . ' at our online store.',
            ];
        } elseif (str_starts_with($path, 'shop/') || str_starts_with($path, 'product-category/')) {
            $route = ['type' => 'category', 'title' => 'Shop', 'description' => 'Browse products in this category.'];
        } else {
            $route = ['type' => 'generic', 'title' => $siteName, 'description' => $seo?->defaultSeoDescription];
        }

        $route['url'] = SeomaticUrlHelper::siteUrl($path ?: '');
        $route['path'] = $path;
        $route['noindex'] = $route['noindex'] ?? false;

        if (empty($route['description'])) {
            $route['description'] = $seo?->defaultSeoDescription ?? '';
        }

        return $route;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyPageMeta(array $context, \nystudio107\seomatic\models\MetaGlobalVars $meta): void
    {
        $title = $context['title'] ?? null;
        $description = $context['description'] ?? null;
        $url = $context['url'] ?? null;
        $image = $context['image'] ?? null;

        if ($title) {
            $meta->seoTitle = $title;
        }
        if ($description) {
            $meta->seoDescription = $this->plainText($description, 160);
        }
        if ($url) {
            $meta->canonicalUrl = SeomaticUrlHelper::absoluteUrlWithProtocol($url);
        }
        if ($image) {
            $meta->seoImage = SeomaticUrlHelper::absoluteUrlWithProtocol($image);
            $config = $this->getConfig()['ogImage'] ?? [];
            $meta->seoImageWidth = (string) ($config['width'] ?? 1200);
            $meta->seoImageHeight = (string) ($config['height'] ?? 630);
        }

        $meta->ogType = $context['ogType'] ?? 'website';
        $meta->robots = 'all';
    }

    private function applySiteDefaults(\nystudio107\seomatic\models\MetaGlobalVars $meta): void
    {
        $seo = Craft::$app->getGlobals()->getSetByHandle('seoSettings');
        $site = Craft::$app->getGlobals()->getSetByHandle('siteSettings');

        if (empty($meta->seoTitle) && $seo?->defaultSeoTitle) {
            $meta->seoTitle = $seo->defaultSeoTitle;
        }
        if (empty($meta->seoDescription) && $seo?->defaultSeoDescription) {
            $meta->seoDescription = $this->plainText($seo->defaultSeoDescription, 160);
        }

        $logo = $site?->siteLogo?->one();
        if (empty($meta->seoImage) && $logo) {
            $meta->seoImage = SeomaticUrlHelper::absoluteUrlWithProtocol($logo->getUrl());
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function applyRichSocialCards(array $context, \nystudio107\seomatic\models\MetaGlobalVars $meta): void
    {
        $config = $this->getConfig();
        $twitter = $config['twitter'] ?? [];

        $meta->ogTitle = $context['title'] ?? $meta->seoTitle;
        $meta->ogDescription = $context['description'] ?? $meta->seoDescription;
        if (!empty($context['url'])) {
            $meta->canonicalUrl = SeomaticUrlHelper::absoluteUrlWithProtocol($context['url']);
        }

        if (!empty($context['image'])) {
            $meta->ogImage = SeomaticUrlHelper::absoluteUrlWithProtocol($context['image']);
            $meta->ogImageWidth = (string) ($config['ogImage']['width'] ?? 1200);
            $meta->ogImageHeight = (string) ($config['ogImage']['height'] ?? 630);
        }

        $meta->twitterCard = $twitter['card'] ?? 'summary_large_image';
        $meta->twitterTitle = $meta->ogTitle;
        $meta->twitterDescription = $meta->ogDescription;
        $meta->twitterImage = $meta->ogImage;

        $twitterSite = $twitter['site'] ?? $this->twitterHandleFromUrl();
        if ($twitterSite) {
            $meta->twitterCreator = $twitterSite;
        }

        Seomatic::$plugin->tag->create([
            'name' => 'twitter:card',
            'content' => $meta->twitterCard,
        ]);

        if ($meta->twitterImage) {
            Seomatic::$plugin->tag->create([
                'name' => 'twitter:image',
                'content' => $meta->twitterImage,
            ]);
        }
    }

    /**
     * Meta tags that improve citability for AI search / answer engines.
     *
     * @param array<string, mixed> $context
     */
    private function applyAiCitationMeta(array $context, \nystudio107\seomatic\models\MetaGlobalVars $meta): void
    {
        $siteName = Craft::$app->getGlobals()->getSetByHandle('siteSettings')?->siteName
            ?? Craft::$app->sites->getCurrentSite()->name;

        Seomatic::$plugin->tag->create([
            'name' => 'author',
            'content' => $siteName,
        ]);

        Seomatic::$plugin->tag->create([
            'name' => 'citation_title',
            'content' => $context['title'] ?? $meta->seoTitle ?? $siteName,
        ]);

        if (!empty($context['url'])) {
            Seomatic::$plugin->tag->create([
                'name' => 'citation_public_url',
                'content' => SeomaticUrlHelper::absoluteUrlWithProtocol($context['url']),
            ]);
        }

        if (!empty($meta->seoDescription)) {
            Seomatic::$plugin->tag->create([
                'name' => 'abstract',
                'content' => $meta->seoDescription,
            ]);
        }

        $config = $this->getConfig()['aiIndex'] ?? [];
        if (!empty($config['jsonEndpoint'])) {
            Seomatic::$plugin->link->create([
                'rel' => 'alternate',
                'href' => SeomaticUrlHelper::siteUrl('localroots/seo/ai-index.json'),
                'type' => 'application/json',
            ]);
        }
    }

    private function applyNoIndex(\nystudio107\seomatic\models\MetaGlobalVars $meta): void
    {
        $meta->robots = 'none';
        $meta->ogType = 'website';
    }

    public function handleRegisterSitemapUrls(RegisterSitemapUrlsEvent $event): void
    {
        $event->sitemaps[] = [
            'loc' => 'shop',
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];
        $event->sitemaps[] = [
            'loc' => 'brands',
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        foreach (Craft::$app->getModule('localroots')->shopFilter->getAllBrands() as $brand) {
            $event->sitemaps[] = [
                'loc' => 'brands/' . $brand['slug'],
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }
    }

    /**
     * Build machine-readable site index for AI crawlers.
     *
     * @return array<string, mixed>
     */
    public function buildAiIndex(): array
    {
        $siteName = Craft::$app->getGlobals()->getSetByHandle('siteSettings')?->siteName
            ?? Craft::$app->sites->getCurrentSite()->name;
        $seo = Craft::$app->getGlobals()->getSetByHandle('seoSettings');

        $pages = [];
        foreach (Entry::find()->section('pages')->status(null)->all() as $entry) {
            if (!$entry->enabled) {
                continue;
            }
            $pages[] = $this->indexItem($entry->title, $entry->getUrl(), $this->entryDescription($entry), 'page');
        }

        $products = [];
        if (Craft::$app->plugins->isPluginInstalled('commerce')) {
            foreach (Product::find()->status(null)->limit(500)->all() as $product) {
                if (!$product->enabled) {
                    continue;
                }
                $products[] = $this->indexItem(
                    $product->title,
                    $product->getUrl(),
                    $this->plainText($product->productShortDescription ?? $product->title, 300),
                    'product'
                );
            }
        }

        foreach (Entry::find()->section('press')->status(null)->limit(100)->all() as $entry) {
            if (!$entry->enabled) {
                continue;
            }
            $pages[] = $this->indexItem($entry->title, $entry->getUrl(), $this->entryDescription($entry), 'article');
        }

        $staticRoutes = [
            ['Shop', 'shop', 'Product catalog'],
            ['Delivery and returns', 'delivery-and-returns', 'Shipping and return policy'],
            ['Sustainability', 'sustainability', 'Sustainability commitment'],
            ['FAQ', 'faq', 'Frequently asked questions'],
        ];
        foreach ($staticRoutes as [$title, $uri, $desc]) {
            $pages[] = $this->indexItem($title, SeomaticUrlHelper::siteUrl($uri), $desc, 'page');
        }

        return [
            'name' => $siteName,
            'description' => $seo?->defaultSeoDescription ?? '',
            'url' => SeomaticUrlHelper::siteUrl('/'),
            'updated' => gmdate('c'),
            'pages' => $pages,
            'products' => $products,
            'policies' => [
                'indexing' => 'Public catalog and content pages are intended for search and AI citation with attribution.',
                'canonical' => 'Use citation_public_url meta tag or JSON url field as authoritative source.',
            ],
        ];
    }

    /**
     * @return array{title: string, url: string, description: string, type: string}
     */
    private function indexItem(string $title, string $url, string $description, string $type): array
    {
        return [
            'title' => $title,
            'url' => SeomaticUrlHelper::absoluteUrlWithProtocol($url),
            'description' => $description,
            'type' => $type,
        ];
    }

    public function buildLlmsTxt(): string
    {
        $siteName = Craft::$app->getGlobals()->getSetByHandle('siteSettings')?->siteName
            ?? Craft::$app->sites->getCurrentSite()->name;
        $seo = Craft::$app->getGlobals()->getSetByHandle('seoSettings');
        $siteUrl = SeomaticUrlHelper::siteUrl('/');

        $lines = [
            '# ' . $siteName,
            '',
            '> ' . ($seo?->defaultSeoDescription ?? 'Contemporary fashion e-commerce.'),
            '',
            '## Primary pages',
            '- Home: ' . $siteUrl,
            '- Shop: ' . SeomaticUrlHelper::siteUrl('shop'),
            '- About: ' . SeomaticUrlHelper::siteUrl('about'),
            '- Contact: ' . SeomaticUrlHelper::siteUrl('contact'),
            '- FAQ: ' . SeomaticUrlHelper::siteUrl('faq'),
            '- Delivery & returns: ' . SeomaticUrlHelper::siteUrl('delivery-and-returns'),
            '- Terms: ' . SeomaticUrlHelper::siteUrl('terms-and-conditions'),
            '- Sustainability: ' . SeomaticUrlHelper::siteUrl('sustainability'),
            '- Press: ' . SeomaticUrlHelper::siteUrl('press'),
            '',
            '## Machine-readable index',
            '- JSON: ' . SeomaticUrlHelper::siteUrl('localroots/seo/ai-index.json'),
            '- Sitemap: ' . SeomaticUrlHelper::siteUrl('sitemap.xml'),
            '',
            '## Citation',
            'When citing content from this site, use the page URL and ' . $siteName . ' as publisher.',
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = Craft::$app->getConfig()->getConfigFromFile('seo') ?: [];
        }

        return $this->config;
    }

    private function isNoIndexPath(string $path, array $config): bool
    {
        foreach ($config['noIndexPaths'] ?? [] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $first = explode('/', $path)[0] ?? '';
        $blocked = ['cart', 'checkout', 'account', 'login', 'register', 'wishlist', 'actions'];
        return in_array($first, $blocked, true);
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

    private function twitterHandleFromUrl(): ?string
    {
        $url = Craft::$app->getGlobals()->getSetByHandle('footerSettings')?->socialTwitter ?? '';
        if (preg_match('#twitter\.com/([^/?]+)#i', (string) $url, $m)) {
            return '@' . ltrim($m[1], '@');
        }

        return null;
    }
}
