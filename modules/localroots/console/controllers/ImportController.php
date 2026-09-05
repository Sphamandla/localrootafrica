<?php

namespace modules\localroots\console\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin as Commerce;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use DOMDocument;
use DOMXPath;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class ImportController extends Controller
{
    public $optionPath = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['path']);
    }

    public function optionAliases(): array
    {
        return ['path' => 'path'];
    }

    public function actionFromHtml(): int
    {
        $basePath = $this->path ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
        $basePath = rtrim($basePath, '/');

        if (!is_dir($basePath)) {
            $this->stderr("Template path not found: {$basePath}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Importing from: {$basePath}\n", Console::FG_GREEN);

        $productCount = $this->_importProducts($basePath . '/default-shop/index.html', $basePath);
        $this->stdout("  Imported {$productCount} products\n");

        $pageCount = $this->_importPages($basePath);
        $this->stdout("  Imported {$pageCount} pages\n");

        $promoCount = $this->_importPromotions($basePath . '/default-shop/index.html', $basePath);
        $this->stdout("  Imported {$promoCount} promotions\n");

        $this->stdout("Import complete!\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private function _importProducts(string $htmlFile, string $basePath): int
    {
        if (!file_exists($htmlFile)) {
            return 0;
        }

        $html = file_get_contents($htmlFile);
        $productType = Commerce::getInstance()->getProductTypes()->getProductTypeByHandle('default');
        if (!$productType) {
            $this->stderr("  Product type 'default' not found. Run setup first.\n", Console::FG_YELLOW);
            return 0;
        }

        $productsVolume = Craft::$app->getVolumes()->getVolumeByHandle('products');
        $count = 0;
        $seen = [];

        preg_match_all(
            '/e-loop-item-(\d+).*?data-product_name="([^"]+)".*?data-product_sku="([^"]*)".*?<img[^>]+src="([^"]+)"[^>]*>.*?class="price">(.*?)<\/p>/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            preg_match_all(
                '/post-(\d+) product type-product.*?data-product_name="([^"]+)".*?product_sku="([^"]*)".*?src="(\.\.\/)?(wp-content\/uploads\/[^"]+)".*?class="price">(.*?)<\/p>/s',
                $html,
                $altMatches,
                PREG_SET_ORDER
            );
            $matches = $altMatches;
        }

        foreach ($matches as $match) {
            $postId = $match[1];
            if (isset($seen[$postId])) {
                continue;
            }
            $seen[$postId] = true;

            $title = html_entity_decode(strip_tags($match[2]));
            $sku = $match[3] ?: 'LR-' . $postId;
            $imagePath = str_replace('../', '', $match[4] ?? $match[4]);
            if (str_contains($imagePath, 'wp-content')) {
                $imagePath = preg_replace('#^.*?wp-content/#', 'wp-content/', $imagePath);
            }
            $priceHtml = $match[5] ?? $match[count($match) - 1];

            $price = $this->_extractPrice($priceHtml);
            $slug = StringHelper::slugify($title);

            $existing = Product::find()->slug($slug)->one();
            if ($existing) {
                continue;
            }

            $product = new Product();
            $product->typeId = $productType->id;
            $product->title = $title;
            $product->slug = $slug;
            $product->enabled = true;
            $product->setFieldValue('productShortDescription', $title);

            $asset = $this->_importAsset($basePath . '/' . $imagePath, $productsVolume);
            if ($asset) {
                $product->setFieldValue('productImages', [$asset->id]);
            }

            Craft::$app->getElements()->saveElement($product);

            $variant = new Variant();
            $variant->productId = $product->id;
            $variant->sku = $sku;
            $variant->price = $price;
            $variant->basePrice = $price;
            $variant->hasUnlimitedStock = true;
            $variant->enabled = true;
            Craft::$app->getElements()->saveElement($variant);

            $this->_assignCategory($product, 'Default Shop');
            $count++;
            $this->stdout("    Product: {$title} (R{$price})\n");
        }

        if ($count === 0) {
            $count = $this->_importProductsFallback($html, $basePath, $productType, $productsVolume);
        }

        return $count;
    }

    private function _importProductsFallback(string $html, string $basePath, $productType, $productsVolume): int
    {
        $count = 0;
        $seen = [];

        preg_match_all(
            '/data-product_name="([^"]+)"[^>]*data-product_image="([^"]+)"/',
            $html,
            $nameMatches,
            PREG_SET_ORDER
        );

        foreach ($nameMatches as $i => $match) {
            $title = html_entity_decode($match[1]);
            $key = md5($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $slug = StringHelper::slugify($title);
            if (Product::find()->slug($slug)->one()) {
                continue;
            }

            $price = 145 + ($i * 15);
            $product = new Product();
            $product->typeId = $productType->id;
            $product->title = $title;
            $product->slug = $slug;
            $product->enabled = true;
            $product->setFieldValue('productShortDescription', $title);

            $imageUrl = $match[2];
            $localPath = preg_replace('#https?://[^/]+/#', '', $imageUrl);
            $asset = $this->_importAsset($basePath . '/' . $localPath, $productsVolume);
            if ($asset) {
                $product->setFieldValue('productImages', [$asset->id]);
            }

            Craft::$app->getElements()->saveElement($product);

            $variant = new Variant();
            $variant->productId = $product->id;
            $variant->sku = 'LR-' . ($i + 1000);
            $variant->price = $price;
            $variant->basePrice = $price;
            $variant->hasUnlimitedStock = true;
            $variant->enabled = true;
            Craft::$app->getElements()->saveElement($variant);

            $count++;
            $this->stdout("    Product: {$title}\n");
        }

        return $count;
    }

    private function _importPages(string $basePath): int
    {
        $section = Craft::$app->entries->getSectionByHandle('pages');
        if (!$section) {
            return 0;
        }
        $entryType = $section->getEntryTypes()[0];

        $pages = [
            ['slug' => 'home', 'title' => 'Home', 'file' => 'default-shop/index.html', 'template' => '_pages/home'],
            ['slug' => 'about', 'title' => 'About', 'file' => 'about/index.html'],
            ['slug' => 'contact', 'title' => 'Contact', 'file' => 'contact/index.html'],
            ['slug' => 'delivery-and-returns', 'title' => 'Delivery and Returns', 'file' => 'delivery-and-returns.html'],
            ['slug' => 'faq', 'title' => 'FAQ', 'file' => 'faq/index.html'],
        ];

        $count = 0;
        foreach ($pages as $page) {
            $existing = Entry::find()->section('pages')->slug($page['slug'])->one();
            if ($existing) {
                continue;
            }

            $body = '';
            $filePath = $basePath . '/' . $page['file'];
            if (file_exists($filePath)) {
                $body = $this->_extractMainContent(file_get_contents($filePath));
            }

            $entry = new Entry();
            $entry->sectionId = $section->id;
            $entry->typeId = $entryType->id;
            $entry->title = $page['title'];
            $entry->slug = $page['slug'];
            $entry->enabled = true;
            $entry->setFieldValue('pageBody', $body ?: $page['title'] . ' page content.');
            Craft::$app->getElements()->saveElement($entry);
            $count++;
        }

        return $count;
    }

    private function _importPromotions(string $htmlFile, string $basePath): int
    {
        if (!file_exists($htmlFile)) {
            return 0;
        }

        $section = Craft::$app->entries->getSectionByHandle('promotions');
        if (!$section) {
            return 0;
        }
        $entryType = $section->getEntryTypes()[0];
        $contentVolume = Craft::$app->getVolumes()->getVolumeByHandle('content');

        $promos = [
            ['title' => 'Summer Collection', 'subtitle' => 'New arrivals for the season', 'button' => 'Shop Now', 'link' => '/shop'],
            ['title' => 'Sale', 'subtitle' => 'Up to 40% off selected items', 'button' => 'View Sale', 'link' => '/shop/sale'],
            ['title' => 'Bespoke Tailoring', 'subtitle' => 'Custom fit, premium fabrics', 'button' => 'Learn More', 'link' => '/about'],
        ];

        $count = 0;
        foreach ($promos as $promo) {
            $slug = StringHelper::slugify($promo['title']);
            if (Entry::find()->section('promotions')->slug($slug)->one()) {
                continue;
            }

            $entry = new Entry();
            $entry->sectionId = $section->id;
            $entry->typeId = $entryType->id;
            $entry->title = $promo['title'];
            $entry->slug = $slug;
            $entry->enabled = true;
            $entry->setFieldValue('promoTitle', $promo['title']);
            $entry->setFieldValue('promoSubtitle', $promo['subtitle']);
            $entry->setFieldValue('promoButtonText', $promo['button']);
            $entry->setFieldValue('promoLink', $promo['link']);
            Craft::$app->getElements()->saveElement($entry);
            $count++;
        }

        return $count;
    }

    private function _extractPrice(string $html): float
    {
        if (preg_match('/<ins[^>]*>.*?(\d+(?:\.\d+)?)/s', $html, $m)) {
            return (float)$m[1];
        }
        if (preg_match('/(\d+(?:\.\d+)?)/', strip_tags($html), $m)) {
            return (float)$m[1];
        }
        return 99.00;
    }

    private function _extractMainContent(string $html): string
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//main//p | //div[contains(@class,'elementor-widget-heading')]//h1 | //div[contains(@class,'elementor-widget-heading')]//h2");
        $parts = [];
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text && strlen($text) > 10) {
                $parts[] = $text;
            }
        }
        return implode("\n\n", array_slice($parts, 0, 5));
    }

    private function _importAsset(string $filePath, $volume): ?Asset
    {
        if (!$volume || !file_exists($filePath)) {
            return null;
        }

        $filename = basename($filePath);
        $existing = Asset::find()->filename($filename)->volumeId($volume->id)->one();
        if ($existing) {
            return $existing;
        }

        $tempPath = Craft::$app->getPath()->getTempPath() . '/' . $filename;
        if (!copy($filePath, $tempPath)) {
            return null;
        }

        $folder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $volume->id;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (Craft::$app->getElements()->saveElement($asset)) {
            return $asset;
        }

        return null;
    }

    private function _assignCategory(Product $product, string $categoryName): void
    {
        $group = Craft::$app->getCategories()->getGroupByHandle('productCategories');
        if (!$group) {
            return;
        }

        $category = Category::find()
            ->group('productCategories')
            ->title($categoryName)
            ->one();

        if (!$category) {
            $category = new Category();
            $category->groupId = $group->id;
            $category->title = $categoryName;
            Craft::$app->getElements()->saveElement($category);
        }

        $product->setFieldValue('productCategories', [$category->id]);
        Craft::$app->getElements()->saveElement($product);
    }
}
