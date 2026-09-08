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
use modules\localroots\services\PayfastService;

class PayfastGateway extends BaseOffsiteGateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        return Craft::$app->getView()->renderTemplate('_commerce/gateways/payfast', $params);
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
        throw new NotImplementedException('PayFast does not support authorize.');
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotImplementedException('PayFast does not support capture.');
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('PayFast does not support authorize.');
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $request = Craft::$app->getRequest();
        $post = $request->getBodyParams();

        if (empty($post)) {
            $post = $_POST;
        }

        /** @var PayfastService $payfast */
        $payfast = Craft::$app->getModule('localroots')->payfast;

        if (!$payfast->validateItn($post)) {
            return RedirectResponse::failed('PayFast payment validation failed.');
        }

        $status = strtoupper(trim((string)($post['payment_status'] ?? '')));
        if ($status === 'COMPLETE') {
            return RedirectResponse::success(
                (string)($post['pf_payment_id'] ?? $post['m_payment_id'] ?? ''),
                $post
            );
        }

        return RedirectResponse::failed('Payment status: ' . $status);
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException('PayFast does not support payment sources.');
    }

    public function deletePaymentSource(string $token): bool
    {
        return false;
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        $order = $transaction->getOrder();
        /** @var PayfastService $payfast */
        $payfast = Craft::$app->getModule('localroots')->payfast;

        $siteUrl = rtrim(App::env('PRIMARY_SITE_URL') ?: Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), '/');

        $data = [
            'return_url' => UrlHelper::siteUrl('commerce/payments/complete-payment', [
                'commerceTransactionHash' => $transaction->hash,
            ]),
            'cancel_url' => UrlHelper::siteUrl('checkout/canceled'),
            'notify_url' => UrlHelper::siteUrl('localroots/payfast/notify'),
            'name_first' => $order->billingAddress?->firstName ?? 'Customer',
            'name_last' => $order->billingAddress?->lastName ?? '',
            'email_address' => $order->email ?? '',
            'm_payment_id' => $order->reference ?? (string)$order->id,
            'amount' => number_format($transaction->paymentAmount, 2, '.', ''),
            'item_name' => 'Order ' . ($order->reference ?? $order->id),
            'item_description' => 'Local Roots Africa order',
            'custom_str1' => $transaction->hash,
        ];

        $checkout = $payfast->buildCheckoutData($data);

        return RedirectResponse::redirectPost(
            $payfast->processUrl(),
            $checkout,
            (string)($order->reference ?? $order->id)
        );
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('PayFast refunds must be processed in the PayFast dashboard.');
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function supportsPartialRefund(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }
}
