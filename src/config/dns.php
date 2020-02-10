<?php

return [
    'ttl' => env('DNS_TTL', 3600),
    'spf' => env('DNS_SPF', null),
    'static' => env('DNS_STATIC', null),
    'copyfrom' => env('DNS_COPY_FROM', null),
];
