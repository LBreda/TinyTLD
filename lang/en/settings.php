<?php

return [
    'title' => 'Settings',
    'description' => 'Manage your profile and account settings',
    'profile' => [
        'nav' => 'Profile',
        'title' => 'Profile settings',
        'description' => 'Update your name and email address',
        'updated' => 'Profile updated.',
        'unverified' => 'Your email address is unverified.',
        'resend_verification' => 'Click here to re-send the verification email.',
        'verification_sent' => 'A new verification link has been sent to your email address.',
    ],

    'appearance' => [
        'nav' => 'Appearance',
        'title' => 'Appearance settings',
        'description' => 'Update the appearance settings for your account',
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],

    'security' => [
        'nav' => 'Security',
        'title' => 'Security settings',
        'update_password' => 'Update password',
        'update_password_description' => 'Ensure your account is using a long, random password to stay secure',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'password_updated' => 'Password updated.',
    ],

    'delete_account' => [
        'title' => 'Delete account',
        'description' => 'Delete your account and all of its resources',
        'confirm_title' => 'Are you sure you want to delete your account?',
        'confirm_description' => 'Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
    ],
];
