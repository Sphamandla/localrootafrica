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

class OzowGateway extends BaseOffsiteGateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        return Craft::$app->getView()->renderTemplate('_commerce/gateways/ozow', $params);
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
        throw new NotImplementedException('Ozow does not support authorize.');
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotImplementedException('Ozow does not support capture.');
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Ozow does not support authorize.');
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $request = Craft::$app->getRequest();
        $status = strtoupper(trim((string)$request->getParam('Status', '')));
        $reference = (string)$request->getParam('TransactionReference', '');

        if ($status === 'COMPLETE' || $status === 'SUCCESS') {
            return RedirectResponse::success($reference, $request->getQueryParams());
        }

        return RedirectResponse::failed('Ozow payment status: ' . $status);
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException('Ozow does not support payment sources.');
    }

    public function deletePaymentSource(string $token): bool
    {
        return false;
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        $order = $transaction->getOrder();
        $siteCode = App::env('OZOW_SITE_CODE') ?: 'your_ozow_site_code';
        $privateKey = App::env('OZOW_PRIVATE_KEY') ?: 'your_ozow_private_key';
        $isSandbox = App::parseBooleanEnv(App::env('OZOW_SANDBOX')) ?? true;

        $siteUrl = rtrim(App::env('PRIMARY_SITE_URL') ?: Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), '/');
        $amount = number_format($transaction->paymentAmount, 2, '.', '');
        $transactionRef = $order->reference ?? (string)$order->id;
        $bankRef = 'LR-' . $transactionRef;
        $cancelUrl = UrlHelper::siteUrl('checkout');
        $errorUrl = UrlHelper::siteUrl('checkout');
        $successUrl = UrlHelper::siteUrl('commerce/payments/complete-payment', [
            'commerceTransactionHash' => $transaction->hash,
        ]);
        $notifyUrl = UrlHelper::siteUrl('localroots/ozow/notify');
        $isTest = $isSandbox ? 'true' : 'false';

        $hashString = implode('', [
            $siteCode, $isTest, $transactionRef, $bankRef, $amount,
            $cancelUrl, $errorUrl, $successUrl, $notifyUrl, $privateKey,
        ]);
        $hashCheck = hash('sha512', strtolower($hashString));

        $data = [
            'SiteCode' => $siteCode,
            'CountryCode' => 'ZA',
            'CurrencyCode' => 'ZAR',
            'Amount' => $amount,
            'TransactionReference' => $transactionRef,
            'BankReference' => $bankRef,
            'Optional1' => $transaction->hash,
            'CancelUrl' => $cancelUrl,
            'ErrorUrl' => $errorUrl,
            'SuccessUrl' => $successUrl,
            'NotifyUrl' => $notifyUrl,
            'IsTest' => $isTest,
            'HashCheck' => $hashCheck,
        ];

        $url = $isSandbox
            ? 'https://pay.ozow.com'
            : 'https://pay.ozow.com';

        return RedirectResponse::redirectPost($url, $data, $transactionRef);
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Ozow refunds must be processed in the Ozow dashboard.');
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
