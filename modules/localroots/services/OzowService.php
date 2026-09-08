<?php

namespace modules\localroots\services;

use craft\base\Component;
use craft\helpers\App;

class OzowService extends Component
{
    public function privateKey(): string
    {
        return trim((string)(App::env('OZOW_PRIVATE_KEY') ?: ''));
    }

    /**
     * Validates an Ozow NotifyUrl POST payload using SHA512 HashCheck.
     *
     * @see https://ozow.com/integrations
     */
    public function validateNotifyHash(array $post): bool
    {
        $received = strtolower(trim((string)($post['Hash'] ?? $post['HashCheck'] ?? '')));
        if ($received === '') {
            return false;
        }

        $privateKey = $this->privateKey();
        if ($privateKey === '' || str_contains($privateKey, 'your_')) {
            return false;
        }

        $siteCode = (string)($post['SiteCode'] ?? '');
        $transactionId = (string)($post['TransactionId'] ?? '');
        $transactionReference = (string)($post['TransactionReference'] ?? '');
        $amount = (string)($post['Amount'] ?? '');
        $status = (string)($post['Status'] ?? '');
        $optional1 = (string)($post['Optional1'] ?? '');
        $optional2 = (string)($post['Optional2'] ?? '');
        $optional3 = (string)($post['Optional3'] ?? '');
        $optional4 = (string)($post['Optional4'] ?? '');
        $optional5 = (string)($post['Optional5'] ?? '');
        $currencyCode = (string)($post['CurrencyCode'] ?? 'ZAR');
        $isTest = (string)($post['IsTest'] ?? '');
        $statusMessage = (string)($post['StatusMessage'] ?? '');

        $stringToHash = strtolower(
            $siteCode
            . $transactionId
            . $transactionReference
            . $amount
            . $status
            . $optional1
            . $optional2
            . $optional3
            . $optional4
            . $optional5
            . $currencyCode
            . $isTest
            . $statusMessage
            . $privateKey
        );

        $expected = hash('sha512', $stringToHash);

        return hash_equals($expected, $received);
    }

    public function isSuccessStatus(string $status): bool
    {
        $status = strtoupper(trim($status));

        return in_array($status, ['COMPLETE', 'SUCCESS', 'COMPLETED'], true);
    }
}
