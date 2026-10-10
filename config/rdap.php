<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RDAP Base URL
    |--------------------------------------------------------------------------
    |
    | Absolute URL of the RDAP service, used to build "self" and "related"
    | links. It must not end with a slash.
    |
    */

    'base_url' => rtrim((string) env('RDAP_BASE_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/rdap'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Port 43 WHOIS server
    |--------------------------------------------------------------------------
    |
    | Host name of the legacy WHOIS server, published as "port43" when set.
    |
    */

    'port43' => env('RDAP_PORT43'),

    /*
    |--------------------------------------------------------------------------
    | Search limits
    |--------------------------------------------------------------------------
    */

    'search_limit' => (int) env('RDAP_SEARCH_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | Rate limit (requests per minute per client IP)
    |--------------------------------------------------------------------------
    */

    'rate_limit' => (int) env('RDAP_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Notices
    |--------------------------------------------------------------------------
    */

    'terms_of_service_url' => env('RDAP_TERMS_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/terms'),

    'terms_of_service' => [
        'Service subject to the Terms of Use of the registry.',
        'Personal data is published only where the data subject has consented to its disclosure.',
    ],

    'status_codes_url' => env('RDAP_STATUS_CODES_URL', 'https://icann.org/epp'),

    'inaccuracy_complaint_url' => env('RDAP_INACCURACY_URL', 'https://icann.org/wicf'),

    'redaction_reason' => 'Server policy: data not disclosed by the contact',

];
