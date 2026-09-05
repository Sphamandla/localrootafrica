<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;

class PayfastService extends Component
{
    private const SANDBOX_MERCHANT_ID = '10000100';
    private const SANDBOX_MERCHANT_KEY = '46f0cd694581a';
    private const SANDBOX_PASSPHRASE = 'jt7NOE43FZPn';

    private const ATTRIBUTE_ORDER = [
        'merchant_id', 'merchant_key', 'return_url', 'cancel_url', 'notify_url',
        'name_first', 'name_last', 'email_address', 'cell_number',
        'm_payment_id', 'amount', 'item_name', 'item_description',
        'custom_str1', 'custom_str2', 'custom_str3',
        'email_confirmation', 'confirmation_address', 'payment_method',
    ];

    public function isSandbox(): bool
    {
        return App::parseBooleanEnv(App::env('PAYFAST_SANDBOX')) ?? true;
    }

    public function processUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';
    }

    public function merchantId(): string
    {
        $id = trim((string)(App::env('PAYFAST_MERCHANT_ID') ?: ''));
        return $id !== '' && $id !== 'your_payfast_merchant_id' ? $id : self::SANDBOX_MERCHANT_ID;
    }

    public function merchantKey(): string
    {
        $key = trim((string)(App::env('PAYFAST_MERCHANT_KEY') ?: ''));
        return $key !== '' && $key !== 'your_payfast_merchant_key' ? $key : self::SANDBOX_MERCHANT_KEY;
    }

    public function passphrase(): string
    {
        $configured = trim((string)(App::env('PAYFAST_PASSPHRASE') ?: ''));
        if ($configured !== '' && $configured !== 'your_payfast_passphrase') {
            return $configured;
        }
        if ($this->isSandbox() && $this->merchantId() === self::SANDBOX_MERCHANT_ID) {
            return self::SANDBOX_PASSPHRASE;
        }
        return '';
    }

    public function buildCheckoutData(array $data): array
    {
        $data['merchant_id'] = $this->merchantId();
        $data['merchant_key'] = $this->merchantKey();
        $ordered = $this->orderCheckoutAttributes($data);
        $ordered['signature'] = $this->sign($ordered);
        return $ordered;
    }

    public function sign(array $data): string
    {
        $ordered = $this->orderCheckoutAttributes($data);
        return $this->md5ParamString($this->buildParamString($ordered));
    }

    public function validateItn(array $post): bool
    {
        $signature = strtolower(trim((string)($post['signature'] ?? '')));
        if ($signature === '') {
            return false;
        }

        $ordered = [];
        foreach ($post as $key => $val) {
            if ($key === 'signature') {
                break;
            }
            $ordered[(string)$key] = stripslashes((string)$val);
        }

        $expected = $this->md5ParamString($this->buildItnParamString($ordered));
        return hash_equals($expected, $signature);
    }

    private function orderCheckoutAttributes(array $data): array
    {
        unset($data['signature'], $data['passphrase']);
        $ordered = [];
        foreach (self::ATTRIBUTE_ORDER as $key) {
            if (array_key_exists($key, $data)) {
                $ordered[$key] = (string)$data[$key];
            }
        }
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $ordered)) {
                $ordered[$key] = (string)$value;
            }
        }
        return $ordered;
    }

    private function buildParamString(array $ordered): string
    {
        $pairs = [];
        foreach ($ordered as $key => $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $pairs[] = $key . '=' . $this->pfEncode($trimmed);
        }
        return implode('&', $pairs);
    }

    private function buildItnParamString(array $ordered): string
    {
        $pairs = [];
        foreach ($ordered as $key => $val) {
            if ($key === 'signature') {
                continue;
            }
            $pairs[] = $key . '=' . $this->pfEncode((string)$val);
        }
        return implode('&', $pairs);
    }

    private function md5ParamString(string $pairsOnly): string
    {
        $query = $pairsOnly;
        $passphrase = $this->passphrase();
        if ($passphrase !== '') {
            $query .= '&passphrase=' . $this->pfEncode($passphrase);
        }
        return md5($query);
    }

    private function pfEncode(string $value): string
    {
        return strtoupper(rawurlencode($value));
    }
}
