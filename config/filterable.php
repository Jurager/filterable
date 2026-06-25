<?php

return [

    'cache' => [

        /*
         * Enable query result caching for all models using HasFilterable.
         * Can be overridden per Filterable subclass via $cacheEnabled.
         */
        'enabled' => env('FILTERABLE_CACHE', false),

        /*
         * Default cache TTL in seconds.
         * Can be overridden per Filterable subclass via $cacheTtl.
         */
        'ttl' => (int) env('FILTERABLE_CACHE_TTL', 3600),

    ],

];
