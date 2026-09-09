<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Json;
use craft\web\Controller;
use yii\web\Response;

class YocoController extends Controller
{
    protected array|int|bool $allowAnonymous = ['notify'];

    public $enableCsrfValidation = false;

    public function actionNotify(): Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $rawBody = $request->getRawBody();

        /** @var \modules\localroots\services\YocoService $yoco */
        $yoco = Craft::$app->getModule('localroots')->yoco;

        $headers = [
            'webhook-id' => $request->getHeaders()->get('webhook-id'),
            'webhook-timestamp' => $request->getHeaders()->get('webhook-timestamp'),
            'webhook-signature' => $request->getHeaders()->get('webhook-signature'),
        ];

        $webhookSecret = $yoco->webhookSecret();
        if ($webhookSecret !== '') {
            if (!$yoco->verifyWebhookSignature($rawBody, $headers)) {
                Craft::warning('Yoco notify rejected: webhook signature verification failed.', __METHOD__);
                $this->response->setStatusCode(403);

                return $this->asJson(['success' => false]);
            }
        } elseif (!Craft::$app->getConfig()->getGeneral()->devMode) {
            Craft::warning('Yoco notify rejected: YOCO_WEBHOOK_SECRET is not configured.', __METHOD__);
            $this->response->setStatusCode(403);

            return $this->asJson(['success' => false]);
        }

        try {
            $post = $rawBody !== '' ? Json::decode($rawBody) : $request->getBodyParams();
        } catch (\Throwable) {
            Craft::warning('Yoco notify rejected: invalid JSON body.', __METHOD__);
            $this->response->setStatusCode(400);

            return $this->asJson(['success' => false]);
        }

        $payload = is_array($post['payload'] ?? null) ? $post['payload'] : $post;
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $transactionHash = (string)($metadata['transactionHash'] ?? '');
        $checkoutId = (string)($payload['checkoutId'] ?? $payload['id'] ?? $post['checkoutId'] ?? '');
        $status = strtolower(trim((string)($payload['status'] ?? $post['status'] ?? '')));

        $transaction = $transactionHash
            ? Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionHash)
            : null;

        if (!$transaction && $checkoutId !== '') {
            $reference = (string)($metadata['orderReference'] ?? '');
            if ($reference !== '') {
                $order = Commerce::getInstance()->getOrders()->getOrderByReferenceNumber($reference);
                if ($order) {
                    $transactions = Commerce::getInstance()->getTransactions()->getTransactionsByOrderId($order->id);
                    $transaction = $transactions[0] ?? null;
                }
            }
        }

        if (!$transaction) {
            return $this->asJson(['success' => true]);
        }

        if ($checkoutId !== '') {
            $apiStatus = $yoco->fetchCheckoutStatus($checkoutId);
            if ($apiStatus === null && $webhookSecret === '') {
                Craft::warning('Yoco notify rejected: could not confirm checkout via API.', __METHOD__);
                $this->response->setStatusCode(403);

                return $this->asJson(['success' => false]);
            }
            if ($apiStatus !== null) {
                $status = $apiStatus;
            }
        } elseif ($webhookSecret === '') {
            Craft::warning('Yoco notify rejected: missing checkout id for API verification.', __METHOD__);
            $this->response->setStatusCode(403);

            return $this->asJson(['success' => false]);
        }

        $order = $transaction->getOrder();
        if ($order) {
            Craft::$app->getModule('localroots')->transactionTracker->log(
                $order,
                'yoco',
                'webhook',
                (float)($payload['amount'] ?? $post['amount'] ?? $transaction->paymentAmount) / 100,
                'ZAR',
                $yoco->isSuccessfulStatus($status) ? 'success' : 'failed',
                $checkoutId,
                null,
                is_array($post) ? $post : []
            );
        }

        if ($yoco->isSuccessfulStatus($status)) {
            $amount = (float)($payload['amount'] ?? $post['amount'] ?? 0) / 100;
            if (!Craft::$app->getModule('localroots')->paymentVerification->verifyTransactionAmount($transaction, $amount, 'ZAR')) {
                return $this->asJson(['success' => false, 'error' => 'amount_mismatch']);
            }

            Commerce::getInstance()->getPayments()->completePayment($transaction);
        }

        return $this->asJson(['success' => true]);
    }
}
