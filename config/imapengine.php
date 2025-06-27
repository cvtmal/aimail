<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mailbox
    |--------------------------------------------------------------------------
    |
    | The default mailbox to use when one is not specified.
    |
    */
    'default' => env('IMAPENGINE_DEFAULT_ACCOUNT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Mailboxes
    |--------------------------------------------------------------------------
    |
    | Configure the mailboxes you want to use in your application.
    |
    */
    'mailboxes' => [
        'default' => [
            'host' => env('IMAPENGINE_HOST', env('IMAP_HOST', 'localhost')),
            'port' => env('IMAPENGINE_PORT', env('IMAP_PORT', 993)),
            'username' => env('IMAPENGINE_USERNAME', env('IMAP_USERNAME', 'user@example.com')),
            'password' => env('IMAPENGINE_PASSWORD', env('IMAP_PASSWORD', '')),
            'encryption' => env('IMAPENGINE_ENCRYPTION', env('IMAP_ENCRYPTION', 'ssl')),
        ],

        'damian' => [
            'host' => env('IMAPENGINE_DAMIAN_HOST', env('IMAP_DAMIAN_HOST', 'mail.cyon.ch')),
            'port' => env('IMAPENGINE_DAMIAN_PORT', env('IMAP_DAMIAN_PORT', 993)),
            'username' => env('IMAPENGINE_DAMIAN_USERNAME', env('IMAP_DAMIAN_USERNAME', 'damian.ermanni@myitjob.ch')),
            'password' => env('IMAPENGINE_DAMIAN_PASSWORD', env('IMAP_DAMIAN_PASSWORD')),
            'encryption' => env('IMAPENGINE_DAMIAN_ENCRYPTION', env('IMAP_DAMIAN_ENCRYPTION', 'ssl')),
        ],

        'info' => [
            'host' => env('IMAPENGINE_INFO_HOST', env('IMAP_INFO_HOST', 'mail.cyon.ch')),
            'port' => env('IMAPENGINE_INFO_PORT', env('IMAP_INFO_PORT', 993)),
            'username' => env('IMAPENGINE_INFO_USERNAME', env('IMAP_INFO_USERNAME', 'info@myitjob.ch')),
            'password' => env('IMAPENGINE_INFO_PASSWORD', env('IMAP_INFO_PASSWORD')),
            'encryption' => env('IMAPENGINE_INFO_ENCRYPTION', env('IMAP_INFO_ENCRYPTION', 'ssl')),
        ],
    ],
];
