<?php
/**
 * Example environment configuration.
 * Copy to env.php and fill in real values. env.php is gitignored.
 */

// Ensure a consistent server timezone for date calculations
date_default_timezone_set('Europe/Amsterdam');

return [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => 'your_mysql_password',
    'DB_NAME' => 'aquarium',

    // Login password for the dashboard / API
    'LOGIN_PASSWORD' => 'change-me',

    // Secret used to sign auth tokens (use a long random string)
    'AUTH_HMAC_SECRET' => 'change-me-to-a-long-random-string',

    // Auth cookie lifetime in days (PWA / mobile login persistence)
    'AUTH_COOKIE_DAYS' => 90,

    // URL prefix when app is hosted in a subdirectory (leave empty for domain root)
    // 'BASE_PATH' => '/aquarium',
];
