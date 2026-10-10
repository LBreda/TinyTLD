<?php

return [
    'singular' => 'Nameserver',
    'plural' => 'Nameservers',
    'new' => 'New nameserver',
    'search' => 'Search nameservers...',
    'empty' => 'No nameservers found.',
    'saved' => 'Nameserver saved.',
    'delete_confirm' => 'Delete this nameserver?',
    'still_delegated' => 'The nameserver is still delegated by some domains.',
    'fields' => [
        'host' => 'Host',
        'host_name' => 'Host name',
        'addresses' => 'Addresses',
        'ip_addresses' => 'IP addresses',
        'ip_addresses_help' => 'IPv4/IPv6 glue, one per line. Only needed for hosts inside a TLD served by this registry.',
    ],

    'errors' => [
        'invalid_host_name' => 'Invalid host name.',
        'invalid_ip' => 'Invalid IP address: :ip',
        'already_exists' => 'This nameserver already exists.',
    ],
];
