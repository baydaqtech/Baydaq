<?php

return [
    'login' => [
        'title' => 'Sign in',
        'subtitle' => 'Sign in to Baydaq Platform to follow your projects and services.',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Remember me',
        'submit' => 'Sign in',
        'forgot' => 'Forgot your password?',
        'no_signup' => 'Accounts are created by the Baydaq team. To get one, send a project or service request.',
    ],

    'forgot' => [
        'title' => 'Forgot password',
        'subtitle' => 'Enter your email and we will send you a link to set a new password.',
        'submit' => 'Send link',
        'back_to_login' => 'Back to sign in',
    ],

    'reset' => [
        'title' => 'New password',
        'subtitle' => 'Choose a new password for your account.',
        'submit' => 'Save password',
    ],

    'change' => [
        'title' => 'Change your password',
        'subtitle' => 'To keep your account safe, choose a new password before you continue.',
        'submit' => 'Save and continue',
    ],

    'setup' => [
        'title' => 'Your account is ready',
        'subtitle' => 'Choose a password to start using Baydaq Platform.',
        'submit' => 'Activate my account',
        'invalid_link' => 'This activation link is invalid or has expired. Ask the Baydaq team for a new one.',
    ],

    'two_factor' => [
        'title' => 'Two-step sign-in',
        'subtitle' => 'After your password, sign-in also asks for a code from an app on your phone.',
        'status' => 'Two-step sign-in',
        'on' => 'On',
        'off' => 'Off',
        'pending' => 'Waiting for the first code',
        'explain' => 'Even if someone learns your password, they cannot sign in without your phone. Use any authenticator app: Google Authenticator, Microsoft Authenticator, 1Password…',
        'required_notice' => 'Your account can reach everything in the company, so it must use two-step sign-in. Turn it on to continue.',
        'turn_on' => 'Turn on',
        'step_install' => 'Open an authenticator app on your phone (or install one).',
        'step_scan' => 'Scan this code, or type the key below.',
        'step_code' => 'Enter the six-digit code the app shows.',
        'qr_alt' => 'QR code for the authenticator app',
        'copy_key' => 'Copy key',
        'code' => 'Six-digit code',
        'confirm' => 'Confirm and turn on',
        'cancel' => 'Cancel',
        'codes_explain' => 'Keep these recovery codes somewhere safe. Each one signs you in once if you lose your phone.',
        'copy_codes' => 'Copy codes',
        'copied' => 'Copied.',
        'show_codes' => 'Show recovery codes',
        'new_codes' => 'Make new codes',
        'new_codes_title' => 'Make new recovery codes?',
        'new_codes_explain' => 'The old codes stop working at once.',
        'continue' => 'Continue',
        'later' => 'Not now',
        'turn_off' => 'Turn off two-step sign-in',
        'turn_off_title' => 'Turn off two-step sign-in?',
        'turn_off_explain' => 'Your password alone will be enough to sign in again.',
    ],

    'challenge' => [
        'title' => 'Enter your code',
        'subtitle' => 'Open your authenticator app and enter the six-digit code for Baydaq.',
        'recovery_subtitle' => 'Enter one of the recovery codes you saved when you turned on two-step sign-in.',
        'code' => 'Six-digit code',
        'recovery_code' => 'Recovery code',
        'submit' => 'Sign in',
        'use_recovery' => 'Lost your phone? Use a recovery code',
        'use_code' => 'Use the code from the app',
    ],

    'confirm' => [
        'title' => 'Confirm your password',
        'subtitle' => 'For your security, enter your password again before you change how you sign in.',
        'submit' => 'Confirm',
    ],

    'notifications' => [
        'title' => 'Notifications',
        'subtitle' => 'Everything appears in the notification centre (the bell). Choose what also comes by email.',
        'email' => 'Email',
        'save' => 'Save',
        'saved' => 'Notification settings saved.',
        'back' => 'Back',
    ],

    'fields' => [
        'email' => 'Email',
        'password' => 'Password',
        'new_password' => 'New password',
        'password_confirmation' => 'Confirm password',
        'password_hint' => 'At least 8 characters, with letters and numbers.',
    ],

    'invite_mail' => [
        'subject' => 'Your Baydaq Platform account is ready',
        'greeting' => 'Hello :name,',
        'intro' => 'The Baydaq Tech team has created an account for you on Baydaq Platform, where you can follow your projects, services and invoices.',
        'action' => 'Choose your password',
        'expiry' => 'This link is valid for :days days.',
        'outro' => 'If you were not expecting this email, you can ignore it.',
    ],
];
