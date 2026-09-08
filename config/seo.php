<?php

/**
 * Local Roots Africa — SEO module defaults.
 *
 * Integrates with SEOmatic for meta tags, JSON-LD, sitemaps, and robots.txt.
 * Inspired by SEOmatic, Ether SEO, and Sprout SEO patterns — extended for
 * rich social cards and AI/AEO discoverability.
 */
return [
    // Routes that should never be indexed (commerce/account flows).
    'noIndexRoutes' => [
        'cart',
        'checkout',
        'account',
        'account/<endpoint:[^/]+>',
        'account/<endpoint:[^/]+>/<id:[^/]+>',
        'login',
        'register',
        'localroots/payfast/notify',
        'localroots/ozow/notify',
        'localroots/yoco/notify',
        'localroots/checkout/track-progress',
        'localroots/checkout/track-attempt',
        'localroots/checkout/calculate-shipping',
        'localroots/newsletter/subscribe',
        'localroots/contact/submit',
        'localroots/shop/load-more',
        'localroots/reviews/submit',
        'localroots/questions/submit',
    ],

    // Path prefixes treated as noindex when matched.
    'noIndexPaths' => [
        'actions/',
        'admin',
        'cpresources/',
    ],

    // Twitter card defaults (summary_large_image for rich tiles).
    'twitter' => [
        'card' => 'summary_large_image',
        'site' => '', // Falls back to footerSettings.twitterUrl handle if empty.
        'creator' => '',
    ],

    // Open Graph image dimensions for social rich tiles.
    'ogImage' => [
        'width' => 1200,
        'height' => 630,
    ],

    // AI / AEO: machine-readable index and llms.txt (optional but helps non-Google AI crawlers).
    'aiIndex' => [
        'enabled' => true,
        'llmsTxt' => true,
        'jsonEndpoint' => true,
    ],

    // Organization schema defaults (overridden by siteSettings / footerSettings globals).
    'organization' => [
        'type' => 'Organization',
        'areaServed' => 'ZA',
        'knowsAbout' => [
            'African fashion',
            'Sustainable clothing',
            'E-commerce',
        ],
    ],
];
