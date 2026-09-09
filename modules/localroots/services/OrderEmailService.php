<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\helpers\MoneyHelper;

class OrderEmailService extends Component
{
    public function sendOnlinePaymentConfirmation(Order $order): bool
    {
        $email = $order->email;
        if (!$email) {
            return false;
        }

        $reference = $order->reference ?? (string)$order->id;
        $total = MoneyHelper::formatCurrency($order->totalPrice, $order->currency);
        $gateway = $order->getGateway();
        $gatewayName = $gateway?->name ?? 'Online payment';

        $lines = array_merge(
            $this->greetingLines($order),
            [
                '',
                'Thank you for your order. We have received your payment via ' . $gatewayName . '.',
                '',
                'Order number: ' . $reference,
                'Amount paid: ' . $total,
                '',
            ],
            $this->lineItemLines($order),
            [
                '',
                'We will notify you when your order ships.',
                '',
                'If you have any questions, reply to this email.',
            ]
        );

        return $this->send($email, 'Order ' . $reference . ' confirmed', implode("\n", $lines));
    }

    public function sendOrderReceived(Order $order): bool
    {
        $email = $order->email;
        if (!$email) {
            return false;
        }

        $reference = $order->reference ?? (string)$order->id;
        $total = MoneyHelper::formatCurrency($order->totalPrice, $order->currency);

        $lines = array_merge(
            $this->greetingLines($order),
            [
                '',
                'We have received your order.',
                '',
                'Order number: ' . $reference,
                'Order total: ' . $total,
                '',
            ],
            $this->lineItemLines($order),
            [
                '',
                'We will keep you updated as your order progresses.',
            ]
        );

        return $this->send($email, 'Order ' . $reference . ' received', implode("\n", $lines));
    }

    /**
     * @return string[]
     */
    private function greetingLines(Order $order): array
    {
        $name = trim(($order->billingAddress?->firstName ?? '') . ' ' . ($order->billingAddress?->lastName ?? ''));

        return ['Hi ' . ($name !== '' ? $name : 'there') . ','];
    }

    /**
     * @return string[]
     */
    private function lineItemLines(Order $order): array
    {
        $lines = ['Items:'];
        foreach ($order->getLineItems() as $item) {
            $lines[] = sprintf(
                '- %s x %s: %s',
                $item->qty,
                $item->description,
                MoneyHelper::formatCurrency($item->total, $order->currency)
            );
        }

        if ($order->totalShippingCost > 0) {
            $lines[] = 'Shipping: ' . MoneyHelper::formatCurrency($order->totalShippingCost, $order->currency);
        }

        if ($order->totalTax > 0) {
            $lines[] = 'VAT: ' . MoneyHelper::formatCurrency($order->totalTax, $order->currency);
        }

        return $lines;
    }

    private function send(string $to, string $subject, string $body): bool
    {
        try {
            return Craft::$app->getMailer()
                ->compose()
                ->setTo($to)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (\Throwable $e) {
            Craft::error('Order email failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }
}
