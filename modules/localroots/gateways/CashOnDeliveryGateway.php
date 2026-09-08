<?php

namespace modules\localroots\gateways;

use Craft;

class CashOnDeliveryGateway extends OfflineGateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        return Craft::$app->getView()->renderTemplate('_commerce/gateways/cash-on-delivery', $params);
    }
}
