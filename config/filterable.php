<?php

return [

    'cache' => [

        /*
         * Default cache TTL in seconds, used by ->cached() when the model's
         * $cache property doesn't specify one. Caching is always opt-in per
         * query — there is no global "cache everything" switch.
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
