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
    'localroots/checkout/apply-coupon' => 'localroots/checkout/apply-coupon',
    'localroots/checkout/save-cart-meta' => 'localroots/checkout/save-cart-meta',
    'localroots/newsletter/subscribe' => 'localroots/newsletter/subscribe',
    'newsletter/subscribe' => 'localroots/newsletter/subscribe',
    'localroots/contact/submit' => 'localroots/contact/submit',
    'localroots/account/save-address' => 'localroots/account/save-address',
    'localroots/health' => 'localroots/health/index',
    'localroots/order-status/track' => 'localroots/order-status/track',
    'localroots/shop/load-more' => 'localroots/shop/load-more',
    'localroots/reviews/submit' => 'localroots/review/submit',
    'localroots/questions/submit' => 'localroots/question/submit',
    'localroots/seo/ai-index.json' => 'localroots/seo/ai-index',
    'llms.txt' => 'localroots/seo/llms-txt',
    'shop' => ['template' => '_pages/products/index'],
    'shop/page/<page:\d+>' => ['template' => '_pages/products/index'],
    'shop/<slug:{slug}>' => ['template' => '_pages/products/category'],
    'brands' => ['template' => '_pages/products/brands'],
    'brands/<slug:{slug}>' => ['template' => '_pages/products/brand'],
    'brands/<slug:{slug}>/page/<page:\d+>' => ['template' => '_pages/products/brand'],
    'product-category/<path:.*>/page/<page:\d+>' => ['template' => '_pages/products/category'],
    'product-category/<path:.*>' => ['template' => '_pages/products/category'],
    'cart' => ['template' => '_pages/cart/index'],
    'checkout' => ['template' => '_pages/checkout/index'],
    'checkout/success' => ['template' => '_pages/checkout/success'],
    'checkout/failed' => ['template' => '_pages/checkout/failed'],
    'checkout/canceled' => ['template' => '_pages/checkout/canceled'],
    'account' => ['template' => '_pages/account/index'],
    'account/<endpoint:[^/]+>' => ['template' => '_pages/account/index'],
    'account/<endpoint:[^/]+>/<id:[^/]+>' => ['template' => '_pages/account/index'],
    'login' => ['template' => '_pages/account/login'],
    'register' => ['template' => '_pages/account/register'],
    'wishlist' => ['template' => '_pages/wishlist'],
    'about' => ['template' => '_pages/about'],
    'sustainability' => ['template' => '_pages/sustainability'],
    'press' => ['template' => '_pages/press/index'],
    'contact' => ['template' => '_pages/contact'],
    'delivery-and-returns' => ['template' => '_pages/delivery-and-returns'],
    'order-status' => ['template' => '_pages/order-status'],
    'faq' => ['template' => '_pages/faq/index'],
    'privacy' => ['template' => '_pages/privacy'],
];
