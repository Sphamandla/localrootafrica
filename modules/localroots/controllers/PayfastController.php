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
            $order = $transaction->getOrder();
            if ($order) {
                Craft::$app->getModule('localroots')->transactionTracker->log(
                    $order,
                    'payfast',
                    'webhook',
                    (float)($post['amount_gross'] ?? $transaction->paymentAmount),
                    'ZAR',
                    'success',
                    (string)($post['pf_payment_id'] ?? ''),
                    null,
                    $post
                );
            }
            Commerce::getInstance()->getPayments()->completePayment($transaction);
        } elseif ($transaction) {
            $order = $transaction->getOrder();
            if ($order) {
                Craft::$app->getModule('localroots')->transactionTracker->log(
                    $order,
                    'payfast',
                    'webhook',
                    (float)($post['amount_gross'] ?? $transaction->paymentAmount),
                    'ZAR',
                    'failed',
                    (string)($post['pf_payment_id'] ?? ''),
                    null,
                    $post,
                    'Payment status: ' . ($post['payment_status'] ?? 'unknown')
                );
            }
        }

        return $this->asRaw('OK');
    }
}
