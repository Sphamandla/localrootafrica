<?php

namespace modules\localroots\gateways;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;
use modules\localroots\models\RedirectResponse;

class CashEftGateway extends Gateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        return Craft::$app->getView()->renderTemplate('_commerce/gateways/cash-eft', $params);
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
        ];
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        $order = $transaction->getOrder();
        $reference = 'EFT-' . ($order->reference ?? (string) $order->id);

        return RedirectResponse::success($reference);
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return RedirectResponse::success($reference ?: 'EFT-CAPTURED');
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Cash/EFT does not support offsite authorize completion.');
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Cash/EFT does not support purchase completion.');
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotImplementedException('Cash/EFT uses authorize only.');
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotImplementedException('Cash/EFT does not support payment sources.');
    }

    public function deletePaymentSource(string $token): bool
    {
        return false;
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        throw new NotImplementedException('Cash/EFT refunds must be processed manually.');
    }

    public function processWebHook(): WebResponse
    {
        return new WebResponse(['statusCode' => 200]);
    }

    public function supportsAuthorize(): bool
    {
        return true;
    }

    public function supportsCapture(): bool
    {
        return true;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    public function supportsPurchase(): bool
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

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }
}
