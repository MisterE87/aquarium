<?php
/**
 * Test environment configuration for API integration tests.
 * Used by tests/api_test.sh — no real secrets. Small comment for testing FTP upload pipeline
 */
return [
    'DB_HOST' => getenv('AQUARIUM_DB_HOST') ?: '127.0.0.1',
    'DB_NAME' => getenv('AQUARIUM_DB_NAME') ?: 'aquarium_test',
    'DB_USER' => getenv('AQUARIUM_DB_USER') ?: 'root',
    'DB_PASS' => getenv('AQUARIUM_DB_PASS') ?: '',
    'LOGIN_PASSWORD' => getenv('AQUARIUM_LOGIN_PASSWORD') ?: 'test-password',
    'AUTH_HMAC_SECRET' => getenv('AQUARIUM_AUTH_HMAC_SECRET') ?: 'test-hmac-secret',
    // URL prefix when app is hosted in a subdirectory (e.g. '/aquarium' on premium hosting).
    'BASE_PATH' => getenv('AQUARIUM_BASE_PATH') ?: '',
];
