<?php

namespace modules\localroots\adjusters;

use craft\base\Component;
use craft\commerce\adjusters\Shipping as ShippingAdjuster;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use Craft;

class LocalRootsShippingAdjuster extends Component implements AdjusterInterface
{
    public function adjust(Order $order): array
    {
        /** @var \modules\localroots\services\ShippingService $shipping */
        $shipping = Craft::$app->getModule('localroots')->shipping;
        $data = $shipping->getForCart($order);

        if (!$data || ($data['method'] ?? '') === 'local_pickup') {
            return [];
        }

        $rate = (float)($data['rate'] ?? 0);
        if ($rate <= 0) {
            return [];
        }

        $adjustment = new OrderAdjustment();
        $adjustment->type = ShippingAdjuster::ADJUSTMENT_TYPE;
        $adjustment->name = 'Shipping';
        $adjustment->description = (string)($data['label'] ?? 'The Courier Guy');
        $adjustment->amount = $rate;
        $adjustment->sourceSnapshot = [
            'method' => $data['method'],
            'adjuster' => self::class,
        ];

        return [$adjustment];
    }
}
