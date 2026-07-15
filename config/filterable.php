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

    'included_relations' => [

        /*
         * Automatically apply filter[included.*] relation scoping to any model
         * that supports it (has loadIncludedRelations(), via HasFilterable),
         * whenever it's retrieved — search results, listings, single lookups.
         * No per-model or per-query wiring needed. Disable to apply it yourself.
         */
        'auto_load' => env('FILTERABLE_AUTO_LOAD_INCLUDED_RELATIONS', true),

    ],

];
