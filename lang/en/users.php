<?php

return [
    'singular' => 'User',
    'plural' => 'Users',
    'new' => 'New user',
    'search' => 'Search users...',
    'saved' => 'User saved.',
    'delete_confirm' => 'Delete this user?',
    'fields' => [
        'role' => 'Role',
        'password_help' => 'Leave empty to keep the current password.',
        'registry_administrator' => 'Registry administrator',
        'operators_help' => 'Operators only see the domains and contacts of their registrar.',
    ],

    'roles' => [
        'admin' => 'Registry admin',
        'operator' => 'Registrar operator',
        'none' => 'No access',
    ],

    'send_reset_link' => 'Send reset link',
    'reset_link_sent' => 'Password reset link sent.',
    'errors' => [
        'cannot_delete_self' => 'You cannot delete yourself.',
        'cannot_remove_own_admin' => 'You cannot remove your own admin rights.',
    ],
];
