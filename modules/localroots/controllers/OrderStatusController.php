<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\web\Controller;
use yii\web\Response;

class OrderStatusController extends Controller
{
    protected array|int|bool $allowAnonymous = ['track'];

    public function actionTrack(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $orderId = trim((string) $request->getBodyParam('orderid', ''));
        $email = trim((string) $request->getBodyParam('order_email', ''));
        $redirect = $request->getValidatedBodyParam('redirect') ?: '/order-status';

        if ($orderId === '' || $email === '') {
            Craft::$app->getSession()->setError('Please enter your order number and billing email.');
            return $this->redirect($redirect);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Craft::$app->getSession()->setError('Please enter a valid billing email address.');
            return $this->redirect($redirect);
        }

        $order = $this->_findOrder($orderId, $email);
        if (!$order) {
            Craft::$app->getSession()->setError('No order found with that number and email address.');
            return $this->redirect($redirect);
        }

        $courierGuy = Craft::$app->getModule('localroots')->courierGuy;
        $waybill = $courierGuy->getTrackingReferenceForOrder($order);
        $tracking = $waybill ? $courierGuy->getTrackingByWaybill($waybill) : null;

        Craft::$app->getSession()->setFlash('orderStatusTrack', [
            'order' => [
                'reference' => $order->reference ?? (string) $order->id,
                'number' => $order->number,
                'email' => $order->email,
                'dateOrdered' => $order->dateOrdered?->format('Y-m-d H:i:s'),
                'isPaid' => (bool) $order->isPaid,
                'isCompleted' => (bool) $order->isCompleted,
                'orderStatus' => $order->getOrderStatus()?->name,
                'totalPrice' => (float) $order->totalPrice,
                'lineItems' => array_map(static fn ($lineItem) => [
                    'description' => $lineItem->description,
                    'qty' => (float) $lineItem->qty,
                    'total' => (float) $lineItem->total,
                ], $order->getLineItems()),
            ],
            'waybill' => $waybill,
            'tracking' => $tracking,
            'submitted' => [
                'orderid' => $orderId,
                'order_email' => $email,
            ],
        ]);

        return $this->redirect($redirect);
    }

    private function _findOrder(string $orderId, string $email): ?Order
    {
        $candidates = Order::find()
            ->isCompleted(true)
            ->orderBy('dateOrdered desc')
            ->reference($orderId)
            ->all();

        if (!$candidates) {
            $candidates = Order::find()
                ->isCompleted(true)
                ->orderBy('dateOrdered desc')
                ->number($orderId)
                ->all();
        }

        if (!$candidates && ctype_digit($orderId)) {
            $candidates = Order::find()
                ->isCompleted(true)
                ->id((int) $orderId)
                ->all();
        }

        foreach ($candidates as $order) {
            if (strcasecmp((string) $order->email, $email) === 0) {
                return $order;
            }
        }

        return null;
    }
}
