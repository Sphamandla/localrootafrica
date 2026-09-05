<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use modules\localroots\services\PayfastService;
use yii\web\Response;

class PayfastController extends Controller
{
    protected array|int|bool $allowAnonymous = ['notify'];

    public function actionNotify(): Response
    {
        $this->requirePostRequest();
        $post = Craft::$app->getRequest()->getBodyParams();

        /** @var PayfastService $payfast */
        $payfast = Craft::$app->getModule('localroots')->payfast;

        if (!$payfast->validateItn($post)) {
            return $this->asRaw('INVALID');
        }

        $hash = (string)($post['custom_str1'] ?? '');
        $transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($hash);

        if ($transaction && strtoupper((string)($post['payment_status'] ?? '')) === 'COMPLETE') {
            Commerce::getInstance()->getPayments()->completePayment($transaction);
        }

        return $this->asRaw('OK');
    }
}
