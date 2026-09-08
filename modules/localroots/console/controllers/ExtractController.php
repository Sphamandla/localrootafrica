<?php

namespace modules\localroots\console\controllers;

use craft\helpers\App;
use modules\localroots\services\TemplateExtractorService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class ExtractController extends Controller
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

    public function actionTemplates(): int
    {
        $basePath = $this->path ?? App::env('TEMPLATE_HTML_PATH') ?: '/Users/test/www/localrootsafrica/innovecouture.vamtam.com';

        $this->stdout("Extracting templates from: {$basePath}\n", Console::FG_GREEN);

        $service = new TemplateExtractorService([
            'templateBase' => rtrim($basePath, '/'),
        ]);

        $written = $service->extractAll();

        foreach ($written as $file) {
            $this->stdout("  Wrote: {$file}\n");
        }

        $this->stdout("Extracted " . count($written) . " template fragments.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
