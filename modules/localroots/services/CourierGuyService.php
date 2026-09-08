<?php

namespace modules\localroots\services;

use craft\helpers\App;
use craft\helpers\Json;
use yii\base\Component;

class CourierGuyService extends Component
{
    public function getRateForPostalCode(?string $postalCode, float $weight = 1.0): float
    {
        $apiKey = App::env('COURIER_GUY_API_KEY');
        $testMode = App::env('COURIER_GUY_TEST_MODE') !== 'false';
        $fallback = (float)(App::env('COURIER_GUY_FALLBACK_RATE') ?: 99.0);

        if (!$postalCode || $testMode || !$apiKey || str_contains($apiKey, 'your_')) {
            return $fallback;
        }

        $apiUrl = App::env('COURIER_GUY_API_URL') ?: 'https://api.thecourierguy.co.za';

        try {
            $payload = [
                'collection_address' => ['postal_code' => App::env('COURIER_GUY_ORIGIN_POSTCODE') ?: '2000'],
                'delivery_address' => ['postal_code' => $postalCode],
                'parcels' => [['weight' => max(0.5, $weight)]],
            ];

            $ch = curl_init(rtrim($apiUrl, '/') . '/rates');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => Json::encode($payload),
                CURLOPT_TIMEOUT => 8,
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && $response) {
                $data = Json::decode($response);
                if (!empty($data['rate'])) {
                    return (float)$data['rate'];
                }
                if (!empty($data['rates'][0]['price'])) {
                    return (float)$data['rates'][0]['price'];
                }
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }
}
