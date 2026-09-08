<?php

namespace modules\localroots\console\controllers;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class CouponsController extends Controller
{
    public function actionSyncFromEnv(): int
    {
        $stats = Craft::$app->getModule('localroots')->envCoupons->syncFromEnv();
        Craft::$app->getCache()->delete('localroots-env-coupons-hash');

        $this->stdout(sprintf(
            "Synced env coupons (created: %d, updated: %d, disabled: %d)\n",
            $stats['created'],
            $stats['updated'],
            $stats['disabled']
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
