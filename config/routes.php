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
    'localroots/ozow/notify' => 'localroots/ozow/notify',
    'localroots/yoco/notify' => 'localroots/yoco/notify',
    'localroots/checkout/track-progress' => 'localroots/checkout/track-progress',
    'localroots/checkout/track-attempt' => 'localroots/checkout/track-attempt',
    'localroots/checkout/calculate-shipping' => 'localroots/checkout/calculate-shipping',
    'localroots/newsletter/subscribe' => 'localroots/newsletter/subscribe',
    'localroots/contact/submit' => 'localroots/contact/submit',
    'shop' => ['template' => '_pages/products/index'],
    'shop/page/<page:\d+>' => ['template' => '_pages/products/index'],
    'shop/<slug:{slug}>' => ['template' => '_pages/products/category'],
    'product-category/<path:.*>' => ['template' => '_pages/products/category'],
    'cart' => ['template' => '_pages/cart/index'],
    'checkout' => ['template' => '_pages/checkout/index'],
    'account' => ['template' => '_pages/account/index'],
    'login' => ['template' => '_pages/account/login'],
    'register' => ['template' => '_pages/account/register'],
    'wishlist' => ['template' => '_pages/wishlist'],
    'about' => ['template' => '_pages/about'],
    'sustainability' => ['template' => '_pages/sustainability'],
    'press' => ['template' => '_pages/press/index'],
    'contact' => ['template' => '_pages/contact'],
    'faq' => ['template' => '_pages/faq/index'],
];
