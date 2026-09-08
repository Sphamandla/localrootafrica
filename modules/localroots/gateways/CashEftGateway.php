<?php

namespace modules\localroots\gateways;

use Craft;

class CashEftGateway extends OfflineGateway
{
    public function getPaymentFormHtml(array $params): ?string
    {
        return Craft::$app->getView()->renderTemplate('_commerce/gateways/cash-eft', $params);
    }
}
