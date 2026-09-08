<?php

namespace modules\localroots\console\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use modules\localroots\services\ShopFilterService;
use modules\localroots\services\TemplateExtractorService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class SeedController extends Controller
{
    public ?string $path = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['path']);
    }

    public function optionAliases(): array
    {
        return ['path' => 'path'];
    }

    public function actionAll(): int
    {
        $basePath = $this->path ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
        $this->stdout("Seeding all content from: {$basePath}\n", Console::FG_GREEN);

        $this->actionExtractTemplates();
        $this->actionPages($basePath);
        $this->actionContact($basePath);
        $this->actionTerms($basePath);
        $this->actionBrands();
        $this->actionFaq($basePath);
        $this->actionPress($basePath);
        $this->actionDeliveryAndReturns($basePath);
        $this->actionOrderStatus($basePath);
        $this->actionMobileMenu($basePath);
        $this->actionSustainability($basePath);

        $this->stdout("Running product import...\n");
        Craft::$app->runAction('localroots/import/from-html', ['path' => $basePath]);

        $this->stdout("Seed complete!\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    public function actionExtractTemplates(): int
    {
        $basePath = $this->path ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
        $service = new TemplateExtractorService(['templateBase' => rtrim($basePath, '/')]);
        $written = $service->extractAll();
        $this->stdout('  Extracted ' . count($written) . " template fragments\n");
        return ExitCode::OK;
    }

    public function actionPages(?string $basePath = null): int
    {
        $basePath = $basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
        $pages = [
            ['slug' => 'about', 'title' => 'About Us', 'file' => 'about/index.html'],
            ['slug' => 'contact', 'title' => 'Contact', 'file' => 'contact/index.html'],
        ];

        $section = Craft::$app->entries->getSectionByHandle('pages');
        if (!$section) {
            $this->stderr("  Pages section not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($pages as $page) {
            $existing = Entry::find()->section('pages')->slug($page['slug'])->one();
            if ($existing) {
                continue;
            }

            $file = rtrim($basePath, '/') . '/' . $page['file'];
            $body = '';
            if (file_exists($file)) {
                $html = file_get_contents($file);
                if (preg_match('/<div[^>]*class="page-content[^"]*"[^>]*>(.*?)<\/article>/is', $html, $m)) {
                    $body = strip_tags($m[1]);
                    $body = StringHelper::truncate($body, 2000);
                }
            }

            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'title' => $page['title'],
                'slug' => $page['slug'],
                'enabled' => true,
            ]);
            $entry->setFieldValues(['pageBody' => $body ?: $page['title']]);
            Craft::$app->elements->saveElement($entry);
            $this->stdout("  Created page: {$page['slug']}\n");
        }

        return ExitCode::OK;
    }

    public function actionContact(?string $basePath = null): int
    {
        $basePath = $basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';

        $section = Craft::$app->entries->getSectionByHandle('pages');
        if (!$section) {
            $this->stderr("  Pages section not found.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $file = rtrim($basePath, '/') . '/contact/index.html';
        $excerpt = 'We’re here to help';
        if (file_exists($file)) {
            $html = file_get_contents($file);
            if (preg_match('/<div class="elementor-widget-container">\s*([^<]+)\s*<\/div>\s*<\/div>\s*<div class="elementor-element elementor-element-b287bac/i', $html, $m)) {
                $excerpt = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $entry = Entry::find()->section('pages')->slug('contact')->one();
        if (!$entry) {
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'title' => 'Contact',
                'slug' => 'contact',
                'enabled' => true,
            ]);
        }

        $entry->title = 'Contact';
        $entry->setFieldValues(['pageBody' => $excerpt]);
        if (!Craft::$app->elements->saveElement($entry)) {
            $this->stderr("  Failed to save Contact entry.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("  Seeded Contact page\n");

        return ExitCode::OK;
    }

    public function actionTerms(?string $basePath = null): int
    {
        $basePath = $basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';

        $section = Craft::$app->entries->getSectionByHandle('pages');
        if (!$section) {
            $this->stderr("  Pages section not found.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $file = rtrim($basePath, '/') . '/index.html?p=3.html';
        $excerpt = 'Terms of Use for Innove Couture';
        if (file_exists($file)) {
            $html = file_get_contents($file);
            if (preg_match('/data-widget_type="theme-post-excerpt\.default"[^>]*>\s*<div class="elementor-widget-container">\s*([^<]+)\s*<\/div>/i', $html, $m)) {
                $excerpt = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $entry = Entry::find()->section('pages')->slug('terms-and-conditions')->one();
        if (!$entry) {
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'title' => 'Terms & conditions',
                'slug' => 'terms-and-conditions',
                'enabled' => true,
            ]);
        }

        $entry->title = 'Terms & conditions';
        $entry->setFieldValues(['pageBody' => $excerpt]);
        if (!Craft::$app->elements->saveElement($entry)) {
            $this->stderr("  Failed to save Terms & conditions entry.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("  Seeded Terms & conditions page\n");

        return ExitCode::OK;
    }

    public function actionBrands(): int
    {
        $group = Craft::$app->tags->getTagGroupByHandle('productTags');
        if (!$group) {
            $this->stderr("  Tag group productTags not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $created = 0;
        foreach (ShopFilterService::LEGACY_BRAND_IDS as $title) {
            $existing = Tag::find()->group('productTags')->title($title)->one();
            if ($existing) {
                continue;
            }

            $tag = new Tag([
                'groupId' => $group->id,
                'title' => $title,
            ]);
            if (Craft::$app->elements->saveElement($tag)) {
                $created++;
            }
        }

        $tags = Tag::find()->group('productTags')->orderBy('title')->all();
        if ($tags === []) {
            $this->stdout("  No brand tags to assign.\n");
            return ExitCode::OK;
        }

        $products = Product::find()->type('default')->status(null)->all();
        $assigned = 0;
        foreach ($products as $index => $product) {
            $tag = $tags[$index % count($tags)];
            $currentIds = array_map(fn(Tag $t) => $t->id, $product->productTags->all());
            if (in_array($tag->id, $currentIds, true)) {
                continue;
            }

            $product->setFieldValue('productTags', [$tag->id]);
            if (Craft::$app->elements->saveElement($product)) {
                $assigned++;
            }
        }

        $this->stdout("  Seeded {$created} brand tags; assigned brands to {$assigned} products.\n");

        return ExitCode::OK;
    }

    public function actionFaq(?string $basePath = null): int
    {
        $basePath = $basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
        $this->_ensureFaqField();

        $section = Craft::$app->entries->getSectionByHandle('pages');
        if (!$section) {
            $this->stderr("  Pages section not found.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $file = rtrim($basePath, '/') . '/faq/index.html';
        if (!file_exists($file)) {
            $this->stderr("  FAQ reference file not found: {$file}\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $html = file_get_contents($file);
        $faqItems = $this->_parseFaqItems($html);

        $entry = Entry::find()->section('pages')->slug('faq')->one();
        if (!$entry) {
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'title' => 'FAQ',
                'slug' => 'faq',
                'enabled' => true,
            ]);
        }

        $entry->setFieldValues(['faqItems' => $faqItems]);
        if (!Craft::$app->elements->saveElement($entry)) {
            $this->stderr("  Failed to save FAQ entry.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('  Seeded FAQ with ' . count($faqItems) . " items\n");
        return ExitCode::OK;
    }

    private function _ensureFaqField(): void
    {
        $fieldsService = Craft::$app->getFields();
        if ($fieldsService->getFieldByHandle('faqItems')) {
            return;
        }

        $field = $fieldsService->createField([
            'type' => Table::class,
            'name' => 'FAQ Items',
            'handle' => 'faqItems',
            'settings' => [
                'columns' => [
                    'col1' => [
                        'heading' => 'Section',
                        'handle' => 'section',
                        'width' => '25%',
                        'type' => 'singleline',
                    ],
                    'col2' => [
                        'heading' => 'Question',
                        'handle' => 'question',
                        'width' => '35%',
                        'type' => 'singleline',
                    ],
                    'col3' => [
                        'heading' => 'Answer',
                        'handle' => 'answer',
                        'width' => '40%',
                        'type' => 'multiline',
                    ],
                ],
            ],
        ]);
        $fieldsService->saveField($field);
        $this->stdout("  Created field: faqItems\n");

        $section = Craft::$app->entries->getSectionByHandle('pages');
        if (!$section) {
            return;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return;
        }

        $layout = $entryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
        $tabs = $layout->getTabs();
        $tab = $tabs[0] ?? new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
        $elements = $tab->getElements();
        $hasField = false;
        foreach ($elements as $element) {
            if ($element instanceof CustomField && $element->fieldUid === $field->uid) {
                $hasField = true;
                break;
            }
        }

        if (!$hasField) {
            $elements[] = Craft::$app->getFields()->createLayoutElement([
                'type' => CustomField::class,
                'fieldUid' => $field->uid,
            ]);
            $tab->setElements($elements);
            $layout->setTabs([$tab]);
            $entryType->setFieldLayout($layout);
            Craft::$app->entries->saveEntryType($entryType);
            $this->stdout("  Added faqItems to pages entry type\n");
        }
    }

    /**
     * @return list<array{section: string, question: string, answer: string}>
     */
    private function _parseFaqItems(string $html): array
    {
        $items = [];
        $sectionTitles = [
            'Most common questions',
            'My order',
            'Delivery',
            'Payment',
            'Campaigns & offers',
        ];

        if (!preg_match('/<aside[^>]*elementor-element-0e166f0[\s\S]*?<\/aside>/i', $html, $asideMatch)) {
            return $items;
        }

        $aside = $asideMatch[0];
        $parts = preg_split(
            '/<h2 class="elementor-heading-title elementor-size-default">(.*?)<\/h2>/i',
            $aside,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $currentSection = '';
        for ($i = 1; $i < count($parts); $i += 2) {
            $heading = html_entity_decode(trim(strip_tags($parts[$i])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $block = $parts[$i + 1] ?? '';

            if (!in_array($heading, $sectionTitles, true)) {
                continue;
            }
            $currentSection = $heading;

            if (!preg_match_all(
                '/<a class="elementor-toggle-title"[^>]*>(.*?)<\/a>\s*<\/div>\s*<div[^>]*class="elementor-tab-content[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/is',
                $block,
                $matches,
                PREG_SET_ORDER
            )) {
                continue;
            }

            foreach ($matches as $match) {
                $question = html_entity_decode(trim(strip_tags($match[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $answer = trim($match[2]);
                $answer = preg_replace('#https://innovecouture\.vamtam\.com/#', '/', $answer);
                $answer = preg_replace('#\.\./index\.html%3Fp=8\.html#', '/account', $answer);
                $answer = preg_replace('#index\.html%3Fp=8\.html#', '/account', $answer);
                $answer = preg_replace('#\.\./track-order/#', '/track-order/', $answer);

                if ($question !== '' && $answer !== '') {
                    $items[] = [
                        'section' => $currentSection,
                        'question' => $question,
                        'answer' => $answer,
                    ];
                }
            }
        }

        return $items;
    }

    public function actionSimplePurchaseField(): int
    {
        $fieldsService = Craft::$app->getFields();
        if (!$fieldsService->getFieldByHandle('simplePurchase')) {
            $field = $fieldsService->createField([
                'type' => Lightswitch::class,
                'name' => 'Simple purchase (no options)',
                'handle' => 'simplePurchase',
            ]);
            $fieldsService->saveField($field);
            $this->stdout("  Created field: simplePurchase\n");

            $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('default');
            if ($productType) {
                $layout = $productType->getFieldLayout() ?? new FieldLayout(['type' => \craft\commerce\elements\Product::class]);
                $tabs = $layout->getTabs();
                $tab = $tabs[0] ?? new FieldLayoutTab(['name' => 'Product', 'layout' => $layout]);
                $elements = $tab->getElements();
                $elements[] = Craft::$app->getFields()->createLayoutElement([
                    'type' => CustomField::class,
                    'fieldUid' => $field->uid,
                ]);
                $tab->setElements($elements);
                $layout->setTabs([$tab]);
                $productType->setFieldLayout($layout);
                \craft\commerce\Plugin::getInstance()->getProductTypes()->saveProductType($productType);
            }
        }

        $simpleSlugs = ['waterproof-windbreaker-jacket'];
        foreach ($simpleSlugs as $slug) {
            $product = \craft\commerce\elements\Product::find()->slug($slug)->one();
            if ($product && !$product->simplePurchase) {
                $product->setFieldValue('simplePurchase', true);
                Craft::$app->elements->saveElement($product);
                $this->stdout("  Marked {$slug} as simple purchase\n");
            }
        }

        return ExitCode::OK;
    }

    public function actionPress(?string $basePath = null): int
    {
        $basePath = rtrim($basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com', '/');
        $section = Craft::$app->entries->getSectionByHandle('press');
        if (!$section) {
            $this->stderr("  Press section not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $contentVolume = Craft::$app->getVolumes()->getVolumeByHandle('content');
        $rewriter = new TemplateExtractorService(['templateBase' => $basePath]);

        $articles = [
            ['slug' => 'introducing-innove-fall-winter-2023', 'file' => '2024/02/08/introducing-innove-fall-winter-2023/index.html'],
            ['slug' => 'spring-art-fair-styles', 'file' => '2024/02/08/spring-art-fair-styles/index.html'],
            ['slug' => 'new-summer-in-store-exclusives', 'file' => '2024/02/08/new-summer-in-store-exclusives/index.html'],
            ['slug' => 'crafted-with-character', 'file' => '2024/02/08/crafted-with-character/index.html'],
            ['slug' => 'spotlight-on-hooded-belted-cape', 'file' => '2024/02/08/spotlight-on-hooded-belted-cape/index.html'],
            ['slug' => 'innove-new-yorks-first-store-opening-in-soho-ny', 'file' => '2024/02/08/innove-new-yorks-first-store-opening-in-soho-ny/index.html'],
        ];

        foreach ($articles as $article) {
            $file = $basePath . '/' . $article['file'];
            if (!file_exists($file)) {
                $this->stderr("  Missing press HTML: {$article['file']}\n", Console::FG_YELLOW);
                continue;
            }

            $parsed = $this->_parsePressArticle(file_get_contents($file), dirname($file), $basePath, $rewriter);
            if (!$parsed) {
                $this->stderr("  Failed to parse press article: {$article['slug']}\n", Console::FG_RED);
                continue;
            }

            $entry = Entry::find()->section('press')->slug($article['slug'])->one();
            $isNew = !$entry;
            if (!$entry) {
                $entry = new Entry([
                    'sectionId' => $section->id,
                    'typeId' => $entryType->id,
                    'slug' => $article['slug'],
                    'enabled' => true,
                ]);
            }

            $entry->title = $parsed['title'];
            if ($parsed['postDate']) {
                $entry->postDate = $parsed['postDate'];
            }

            $heroAsset = null;
            if ($parsed['heroPath'] && $contentVolume) {
                $heroAsset = $this->_importPressAsset($parsed['heroPath'], $contentVolume);
            }

            $entry->setFieldValues([
                'pageBody' => $parsed['body'],
                'pageHeroImage' => $heroAsset ? [$heroAsset->id] : [],
            ]);

            if (!Craft::$app->elements->saveElement($entry)) {
                $this->stderr("  Failed to save press entry: {$article['slug']}\n", Console::FG_RED);
                continue;
            }

            $this->stdout('  ' . ($isNew ? 'Created' : 'Updated') . " press entry: {$article['slug']}\n");
        }

        return ExitCode::OK;
    }

    private function _parsePressArticle(string $html, string $fileDir, string $basePath, TemplateExtractorService $rewriter): ?array
    {
        if (!preg_match('/<h1 class="elementor-heading-title[^"]*">(.*?)<\/h1>/s', $html, $titleMatch)) {
            return null;
        }
        $title = html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postDate = null;
        if (preg_match('/itemprop="datePublished".*?<time>([^<]+)<\/time>/s', $html, $dateMatch)) {
            $postDate = \DateTime::createFromFormat('F j, Y', trim($dateMatch[1])) ?: null;
        }

        $heroPath = null;
        if (preg_match('/theme-post-featured-image.*?data-lazy-src="([^"]+)"/s', $html, $heroMatch)) {
            $heroPath = $this->_resolvePressAssetPath($heroMatch[1], $fileDir, $basePath);
        } elseif (preg_match('/theme-post-featured-image.*?<noscript>.*?src="([^"]+\.jpg)"/s', $html, $heroMatch)) {
            $heroPath = $this->_resolvePressAssetPath($heroMatch[1], $fileDir, $basePath);
        }

        $body = '';
        if (preg_match('/elementor-element-7d788b0.*?<div class="elementor-widget-container">\s*(.*?)\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<div class="elementor-element elementor-element-d324a43/s', $html, $bodyMatch)) {
            $body = $rewriter->rewritePaths($bodyMatch[1]);
        }

        return [
            'title' => $title,
            'postDate' => $postDate,
            'heroPath' => $heroPath,
            'body' => $body ?: $title,
        ];
    }

    private function _resolvePressAssetPath(string $path, string $fileDir, string $basePath): ?string
    {
        $path = html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($path, 'http')) {
            $path = parse_url($path, PHP_URL_PATH) ?: $path;
        }

        $candidates = [];
        if (str_starts_with($path, '/wp-content/')) {
            $candidates[] = $basePath . $path;
            $candidates[] = Craft::getAlias('@webroot') . $path;
        } else {
            $candidates[] = realpath($fileDir . '/' . $path) ?: ($fileDir . '/' . $path);
        }

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function _importPressAsset(string $filePath, $volume): ?Asset
    {
        if (!file_exists($filePath)) {
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

    public function actionDeliveryAndReturns(?string $basePath = null): int
    {
        $basePath = rtrim($basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com', '/');
        $section = Craft::$app->entries->getSectionByHandle('deliveryAndReturns');
        if (!$section) {
            $this->stderr("  Delivery and Returns section not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $legacy = Entry::find()->section('pages')->slug('delivery-and-returns')->one();
        if ($legacy) {
            Craft::$app->elements->deleteElement($legacy);
            $this->stdout("  Removed legacy pages entry: delivery-and-returns\n");
        }

        $file = $basePath . '/index.html?p=736.html';
        if (!file_exists($file)) {
            $file = $basePath . '/delivery-and-returns/index.html';
        }
        if (!file_exists($file)) {
            $file = $basePath . '/delivery-and-returns.html';
        }
        if (!file_exists($file)) {
            $this->stderr("  Missing delivery-and-returns HTML\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $html = file_get_contents($file);
        $rewriter = new TemplateExtractorService(['templateBase' => $basePath]);
        $body = '';
        if (preg_match('/elementor-element-6bd4fae.*?<div class="elementor-widget-container">\s*(.*?)\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/article/is', $html, $bodyMatch)) {
            $body = $rewriter->rewritePaths($bodyMatch[1]);
        }

        $entry = Entry::find()->section('deliveryAndReturns')->one();
        if (!$entry) {
            $entryType = $section->getEntryTypes()[0] ?? null;
            if (!$entryType) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'enabled' => true,
            ]);
        }

        $entry->title = 'Delivery and returns';
        $entry->setFieldValues(['pageBody' => $body ?: $entry->title]);

        if (!Craft::$app->elements->saveElement($entry)) {
            $this->stderr("  Failed to save Delivery and Returns single\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('  Seeded Delivery and Returns single (' . strlen($body) . " bytes body)\n");
        return ExitCode::OK;
    }

    public function actionSustainability(?string $basePath = null): int
    {
        $basePath = rtrim($basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com', '/');
        $section = Craft::$app->entries->getSectionByHandle('sustainability');
        if (!$section) {
            $this->stderr("  Sustainability section not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $legacy = Entry::find()->section('pages')->slug('sustainability')->one();
        if ($legacy) {
            Craft::$app->elements->deleteElement($legacy);
            $this->stdout("  Removed legacy pages entry: sustainability\n");
        }

        $file = $basePath . '/index.html?p=4565.html';
        if (!file_exists($file)) {
            $file = $basePath . '/sustainability/index.html';
        }
        if (!file_exists($file)) {
            $this->stderr("  Missing sustainability HTML\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $html = file_get_contents($file);
        $rewriter = new TemplateExtractorService(['templateBase' => $basePath]);
        $body = '';
        if (preg_match('/elementor-element-95016e6.*?<div class="elementor-widget-container">\s*(.*?)\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/article/is', $html, $bodyMatch)) {
            $body = $rewriter->rewritePaths($bodyMatch[1]);
        }

        $entry = Entry::find()->section('sustainability')->one();
        if (!$entry) {
            $entryType = $section->getEntryTypes()[0] ?? null;
            if (!$entryType) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'enabled' => true,
            ]);
        }

        $entry->title = 'Sustainability';
        $entry->setFieldValues(['pageBody' => $body ?: $entry->title]);

        if (!Craft::$app->elements->saveElement($entry)) {
            $this->stderr("  Failed to save Sustainability single\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('  Seeded Sustainability single (' . strlen($body) . " bytes body)\n");
        return ExitCode::OK;
    }

    public function actionOrderStatus(?string $basePath = null): int
    {
        $basePath = rtrim($basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com', '/');
        $this->_ensureOrderStatusSection();

        $section = Craft::$app->entries->getSectionByHandle('orderStatus');
        if (!$section) {
            $this->stderr("  Order Status section not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $file = $basePath . '/index.html?p=732.html';
        if (!file_exists($file)) {
            $file = $basePath . '/order-status/index.html';
        }
        if (!file_exists($file)) {
            $this->stderr("  Missing order-status HTML\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $html = file_get_contents($file);
        $rewriter = new TemplateExtractorService(['templateBase' => $basePath]);
        $body = '';
        if (preg_match('/elementor-element-6bd4fae.*?<div class="elementor-widget-container">\s*(.*?)\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/article/is', $html, $bodyMatch)) {
            $body = $rewriter->rewritePaths($bodyMatch[1]);
            $body = preg_replace('/action="[^"]*"/', 'action="/order-status"', $body) ?: $body;
        }

        $entry = Entry::find()->section('orderStatus')->one();
        if (!$entry) {
            $entryType = $section->getEntryTypes()[0] ?? null;
            if (!$entryType) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'enabled' => true,
            ]);
        }

        $entry->title = 'Order status';
        $entry->setFieldValues(['pageBody' => $body ?: $entry->title]);

        if (!Craft::$app->elements->saveElement($entry)) {
            $this->stderr("  Failed to save Order Status single\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('  Seeded Order Status single (' . strlen($body) . " bytes body)\n");
        return ExitCode::OK;
    }

    public function actionMobileMenu(?string $basePath = null): int
    {
        $basePath = rtrim($basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com', '/');
        $this->_ensureMobileMenuGlobal();

        $volume = Craft::$app->getVolumes()->getVolumeByHandle('content');
        if (!$volume) {
            $this->stderr("  Content volume not found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $bgAsset = $this->_importContentAsset($basePath . '/wp-content/uploads/2024/01/farol-106-JlriaTaLavA-unsplash.jpg', $volume)
            ?? $this->_importContentAsset(Craft::getAlias('@webroot/assets/wp-content/uploads/2024/01/farol-106-JlriaTaLavA-unsplash.jpg'), $volume);
        $promoAsset = $this->_importContentAsset($basePath . '/wp-content/uploads/2024/01/pexels-cottonbro-studio-7870749.jpg', $volume)
            ?? $this->_importContentAsset(Craft::getAlias('@webroot/assets/wp-content/uploads/2024/01/pexels-cottonbro-studio-7870749.jpg'), $volume);
        $logoAsset = $this->_importContentAsset($basePath . '/wp-content/uploads/2023/12/Logo.svg', $volume)
            ?? $this->_importContentAsset(Craft::getAlias('@webroot/assets/wp-content/uploads/2023/12/Logo.svg'), $volume);

        $menu = Craft::$app->getGlobals()->getSetByHandle('mobileMenuSettings');
        if (!$menu) {
            $this->stderr("  mobileMenuSettings global not found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $menu->setFieldValues([
            'menuBackgroundImage' => $bgAsset ? [$bgAsset->id] : [],
            'menuPromoImage' => $promoAsset ? [$promoAsset->id] : [],
            'menuPromoButtonText' => 'Discover Winter 23',
            'menuPromoButtonUrl' => '/product-category/women/collections/winter-23',
            'menuPrimaryNav' => [
                ['label' => 'Sustainability', 'url' => '/sustainability'],
                ['label' => 'Press', 'url' => '/press'],
                ['label' => 'Contact', 'url' => '/contact'],
            ],
            'menuFooterNav' => [
                ['label' => 'Order status', 'url' => '/order-status'],
                ['label' => 'Delivery and returns', 'url' => '/delivery-and-returns'],
            ],
        ]);

        if (!Craft::$app->elements->saveElement($menu)) {
            $this->stderr("  Failed to save mobileMenuSettings global\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $footer = Craft::$app->getGlobals()->getSetByHandle('footerSettings');
        if ($footer) {
            $footer->setFieldValues([
                'socialInstagram' => $footer->socialInstagram ?: 'https://www.instagram.com/innovecouture.vamtam/',
                'socialFacebook' => $footer->socialFacebook ?: 'https://www.facebook.com/',
                'socialPinterest' => $footer->socialPinterest ?: 'https://www.pinterest.com/',
            ]);
            Craft::$app->elements->saveElement($footer);
        }

        $site = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        if ($site && $logoAsset && !$site->siteLogo->one()) {
            $site->setFieldValues(['siteLogo' => [$logoAsset->id]]);
            Craft::$app->elements->saveElement($site);
        }

        $this->stdout("  Seeded mobileMenuSettings global\n");
        return ExitCode::OK;
    }

    private function _ensureMobileMenuGlobal(): void
    {
        $fieldsService = Craft::$app->getFields();
        $tableColumns = [
            'col1' => ['heading' => 'Label', 'handle' => 'label', 'width' => '40%', 'type' => 'singleline'],
            'col2' => ['heading' => 'URL', 'handle' => 'url', 'width' => '60%', 'type' => 'singleline'],
        ];

        $fieldDefs = [
            ['handle' => 'menuBackgroundImage', 'name' => 'Menu Background Image', 'type' => \craft\fields\Assets::class, 'settings' => ['allowedKinds' => ['image'], 'maxRelations' => 1]],
            ['handle' => 'menuPromoImage', 'name' => 'Menu Promo Image', 'type' => \craft\fields\Assets::class, 'settings' => ['allowedKinds' => ['image'], 'maxRelations' => 1]],
            ['handle' => 'menuPromoButtonText', 'name' => 'Menu Promo Button Text', 'type' => PlainText::class],
            ['handle' => 'menuPromoButtonUrl', 'name' => 'Menu Promo Button URL', 'type' => PlainText::class],
            ['handle' => 'menuPrimaryNav', 'name' => 'Menu Primary Navigation', 'type' => Table::class, 'settings' => ['columns' => $tableColumns]],
            ['handle' => 'menuFooterNav', 'name' => 'Menu Footer Navigation', 'type' => Table::class, 'settings' => ['columns' => $tableColumns]],
        ];

        $contentVolume = Craft::$app->getVolumes()->getVolumeByHandle('content');
        foreach ($fieldDefs as $def) {
            if ($fieldsService->getFieldByHandle($def['handle'])) {
                continue;
            }
            $settings = $def['settings'] ?? [];
            if ($def['type'] === \craft\fields\Assets::class && $contentVolume) {
                $settings['sources'] = ['volume:' . $contentVolume->uid];
            }
            $field = $fieldsService->createField([
                'type' => $def['type'],
                'name' => $def['name'],
                'handle' => $def['handle'],
                'settings' => $settings,
            ]);
            $fieldsService->saveField($field);
            $this->stdout("  Created field: {$def['handle']}\n");
        }

        $globalsService = Craft::$app->getGlobals();
        if ($globalsService->getSetByHandle('mobileMenuSettings')) {
            return;
        }

        $layout = new FieldLayout(['type' => GlobalSet::class]);
        $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
        $elements = [];
        foreach (['menuBackgroundImage', 'menuPromoImage', 'menuPromoButtonText', 'menuPromoButtonUrl', 'menuPrimaryNav', 'menuFooterNav'] as $handle) {
            $field = $fieldsService->getFieldByHandle($handle);
            if ($field) {
                $elements[] = Craft::$app->getFields()->createLayoutElement([
                    'type' => CustomField::class,
                    'fieldUid' => $field->uid,
                ]);
            }
        }
        $tab->setElements($elements);
        $layout->setTabs([$tab]);

        $set = new GlobalSet(['name' => 'Mobile Menu', 'handle' => 'mobileMenuSettings']);
        $set->setFieldLayout($layout);
        $globalsService->saveSet($set);
        $this->stdout("  Created global set: mobileMenuSettings\n");
    }

    private function _ensureOrderStatusSection(): void
    {
        $sectionsService = Craft::$app->entries;
        if ($sectionsService->getSectionByHandle('orderStatus')) {
            return;
        }

        $fieldsService = Craft::$app->getFields();
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $pageBody = $fieldsService->getFieldByHandle('pageBody');
        if (!$pageBody) {
            return;
        }

        $layout = new FieldLayout(['type' => Entry::class]);
        $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
        $tab->setElements([
            Craft::$app->getFields()->createLayoutElement(['type' => TitleField::class]),
            Craft::$app->getFields()->createLayoutElement([
                'type' => CustomField::class,
                'fieldUid' => $pageBody->uid,
            ]),
        ]);
        $layout->setTabs([$tab]);

        $entryType = new \craft\models\EntryType([
            'name' => 'Order Status',
            'handle' => 'orderStatus',
            'hasTitleField' => true,
        ]);
        $entryType->setFieldLayout($layout);
        $sectionsService->saveEntryType($entryType);

        $section = new Section([
            'name' => 'Order Status',
            'handle' => 'orderStatus',
            'type' => Section::TYPE_SINGLE,
            'enableVersioning' => true,
        ]);
        $section->setSiteSettings([$primarySite->id => new Section_SiteSettings([
            'siteId' => $primarySite->id,
            'enabledByDefault' => true,
            'hasUrls' => true,
            'uriFormat' => 'order-status',
            'template' => '_pages/order-status',
        ])]);
        $section->setEntryTypes([$entryType]);
        $sectionsService->saveSection($section);
        $this->stdout("  Created section: orderStatus\n");
    }

    private function _importContentAsset(string $filePath, $volume): ?Asset
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return $this->_importPressAsset($filePath, $volume);
    }
}
