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
}
