<?php

return [
    'title' => 'Two-factor authentication',
    'manage' => 'Manage your two-factor authentication settings',
    'enable' => 'Enable 2FA',
    'disable' => 'Disable 2FA',
    'enabled_description' => 'You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.',
    'disabled_description' => 'When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.',
    'otp_code' => 'OTP Code',
    'setup' => [
        'enable_title' => 'Enable two-factor authentication',
        'enable_description' => 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.',
        'enabled_title' => 'Two-factor authentication enabled',
        'enabled_description' => 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
        'verify_title' => 'Verify authentication code',
        'verify_description' => 'Enter the 6-digit code from your authenticator app.',
        'manual_entry' => 'or, enter the code manually',
    ],

    'challenge' => [
        'authentication_code' => 'Authentication code',
        'authentication_code_description' => 'Enter the authentication code provided by your authenticator application.',
        'recovery_code' => 'Recovery code',
        'recovery_code_description' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
        'or_you_can' => 'or you can',
        'use_recovery_code' => 'login using a recovery code',
        'use_authentication_code' => 'login using an authentication code',
    ],

    'recovery_codes' => [
        'title' => '2FA recovery codes',
        'label' => 'Recovery codes',
        'description' => 'Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.',
        'view' => 'View recovery codes',
        'hide' => 'Hide recovery codes',
        'regenerate' => 'Regenerate codes',
        'usage' => 'Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.',
    ],
];
