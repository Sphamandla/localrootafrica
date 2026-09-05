<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to routes defined in Settings → Routes.
 *
 * Read about Craft’s routing behavior (and this file’s structure), here:
 * @link https://craftcms.com/docs/5.x/system/routing.html
 */

return [
    'localroots/payfast/notify' => 'localroots/payfast/notify',
    'shop' => ['template' => '_pages/products/index'],
    'shop/<slug:{slug}>' => ['template' => '_pages/products/category'],
    'cart' => ['template' => '_pages/cart/index'],
    'checkout' => ['template' => '_pages/checkout/index'],
    'account' => ['template' => '_pages/account/index'],
    'register' => ['template' => '_pages/account/register'],
];
