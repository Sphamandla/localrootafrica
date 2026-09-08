<?php

namespace modules\localroots\gateways;

use craft\commerce\gateways\Manual;

/**
 * Base class for offline checkout methods that complete immediately without an external payment provider.
 */
abstract class OfflineGateway extends Manual
{
}
