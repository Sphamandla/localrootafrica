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
        $module = Craft::$app->getModule('localroots');
        $cart = Commerce::getInstance()->getCarts()->getCart();

        $country = (string)$request->getBodyParam('country', 'ZA');
        if ($country !== 'ZA') {
            return $this->asJson(['success' => false, 'message' => 'Shipping only available in South Africa']);
        }

        $postalCode = (string)$request->getBodyParam('zipCode', '');
        $method = (string)$request->getBodyParam('shippingMethod', 'courier_guy');
        $cart = $module->shipping->applyToCart($cart, $method, $postalCode ?: null);
        $totals = $module->shipping->cartTotals($cart);

        return $this->asJson([
            'success' => true,
            'cost' => $totals['shipping'],
            'formatted' => 'R ' . number_format($totals['shipping'], 0, '.', ','),
            'total' => $totals['total'],
            'totalFormatted' => 'R ' . number_format($totals['total'], 0, '.', ','),
            'tax' => $totals['tax'],
            'discount' => $totals['discount'],
        ]);
    }

    public function actionApplyCoupon(): Response
    {
        $this->requirePostRequest();
        Craft::$app->getModule('localroots')->envCoupons->syncIfChanged();

        $request = Craft::$app->getRequest();
        $module = Craft::$app->getModule('localroots');
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

        $method = (string)$request->getBodyParam('shippingMethod', 'courier_guy');
        $cart = $module->shipping->applyToCart($cart, $method);
        $totals = $module->shipping->cartTotals($cart);

        return $this->asJson([
            'success' => $error === null,
            'message' => $error,
            'couponCode' => $cart->couponCode,
            'totalDiscount' => $totals['discount'],
            'totalDiscountFormatted' => 'R ' . number_format($totals['discount'], 0, '.', ','),
            'total' => $totals['total'],
            'totalFormatted' => 'R ' . number_format($totals['total'], 0, '.', ','),
        ]);
    }

    public function actionSaveCartMeta(): Response
    {
        $this->requirePostRequest();
        $module = Craft::$app->getModule('localroots');
        $cart = Commerce::getInstance()->getCarts()->getCart(false);
        if (!$cart) {
            return $this->asJson(['success' => false]);
        }

        $request = Craft::$app->getRequest();
        $message = $request->getBodyParam('message');
        if ($message !== null) {
            $cart->message = trim((string)$message) ?: null;
        }

        $shippingMethod = $request->getBodyParam('shippingMethod');
        if ($shippingMethod !== null) {
            $postalCode = (string)$request->getBodyParam('postalCode', '');
            $cart = $module->shipping->applyToCart(
                $cart,
                (string)$shippingMethod,
                $postalCode !== '' ? $postalCode : null
            );

            $note = trim((string)($message ?? $cart->message ?? ''));
            $shippingLabel = $shippingMethod === 'local_pickup' ? 'Local pickup' : 'The Courier Guy';
            $shippingNote = 'Shipping method: ' . $shippingLabel;
            $cart->message = $note !== '' ? $note . "\n\n" . $shippingNote : $shippingNote;
            Craft::$app->getElements()->saveElement($cart);
            $cart = Commerce::getInstance()->getCarts()->getCart();
        } elseif ($message !== null) {
            Craft::$app->getElements()->saveElement($cart);
            $cart = Commerce::getInstance()->getCarts()->getCart();
        }

        $totals = $module->shipping->cartTotals($cart);

        return $this->asJson([
            'success' => true,
            'total' => $totals['total'],
            'totalFormatted' => 'R ' . number_format($totals['total'], 0, '.', ','),
            'shipping' => $totals['shipping'],
            'shippingFormatted' => 'R ' . number_format($totals['shipping'], 0, '.', ','),
        ]);
    }
}
