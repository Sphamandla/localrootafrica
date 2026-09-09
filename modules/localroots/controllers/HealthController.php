<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class HealthController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index'];

    public function actionIndex(): Response
    {
        $checks = [
            'app' => true,
            'db' => false,
            'commerce' => Craft::$app->plugins->isPluginInstalled('commerce'),
        ];

        try {
            Craft::$app->getDb()->open();
            Craft::$app->getDb()->createCommand('SELECT 1')->queryScalar();
            $checks['db'] = true;
        } catch (\Throwable) {
            $checks['db'] = false;
        }

        $ok = $checks['app'] && $checks['db'] && $checks['commerce'];
        if (!$ok) {
            $this->response->setStatusCode(503);
        }

        return $this->asJson([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'time' => gmdate('c'),
        ]);
    }
}
