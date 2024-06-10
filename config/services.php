<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'import' => [
        'enabled' => env('IMPORT_ENABLED', false),
        'cron' => env('IMPORT_SCHEDULE', '*/5 * * * *'),
        'local_presence' => [
            'url' => env('IMPORT_LOCAL_PRESENCE_URL', 'https://prezenta.roaep.ro/locale09062024/data/json/simpv/presence/presence_{short_county}_now.json'),
        ],
        'europarl' => [
            'url' => env('IMPORT_EUROPARL_URL', 'https://prezenta.roaep.ro/presa/pv/EUP-20240609/pv_part_cnty_eup_{code}.csv'),
            'username' => env('IMPORT_EUROPARL_USERNAME'),
            'password' => env('IMPORT_EUROPARL_PASSWORD'),
        ],
    ],
];
