<?php

namespace modules\localroots;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use modules\localroots\gateways\CashEftGateway;
use modules\localroots\gateways\CashOnDeliveryGateway;
use modules\localroots\gateways\OzowGateway;
use modules\localroots\gateways\PayfastGateway;
use modules\localroots\gateways\YocoGateway;
use modules\localroots\services\CashEftEmailService;
use modules\localroots\services\OrderSyncService;
use modules\localroots\services\PayfastService;
use modules\localroots\services\ProductReviewService;
use yii\base\Event;
use yii\base\Module;

class LocalRootsModule extends Module
{
    public function init(): void
    {
        Craft::setAlias('@modules/localroots', __DIR__);
        $this->setComponents([
            'payfast' => PayfastService::class,
            'orderSync' => OrderSyncService::class,
            'cashEftEmail' => CashEftEmailService::class,
            'courierGuy' => services\CourierGuyService::class,
            'shopFilter' => services\ShopFilterService::class,
            'transactionTracker' => services\TransactionTracker::class,
            'envCoupons' => services\EnvCouponService::class,
            'productReviews' => ProductReviewService::class,
        ]);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\localroots\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\localroots\\controllers';
        }

        parent::init();

        if (Craft::$app->plugins->isPluginInstalled('commerce')) {
            $this->envCoupons->syncIfChanged();

            Event::on(
                \craft\commerce\services\Gateways::class,
                \craft\commerce\services\Gateways::EVENT_REGISTER_GATEWAY_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = PayfastGateway::class;
                    $event->types[] = OzowGateway::class;
                    $event->types[] = YocoGateway::class;
                    $event->types[] = CashEftGateway::class;
                    $event->types[] = CashOnDeliveryGateway::class;
                }
            );

            Event::on(
                Order::class,
                Order::EVENT_AFTER_ORDER_AUTHORIZED,
                function (Event $event) {
                    /** @var Order $order */
                    $order = $event->sender;
                    $gateway = $order->getGateway();
                    if (!$gateway || !in_array($gateway->handle, ['cash-eft', 'cash-on-delivery'], true)) {
                        return;
                    }

                    $module = $this;
                    $status = \craft\commerce\Plugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle('awaitingPayment');
                    if ($status) {
                        $order->orderStatusId = $status->id;
                        Craft::$app->getElements()->saveElement($order, false);
                    }

                    if ($gateway->handle === 'cash-eft') {
                        $module->cashEftEmail->sendPendingConfirmation($order);
                    }
                }
            );

            Event::on(
                Order::class,
                Order::EVENT_AFTER_ORDER_PAID,
                function (Event $event) {
                    /** @var Order $order */
                    $order = $event->sender;
                    if (!$order->isCompleted) {
                        return;
                    }

                    $transactions = \craft\commerce\Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($order->id);
                    foreach ($transactions as $transaction) {
                        $gateway = $transaction->getGateway();
                        if ($gateway && in_array($gateway->handle, ['cash-eft', 'cash-on-delivery'], true) && $transaction->type === TransactionRecord::TYPE_CAPTURE) {
                            $this->cashEftEmail->sendPaymentConfirmed($order);
                            break;
                        }
                    }
                }
            );

            Event::on(
                Order::class,
                Order::EVENT_AFTER_SAVE,
                function (ModelEvent $event) {
                    /** @var Order $order */
                    $order = $event->sender;
                    if (!$event->isNew) {
                        Craft::$app->getModule('localroots')->orderSync->syncOrder($order);
                    }
                }
            );

            Event::on(
                Order::class,
                Order::EVENT_AFTER_COMPLETE_ORDER,
                function (Event $event) {
                    /** @var Order $order */
                    $order = $event->sender;
                    $module = Craft::$app->getModule('localroots');
                    $module->orderSync->syncOrder($order);
                    $module->productReviews->sendReviewInvites($order);
                }
            );
        }
    }
}
