<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use yii\web\Response;

class YocoController extends Controller
{
    protected array|int|bool $allowAnonymous = ['notify'];

    public function actionNotify(): Response
    {
        $this->requirePostRequest();
        $post = Craft::$app->getRequest()->getBodyParams();
        $reference = (string)($post['metadata']['orderId'] ?? $post['checkoutId'] ?? '');
        $status = (string)($post['status'] ?? '');

        $transaction = null;
        if ($reference) {
            $order = Commerce::getInstance()->getOrders()->getOrderByReferenceNumber($reference);
            if ($order) {
                $transactions = Commerce::getInstance()->getTransactions()->getTransactionsByOrderId($order->id);
                $transaction = $transactions[0] ?? null;
            }
        }

        if ($transaction) {
            $order = $transaction->getOrder();
            if ($order) {
                Craft::$app->getModule('localroots')->transactionTracker->log(
                    $order,
                    'yoco',
                    'webhook',
                    (float)($post['amount'] ?? $transaction->paymentAmount) / 100,
                    'ZAR',
                    $status === 'successful' ? 'success' : 'failed',
                    (string)($post['id'] ?? ''),
                    null,
                    $post
                );
            }

            if ($status === 'successful') {
                Commerce::getInstance()->getPayments()->completePayment($transaction);
            }
        }

        return $this->asJson(['success' => true]);
    }
}
