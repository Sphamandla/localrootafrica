<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;

class ShippingService extends Component
{
    private const SESSION_PREFIX = 'localroots_shipping_';

    public function sessionKey(Order $cart): string
    {
        $token = $cart->number ?: (string)($cart->id ?? 'guest');

        return self::SESSION_PREFIX . $token;
    }

    /**
     * @return array{method: string, rate: float, label: string}|null
     */
    public function getForCart(Order $cart): ?array
    {
        $data = Craft::$app->getSession()->get($this->sessionKey($cart));

        return is_array($data) ? $data : null;
    }

    public function applyToCart(Order $cart, string $method, ?string $postalCode = null): Order
    {
        $postalCode = $postalCode ?: $this->resolvePostalCode($cart, $method);
        $rate = $method === 'local_pickup'
            ? 0.0
            : Craft::$app->getModule('localroots')->courierGuy->getRateForPostalCode($postalCode, $cart->totalWeight);

        Craft::$app->getSession()->set($this->sessionKey($cart), [
            'method' => $method,
            'rate' => $rate,
            'label' => $method === 'local_pickup' ? 'Local pickup' : 'The Courier Guy',
        ]);

        Craft::$app->getElements()->saveElement($cart);

        return Commerce::getInstance()->getCarts()->getCart();
    }

    public function ensureDefaultShipping(Order $cart): Order
    {
        if ($this->getForCart($cart)) {
            return $cart;
        }

        return $this->applyToCart($cart, 'courier_guy', $this->resolvePostalCode($cart, 'courier_guy'));
    }

    public function resolvePostalCode(Order $cart, string $method): ?string
    {
        $shipping = $cart->shippingAddress;
        $billing = $cart->billingAddress;

        if ($method === 'courier_guy' && $shipping?->postalCode) {
            return (string)$shipping->postalCode;
        }

        if ($billing?->postalCode) {
            return (string)$billing->postalCode;
        }

        return $shipping?->postalCode ? (string)$shipping->postalCode : null;
    }

    public function getShippingMethodForOrder(Order $order): string
    {
        $message = (string)($order->message ?? '');
        if (str_contains($message, 'Shipping method: Local pickup')) {
            return 'local_pickup';
        }

        return 'courier_guy';
    }

    /**
     * @return array{total: float, shipping: float, tax: float, discount: float, subtotal: float}
     */
    public function cartTotals(Order $cart): array
    {
        $shipping = $this->getForCart($cart);

        return [
            'subtotal' => (float)$cart->itemSubtotal,
            'tax' => (float)$cart->totalTax,
            'discount' => (float)$cart->totalDiscount,
            'shipping' => (float)($shipping['rate'] ?? 0),
            'total' => (float)$cart->totalPrice,
        ];
    }
}
