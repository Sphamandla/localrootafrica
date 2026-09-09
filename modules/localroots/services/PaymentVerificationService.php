<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\Transaction;

class PaymentVerificationService extends Component
{
    public function verifyTransactionAmount(Transaction $transaction, float $paidAmount, string $currency = 'ZAR'): bool
    {
        $order = $transaction->getOrder();
        if (!$order) {
            Craft::warning('Payment verification failed: transaction has no order.', __METHOD__);

            return false;
        }

        return $this->verifyOrderAmount($order, $paidAmount, $currency);
    }

    public function verifyOrderAmount(Order $order, float $paidAmount, string $currency = 'ZAR'): bool
    {
        if (strtoupper($currency) !== strtoupper((string)$order->currency)) {
            Craft::warning(sprintf(
                'Payment currency mismatch for order %s: expected %s, got %s',
                $order->reference ?? $order->id,
                $order->currency,
                $currency
            ), __METHOD__);

            return false;
        }

        $expected = round((float)($order->outstandingBalance ?? $order->totalPrice), 2);
        $paid = round($paidAmount, 2);

        if (abs($expected - $paid) > 0.01) {
            Craft::warning(sprintf(
                'Payment amount mismatch for order %s: expected %.2f, got %.2f',
                $order->reference ?? $order->id,
                $expected,
                $paid
            ), __METHOD__);

            return false;
        }

        return true;
    }
}
