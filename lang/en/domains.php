<?php

return [
    'singular' => 'Domain',
    'plural' => 'Domains',
    'new' => 'New domain',
    'edit' => 'Edit :name',
    'search' => 'Search domains...',
    'empty' => 'No domains found.',
    'none' => 'No domains.',
    'saved' => 'Domain saved.',
    'deleted' => 'Domain deleted.',
    'delete_confirm' => 'Delete this domain?',
    'managed_by_registry' => 'None (managed by the registry)',
    'fields' => [
        'label_help' => 'Unicode labels are converted to Punycode automatically.',
        'registrant' => 'Registrant',
        'registrant_handle' => 'Registrant handle',
        'admin_contacts' => 'Admin contacts',
        'tech_contacts' => 'Tech contacts',
        'billing_contacts' => 'Billing contacts',
        'handles_help' => 'Handles, comma separated',
        'registered' => 'Registered',
        'expires' => 'Expires',
        'auth_code' => 'Auth code',
        'statuses' => 'Statuses',
        'nameservers_help' => 'One host name per line. Unknown hosts are created.',
        'ds_records' => 'DS records',
        'ds_records_help' => 'One per line: key tag, algorithm, digest type, digest.',
    ],

    'roles' => [
        'admin' => 'Admin',
        'tech' => 'Tech',
        'billing' => 'Billing',
    ],

    'show' => [
        'registration' => 'Registration',
        'last_update' => 'Last update',
        'dnssec' => 'DNSSEC',
        'unsigned' => 'Unsigned delegation.',
        'no_nameservers' => 'No nameservers.',
    ],

    'errors' => [
        'invalid_label' => 'Invalid domain label.',
        'already_registered' => 'This domain is already registered.',
        'unknown_contacts' => 'Unknown contacts: :handles',
        'invalid_ds_record' => 'Invalid DS record: :line',
    ],
];
