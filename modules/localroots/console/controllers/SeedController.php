<?php

namespace modules\localroots\console\controllers;

use Craft;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\fields\Lightswitch;
use craft\fields\Table;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
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
        $this->actionFaq($basePath);
        $this->actionPress($basePath);

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
            ['slug' => 'sustainability', 'title' => 'Sustainability', 'file' => 'sustainability/index.html'],
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
        $basePath = $basePath ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';
        $section = Craft::$app->entries->getSectionByHandle('press');
        if (!$section) {
            $this->stderr("  Press section not found. Run setup first.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        $articles = [
            ['slug' => 'innove-new-yorks-first-store-opening-in-soho-ny', 'title' => "Innove New York's First Store Opening in Soho, NY", 'file' => '2024/02/08/innove-new-yorks-first-store-opening-in-soho-ny/index.html'],
        ];

        foreach ($articles as $article) {
            if (Entry::find()->section('press')->slug($article['slug'])->one()) {
                continue;
            }
            $body = '';
            $file = rtrim($basePath, '/') . '/' . $article['file'];
            if (file_exists($file) && preg_match('/<div[^>]*class="page-content[^"]*"[^>]*>(.*?)<\/article>/is', file_get_contents($file), $m)) {
                $body = StringHelper::truncate(strip_tags($m[1]), 4000);
            }

            $entry = new Entry([
                'sectionId' => $section->id,
                'typeId' => $entryType->id,
                'title' => $article['title'],
                'slug' => $article['slug'],
                'enabled' => true,
            ]);
            $entry->setFieldValues(['pageBody' => $body ?: $article['title']]);
            Craft::$app->elements->saveElement($entry);
            $this->stdout("  Created press entry: {$article['slug']}\n");
        }

        return ExitCode::OK;
    }
}
