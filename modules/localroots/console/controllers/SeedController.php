<?php

namespace modules\localroots\console\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\StringHelper;
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
