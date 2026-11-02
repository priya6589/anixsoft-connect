<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Claude API
    |--------------------------------------------------------------------------
    |
    | Key from https://console.anthropic.com. Haiku 5.5 is the cheapest model
    | and is plenty for sorting contacts and writing short outreach emails.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),

    /*
    |--------------------------------------------------------------------------
    | Outreach settings
    |--------------------------------------------------------------------------
    |
    | "services" drives both the AI sorting and the email drafts, so describe
    | what the company actually sells.
    |
    */

    'company_name' => env('OUTREACH_COMPANY', 'Anixsoft'),

    'sender_name' => env('OUTREACH_SENDER_NAME', 'The Anixsoft Team'),

    'services' => env('OUTREACH_SERVICES', 'custom web development (Laravel/PHP), web applications, mobile apps, and ongoing maintenance and support'),

    // Emails drafted (and later sent) per day.
    'daily_limit' => (int) env('OUTREACH_DAILY_LIMIT', 10),

    // Contacts at or above this AI score (and not "not relevant") become qualified.
    'min_score' => (int) env('OUTREACH_MIN_SCORE', 50),

    /*
    |--------------------------------------------------------------------------
    | Hunter.io (finding and verifying missing emails)
    |--------------------------------------------------------------------------
    |
    | Key from https://hunter.io (Account > API). Only emails Hunter verifies as
    | "valid" are saved. The free plan has a small monthly quota, so lookups
    | are capped per day.
    |
    */

    'hunter_key' => env('HUNTER_API_KEY'),

    'lookup_daily_limit' => (int) env('OUTREACH_LOOKUP_DAILY_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Replies (IMAP)
    |--------------------------------------------------------------------------
    |
    | The mailbox the emails are sent from is checked for replies. Username and
    | password default to the SMTP ones (MAIL_USERNAME / MAIL_PASSWORD).
    |
    */

    'imap' => [
        'host' => env('IMAP_HOST'),
        'port' => (int) env('IMAP_PORT', 993),
        'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
        'username' => env('IMAP_USERNAME', env('MAIL_USERNAME')),
        'password' => env('IMAP_PASSWORD', env('MAIL_PASSWORD')),
    ],

    // Login created by `php artisan db:seed` (AdminUserSeeder). Keep the password in .env only.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Contacts sent to Claude per sorting request.
    'classify_chunk' => 25,

];
