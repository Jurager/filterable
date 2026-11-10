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

];
