<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\helpers\MoneyHelper;

class CashEftEmailService extends Component
{
    public function sendPendingConfirmation(Order $order): bool
    {
        $email = $order->email;
        if (!$email) {
            return false;
        }

        $bank = $this->getBankDetails();
        $reference = $order->reference ?? (string) $order->id;
        $total = MoneyHelper::formatCurrency($order->totalPrice, $order->currency);

        $lines = [
            'Hi ' . trim(($order->billingAddress?->firstName ?? '') . ' ' . ($order->billingAddress?->lastName ?? '')) . ',',
            '',
            'Thank you for your order. Your order has been received and is awaiting payment.',
            '',
            'Order number: ' . $reference,
            'Amount due: ' . $total,
            '',
            'Please pay via EFT using the bank details below. Use your order number as the payment reference.',
            '',
            'Bank: ' . $bank['bankName'],
            'Account name: ' . $bank['accountName'],
            'Account number: ' . $bank['accountNumber'],
            'Branch code: ' . $bank['branchCode'],
            'Account type: ' . $bank['accountType'],
            'Reference: ' . $reference,
            '',
            'Once we receive your payment, we will update your order and begin processing.',
            '',
            'If you have any questions, reply to this email.',
        ];

        return $this->send($email, 'Order ' . $reference . ' – awaiting EFT payment', implode("\n", $lines));
    }

    public function sendCodConfirmation(Order $order): bool
    {
        $email = $order->email;
        if (!$email) {
            return false;
        }

        $reference = $order->reference ?? (string) $order->id;
        $total = MoneyHelper::formatCurrency($order->totalPrice, $order->currency);

        $lines = [
            'Hi ' . trim(($order->billingAddress?->firstName ?? '') . ' ' . ($order->billingAddress?->lastName ?? '')) . ',',
            '',
            'Thank you for your order. We have received it and will prepare it for delivery.',
            '',
            'Order number: ' . $reference,
            'Amount due on delivery: ' . $total,
            '',
            'Please have the exact amount ready when your order arrives.',
            '',
            'If you have any questions, reply to this email.',
        ];

        return $this->send($email, 'Order ' . $reference . ' – cash on delivery', implode("\n", $lines));
    }

    public function sendPaymentConfirmed(Order $order): bool
    {
        $email = $order->email;
        if (!$email) {
            return false;
        }

        $reference = $order->reference ?? (string) $order->id;
        $total = MoneyHelper::formatCurrency($order->totalPrice, $order->currency);

        $lines = [
            'Hi ' . trim(($order->billingAddress?->firstName ?? '') . ' ' . ($order->billingAddress?->lastName ?? '')) . ',',
            '',
            'We have received your EFT payment for order ' . $reference . '.',
            'Amount paid: ' . $total,
            '',
            'Your order is now being processed. We will notify you when it ships.',
        ];

        return $this->send($email, 'Payment received for order ' . $reference, implode("\n", $lines));
    }

    /**
     * @return array{bankName: string, accountName: string, accountNumber: string, branchCode: string, accountType: string}
     */
    public function getBankDetails(): array
    {
        return [
            'bankName' => Craft::parseEnv('$EFT_BANK_NAME') ?: 'First National Bank',
            'accountName' => Craft::parseEnv('$EFT_ACCOUNT_NAME') ?: 'Local Roots Africa (Pty) Ltd',
            'accountNumber' => Craft::parseEnv('$EFT_ACCOUNT_NUMBER') ?: '62812345678',
            'branchCode' => Craft::parseEnv('$EFT_BRANCH_CODE') ?: '250655',
            'accountType' => Craft::parseEnv('$EFT_ACCOUNT_TYPE') ?: 'Cheque',
        ];
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
            Craft::error('Cash/EFT email failed: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
