<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use yii\web\Response;

class CheckoutController extends Controller
{
    protected array|int|bool $allowAnonymous = [
        'track-progress',
        'track-attempt',
        'calculate-shipping',
        'apply-coupon',
        'save-cart-meta',
    ];

    public function actionTrackProgress(): Response
    {
        $this->requirePostRequest();
        $cart = Commerce::getInstance()->getCarts()->getCart(false);
        if (!$cart || !$cart->id) {
            return $this->asJson(['success' => false]);
        }

        $data = Craft::$app->getRequest()->getBodyParams();
        Craft::$app->getModule('localroots')->transactionTracker->trackIncompleteSubmission(
            $cart,
            $data,
            (string)($data['step'] ?? 'progress')
        );

        return $this->asJson(['success' => true]);
    }

    public function actionTrackAttempt(): Response
    {
        $this->requirePostRequest();
        $cart = Commerce::getInstance()->getCarts()->getCart(false);
        if (!$cart || !$cart->id) {
            return $this->asJson(['success' => false]);
        }

        $data = Craft::$app->getRequest()->getBodyParams();
        Craft::$app->getModule('localroots')->transactionTracker->trackIncompleteSubmission(
            $cart,
            $data,
            'payment_attempt'
        );

        return $this->asJson(['success' => true]);
    }

    public function actionCalculateShipping(): Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $cart = Commerce::getInstance()->getCarts()->getCart();

        $country = (string)$request->getBodyParam('country', 'ZA');
        if ($country !== 'ZA') {
            return $this->asJson(['success' => false, 'message' => 'Shipping only available in South Africa']);
        }

        $postalCode = (string)$request->getBodyParam('zipCode', '');
        $rate = Craft::$app->getModule('localroots')->courierGuy->getRateForPostalCode(
            $postalCode ?: null,
            $cart->totalWeight
        );

        return $this->asJson([
            'success' => true,
            'cost' => $rate,
            'formatted' => 'R ' . number_format($rate, 0, '.', ','),
        ]);
    }

    public function actionApplyCoupon(): Response
    {
        $this->requirePostRequest();
        Craft::$app->getModule('localroots')->envCoupons->syncIfChanged();

        $request = Craft::$app->getRequest();
        $cart = Commerce::getInstance()->getCarts()->getCart();
        $couponCode = strtoupper(trim((string)$request->getBodyParam('couponCode', '')));

        $cart->couponCode = $couponCode !== '' ? $couponCode : null;
        Craft::$app->getElements()->saveElement($cart);

        $cart = Commerce::getInstance()->getCarts()->getCart();
        $error = null;
        if ($couponCode !== '') {
            foreach ($cart->getNotices() as $notice) {
                if ($notice->attribute === 'couponCode' || stripos($notice->message, 'coupon') !== false) {
                    $error = $notice->message;
                    break;
                }
            }

            if ($error === null && $cart->totalDiscount <= 0) {
                $error = 'That coupon code is invalid or has expired.';
            }

            if ($error !== null) {
                $cart->couponCode = null;
                Craft::$app->getElements()->saveElement($cart);
                $cart = Commerce::getInstance()->getCarts()->getCart();
            }
        }

        return $this->asJson([
            'success' => $error === null,
            'message' => $error,
            'couponCode' => $cart->couponCode,
            'totalDiscount' => $cart->totalDiscount,
            'totalDiscountFormatted' => 'R ' . number_format($cart->totalDiscount, 0, '.', ','),
            'total' => $cart->totalPrice,
            'totalFormatted' => 'R ' . number_format($cart->totalPrice, 0, '.', ','),
        ]);
    }

    public function actionSaveCartMeta(): Response
    {
        $this->requirePostRequest();
        $cart = Commerce::getInstance()->getCarts()->getCart(false);
        if (!$cart) {
            return $this->asJson(['success' => false]);
        }

        $message = Craft::$app->getRequest()->getBodyParam('message');
        if ($message !== null) {
            $cart->message = trim((string)$message) ?: null;
        }

        $shippingMethod = Craft::$app->getRequest()->getBodyParam('shippingMethod');
        if ($shippingMethod !== null) {
            $note = trim((string)$cart->message);
            $shippingLabel = $shippingMethod === 'local_pickup' ? 'Local pickup' : 'The Courier Guy';
            $shippingNote = 'Shipping method: ' . $shippingLabel;
            $cart->message = $note !== '' ? $note . "\n\n" . $shippingNote : $shippingNote;
        }

        if ($message !== null || $shippingMethod !== null) {
            Craft::$app->getElements()->saveElement($cart);
        }

        return $this->asJson(['success' => true]);
    }
}
