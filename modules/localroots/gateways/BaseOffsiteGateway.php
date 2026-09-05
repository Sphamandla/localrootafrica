<?php

namespace modules\localroots\gateways;

use craft\commerce\base\Gateway;
use craft\commerce\errors\NotImplementedException;
use craft\web\Response as WebResponse;

abstract class BaseOffsiteGateway extends Gateway
{
    public function processWebHook(): WebResponse
    {
        return new WebResponse(['statusCode' => 200]);
    }

    public function supportsAuthorize(): bool
    {
        return false;
    }

    public function supportsCapture(): bool
    {
        return false;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    public function supportsPurchase(): bool
    {
        return true;
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function supportsPartialRefund(): bool
    {
        return false;
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }
}
