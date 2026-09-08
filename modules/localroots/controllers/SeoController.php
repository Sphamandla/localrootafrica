<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use modules\localroots\services\SeoService;
use yii\web\Response;

class SeoController extends Controller
{
    protected array|int|bool $allowAnonymous = ['ai-index', 'llms-txt'];

    public function actionAiIndex(): Response
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('seo');
        if (empty($config['aiIndex']['jsonEndpoint'])) {
            throw new \yii\web\NotFoundHttpException('AI index is disabled.');
        }

        /** @var SeoService $seo */
        $seo = Craft::$app->getModule('localroots')->seo;

        return $this->asJson($seo->buildAiIndex());
    }

    public function actionLlmsTxt(): Response
    {
        $config = Craft::$app->getConfig()->getConfigFromFile('seo');
        if (empty($config['aiIndex']['llmsTxt'])) {
            throw new \yii\web\NotFoundHttpException('llms.txt is disabled.');
        }

        /** @var SeoService $seo */
        $seo = Craft::$app->getModule('localroots')->seo;

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->content = $seo->buildLlmsTxt();

        return $response;
    }
}
