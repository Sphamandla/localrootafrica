<?php

namespace modules\localroots\services;

use craft\commerce\elements\Order;
use craft\helpers\App;
use craft\helpers\Json;
use yii\base\Component;

class CourierGuyService extends Component
{
    public function getTrackingReferenceForOrder(Order $order): ?string
    {
        $layout = $order->getFieldLayout();
        if ($layout && $layout->getFieldByHandle('courierTrackingReference')) {
            $value = trim((string) $order->getFieldValue('courierTrackingReference'));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTrackingByWaybill(string $waybill): ?array
    {
        $waybill = trim($waybill);
        if ($waybill === '') {
            return null;
        }

        $apiKey = App::env('COURIER_GUY_API_KEY');
        $testMode = App::env('COURIER_GUY_TEST_MODE') !== 'false';
        $apiUrl = rtrim(App::env('COURIER_GUY_API_URL') ?: 'https://api.thecourierguy.co.za', '/');

        if ($testMode || !$apiKey || str_contains($apiKey, 'your_')) {
            return $this->_mockTracking($waybill);
        }

        $tracking = $this->_requestTracking($apiUrl . '/tracking/shipments/public?waybill=' . rawurlencode($waybill), $apiKey);
        if ($tracking) {
            return $tracking;
        }

        return $this->_requestTracking(
            $apiUrl . '/tracking/shipments?include_parcels=true&custom_tracking_reference=' . rawurlencode($waybill),
            $apiKey,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function _requestTracking(string $url, string $apiKey): ?array
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || !$response) {
                return null;
            }

            $data = Json::decode($response);
            if (!is_array($data)) {
                return null;
            }

            if (isset($data[0]) && is_array($data[0])) {
                $data = $data[0];
            }

            return $this->_normalizeTracking($data);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function _normalizeTracking(array $data): array
    {
        $events = [];
        foreach (($data['tracking_events'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $events[] = [
                'date' => (string) ($event['date'] ?? ''),
                'status' => (string) ($event['status'] ?? ''),
                'message' => (string) ($event['message'] ?? ''),
                'location' => (string) ($event['location'] ?? ''),
            ];
        }

        usort($events, static fn (array $a, array $b) => strcmp($b['date'], $a['date']));

        return [
            'reference' => (string) ($data['custom_tracking_reference'] ?? ''),
            'status' => (string) ($data['status'] ?? ''),
            'serviceLevel' => (string) ($data['service_level_code'] ?? ''),
            'created' => (string) ($data['shipment_time_created'] ?? ''),
            'collected' => (string) ($data['shipment_collected_date'] ?? ''),
            'delivered' => (string) ($data['shipment_delivered_date'] ?? ''),
            'collectionHub' => (string) ($data['collection_hub'] ?? ''),
            'deliveryHub' => (string) ($data['delivery_hub'] ?? ''),
            'events' => $events,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function _mockTracking(string $waybill): array
    {
        return [
            'reference' => $waybill,
            'status' => 'in-transit',
            'serviceLevel' => 'ECO',
            'created' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'collected' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'delivered' => '',
            'collectionHub' => 'CPT',
            'deliveryHub' => 'JNB',
            'events' => [
                [
                    'date' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                    'status' => 'in-transit',
                    'message' => 'Parcel is on its way to the delivery hub.',
                    'location' => 'Johannesburg',
                ],
                [
                    'date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'status' => 'collected',
                    'message' => 'Parcel collected from sender.',
                    'location' => 'Cape Town',
                ],
                [
                    'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'status' => 'submitted',
                    'message' => 'Shipment submitted to The Courier Guy.',
                    'location' => '',
                ],
            ],
            'mock' => true,
        ];
    }

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
