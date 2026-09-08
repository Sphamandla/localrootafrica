<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use yii\web\Response;

class OzowController extends Controller
{
    protected array|int|bool $allowAnonymous = ['notify'];

    public function actionNotify(): Response
    {
        $this->requirePostRequest();
        $post = Craft::$app->getRequest()->getBodyParams();
        $hash = (string)($post['Optional1'] ?? '');
        $status = strtoupper(trim((string)($post['Status'] ?? '')));

        $transaction = $hash
            ? Commerce::getInstance()->getTransactions()->getTransactionByHash($hash)
            : null;

        if ($transaction) {
            $order = $transaction->getOrder();
            if ($order) {
                Craft::$app->getModule('localroots')->transactionTracker->log(
                    $order,
                    'ozow',
                    'webhook',
                    (float)($post['Amount'] ?? $transaction->paymentAmount),
                    (string)($post['CurrencyCode'] ?? 'ZAR'),
                    $status === 'COMPLETE' || $status === 'SUCCESS' ? 'success' : 'failed',
                    (string)($post['TransactionId'] ?? ''),
                    null,
                    $post
                );
            }

            if ($status === 'COMPLETE' || $status === 'SUCCESS') {
                Commerce::getInstance()->getPayments()->completePayment($transaction);
            }
        }

        return $this->asRaw('OK');
    }
}
