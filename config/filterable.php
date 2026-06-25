<?php

return [

    'cache' => [

        /*
         * Enable query result caching globally.
         * Can be overridden per model via the $cache property.
         */
        'enabled' => env('FILTERABLE_CACHE', false),

        /*
         * Default cache TTL in seconds.
         * Can be overridden per model via the $cache property.
         */
        'ttl' => (int) env('FILTERABLE_CACHE_TTL', 3600),

    ],

];
