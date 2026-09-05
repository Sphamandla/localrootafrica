<?php

namespace modules\localroots\gateways;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use modules\localroots\models\RedirectResponse;

class YocoGateway extends BaseOffsiteGateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        return Craft::$app->getView()->renderTemplate('_commerce/gateways/yoco', $params);
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotImplementedException('Yoco does not support authorize.');
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotImplementedException('Yoco does not support capture.');
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Yoco does not support authorize.');
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $request = Craft::$app->getRequest();
        $status = strtolower(trim((string)$request->getParam('status', '')));
        $reference = (string)$request->getParam('checkoutId', '');

        if (in_array($status, ['successful', 'success', 'paid'], true)) {
            return RedirectResponse::success($reference, $request->getQueryParams());
        }

        return RedirectResponse::failed('Yoco payment status: ' . $status);
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException('Yoco does not support payment sources.');
    }

    public function deletePaymentSource(string $token): bool
    {
        return false;
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        $order = $transaction->getOrder();
        $secretKey = App::env('YOCO_SECRET_KEY') ?: 'your_yoco_secret_key';
        $isSandbox = App::parseBooleanEnv(App::env('YOCO_SANDBOX')) ?? true;

        $amountCents = (int)round($transaction->paymentAmount * 100);
        $successUrl = UrlHelper::siteUrl('commerce/payments/complete-payment', [
            'commerceTransactionHash' => $transaction->hash,
        ]);
        $cancelUrl = UrlHelper::siteUrl('checkout');
        $failureUrl = UrlHelper::siteUrl('checkout');

        $apiUrl = $isSandbox
            ? 'https://payments.yoco.com/api/checkouts'
            : 'https://payments.yoco.com/api/checkouts';

        $payload = [
            'amount' => $amountCents,
            'currency' => 'ZAR',
            'cancelUrl' => $cancelUrl,
            'successUrl' => $successUrl,
            'failureUrl' => $failureUrl,
            'metadata' => [
                'orderReference' => $order->reference ?? (string)$order->id,
                'transactionHash' => $transaction->hash,
            ],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['redirectUrl'])) {
                return RedirectResponse::redirectGet(
                    $data['redirectUrl'],
                    $data['id'] ?? ($order->reference ?? (string)$order->id)
                );
            }
        }

        return RedirectResponse::failed('Unable to initiate Yoco checkout.');
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Yoco refunds must be processed in the Yoco dashboard.');
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }
}
