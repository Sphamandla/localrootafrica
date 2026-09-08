<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use yii\web\Response;

class OzowController extends Controller
{
    protected array|int|bool $allowAnonymous = ['notify'];

    public $enableCsrfValidation = false;

    public function actionNotify(): Response
    {
        $this->requirePostRequest();
        $post = Craft::$app->getRequest()->getBodyParams();

        /** @var \modules\localroots\services\OzowService $ozow */
        $ozow = Craft::$app->getModule('localroots')->ozow;

        if (!$ozow->validateNotifyHash($post)) {
            Craft::warning('Ozow notify rejected: invalid HashCheck.', __METHOD__);
            $this->response->setStatusCode(403);

            return $this->asRaw('INVALID');
        }

        $hash = (string)($post['Optional1'] ?? '');
        $status = strtoupper(trim((string)($post['Status'] ?? '')));

        $transaction = $hash
            ? Commerce::getInstance()->getTransactions()->getTransactionByHash($hash)
            : null;

        if (!$transaction) {
            return $this->asRaw('OK');
        }

        $order = $transaction->getOrder();
        if ($order) {
            Craft::$app->getModule('localroots')->transactionTracker->log(
                $order,
                'ozow',
                'webhook',
                (float)($post['Amount'] ?? $transaction->paymentAmount),
                (string)($post['CurrencyCode'] ?? 'ZAR'),
                $ozow->isSuccessStatus($status) ? 'success' : 'failed',
                (string)($post['TransactionId'] ?? ''),
                null,
                $post
            );
        }

        if ($ozow->isSuccessStatus($status)) {
            Commerce::getInstance()->getPayments()->completePayment($transaction);
        }

        return $this->asRaw('OK');
    }
}
