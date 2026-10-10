<?php

return [
    'singular' => 'TLD',
    'plural' => 'TLDs',
    'new' => 'New TLD',
    'empty' => 'No TLDs configured.',
    'saved' => 'TLD saved.',
    'fields' => [
        'name_help' => 'Without the leading dot.',
        'nameservers' => 'Authoritative nameservers',
        'nameservers_help' => 'One host name per line.',
        'default_ttl' => 'Default TTL',
        'soa_mname' => 'SOA primary nameserver',
        'soa_rname' => 'SOA responsible mailbox',
        'soa_serial' => 'SOA serial',
        'refresh' => 'Refresh',
        'retry' => 'Retry',
        'expire' => 'Expire',
        'negative_ttl' => 'Negative TTL',
    ],

    'zone_file' => 'Zone file',
    'errors' => [
        'invalid' => 'Invalid TLD.',
        'already_exists' => 'This TLD already exists.',
    ],
];
