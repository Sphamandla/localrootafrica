<?php

use craft\helpers\App;

return [
    '*' => [
        'cachingEnabled' => App::env('BLITZ_ENABLED') !== 'false',
        'cacheStorageType' => 'putyourlightson\blitz\drivers\storage\FileStorage',
        'cacheStorageSettings' => [
            'folderPath' => '@webroot/cache/blitz',
        ],
        'excludedUriPatterns' => [
            ['uriPattern' => 'cart'],
            ['uriPattern' => 'checkout'],
            ['uriPattern' => 'account'],
            ['uriPattern' => 'login'],
            ['uriPattern' => 'commerce/.*'],
            ['uriPattern' => 'localroots/.*'],
        ],
    ],
];
