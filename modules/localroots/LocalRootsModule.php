<?php

namespace modules\localroots;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderEvent;
use craft\events\RegisterComponentTypesEvent;
use modules\localroots\gateways\OzowGateway;
use modules\localroots\gateways\PayfastGateway;
use modules\localroots\gateways\YocoGateway;
use modules\localroots\services\OrderSyncService;
use modules\localroots\services\PayfastService;
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
            'courierGuy' => services\CourierGuyService::class,
        ]);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\localroots\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\localroots\\controllers';
        }

        parent::init();

        if (Craft::$app->plugins->isPluginInstalled('commerce')) {
            Event::on(
                \craft\commerce\services\Gateways::class,
                \craft\commerce\services\Gateways::EVENT_REGISTER_GATEWAY_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = PayfastGateway::class;
                    $event->types[] = OzowGateway::class;
                    $event->types[] = YocoGateway::class;
                }
            );

            Event::on(
                Order::class,
                Order::EVENT_AFTER_SAVE,
                function (OrderEvent $event) {
                    if (!$event->isNew) {
                        Craft::$app->getModule('localroots')->orderSync->syncOrder($event->order);
                    }
                }
            );

            Event::on(
                Order::class,
                Order::EVENT_AFTER_COMPLETE_ORDER,
                function (OrderEvent $event) {
                    Craft::$app->getModule('localroots')->orderSync->syncOrder($event->order);
                }
            );
        }
    }
}
