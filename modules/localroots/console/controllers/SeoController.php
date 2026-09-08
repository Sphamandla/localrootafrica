<?php

namespace modules\localroots\console\controllers;

use Craft;
use craft\helpers\Console;
use craft\helpers\UrlHelper;
use yii\console\Controller;
use yii\console\ExitCode;

class SeoController extends Controller
{
    /**
     * Print SEO module status and remind to configure SEOmatic in the CP.
     */
    public function actionStatus(): int
    {
        $seomatic = Craft::$app->plugins->isPluginInstalled('seomatic');
        $this->stdout('Local Roots SEO module' . PHP_EOL, Console::FG_GREEN);
        $this->stdout('  SEOmatic installed: ' . ($seomatic ? 'yes' : 'no') . PHP_EOL);

        $seo = Craft::$app->getGlobals()->getSetByHandle('seoSettings');
        $this->stdout('  Default title: ' . ($seo?->defaultSeoTitle ?: '(not set)') . PHP_EOL);
        $this->stdout('  Default description: ' . ($seo?->defaultSeoDescription ? 'set' : '(not set)') . PHP_EOL);
        $this->stdout('  llms.txt: ' . UrlHelper::siteUrl('llms.txt') . PHP_EOL);
        $this->stdout('  AI index: ' . UrlHelper::siteUrl('localroots/seo/ai-index.json') . PHP_EOL);

        if (!$seomatic) {
            $this->stderr('  Install SEOmatic: php craft plugin/install seomatic' . PHP_EOL, Console::FG_YELLOW);
        } else {
            $this->stdout('  Configure SEOmatic: Admin → SEOmatic → Dashboard' . PHP_EOL);
        }

        return ExitCode::OK;
    }
}
