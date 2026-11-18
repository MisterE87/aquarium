<?php
/**
 * PHP built-in server router for local dev.
 * Usage: php -S localhost:8000 router.php
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/manifest.json') {
    require __DIR__ . '/manifest.php';
    return true;
}

return false;
