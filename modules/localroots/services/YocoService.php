<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\Json;

class YocoService extends Component
{
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function secretKey(): string
    {
        $key = trim((string)(App::env('YOCO_SECRET_KEY') ?: ''));

        return ($key !== '' && !str_contains($key, 'your_')) ? $key : '';
    }

    public function webhookSecret(): string
    {
        return trim((string)(App::env('YOCO_WEBHOOK_SECRET') ?: ''));
    }

    /**
     * Verify a Standard Webhooks (whsec_) signed delivery from Yoco.
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secret = $this->webhookSecret();
        if ($secret === '' || !str_starts_with($secret, 'whsec_')) {
            return false;
        }

        $webhookId = trim((string)($headers['webhook-id'] ?? $headers['Webhook-Id'] ?? ''));
        $timestamp = trim((string)($headers['webhook-timestamp'] ?? $headers['Webhook-Timestamp'] ?? ''));
        $signatureHeader = trim((string)($headers['webhook-signature'] ?? $headers['Webhook-Signature'] ?? ''));

        if ($webhookId === '' || $timestamp === '' || $signatureHeader === '') {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        $skew = abs(time() - (int)$timestamp);
        if ($skew > self::WEBHOOK_TOLERANCE_SECONDS) {
            Craft::warning('Yoco webhook timestamp outside tolerance window.', __METHOD__);

            return false;
        }

        $encodedKey = substr($secret, 6);
        $key = base64_decode($encodedKey, true);
        if ($key === false || $key === '') {
            return false;
        }

        $signedPayload = $webhookId . '.' . $timestamp . '.' . $rawBody;
        $expected = base64_encode(hash_hmac('sha256', $signedPayload, $key, true));

        foreach (preg_split('/\s+/', $signatureHeader) ?: [] as $part) {
            if (!str_starts_with($part, 'v1,')) {
                continue;
            }
            $candidate = substr($part, 3);
            if ($candidate !== '' && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Confirm checkout status directly with Yoco when webhook signing is unavailable.
     */
    public function fetchCheckoutStatus(string $checkoutId): ?string
    {
        $secretKey = $this->secretKey();
        if ($secretKey === '' || $checkoutId === '') {
            return null;
        }

        $url = 'https://payments.yoco.com/api/checkouts/' . rawurlencode($checkoutId);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || !$response) {
            Craft::warning('Yoco checkout lookup failed for ' . $checkoutId . ' (HTTP ' . $httpCode . ').', __METHOD__);

            return null;
        }

        try {
            $data = Json::decode($response);
        } catch (\Throwable) {
            return null;
        }

        return isset($data['status']) ? strtolower((string)$data['status']) : null;
    }

    public function isSuccessfulStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array(strtolower(trim($status)), ['successful', 'success', 'paid', 'completed'], true);
    }
}
