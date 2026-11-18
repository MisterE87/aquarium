<?php
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$manifestPath = __DIR__ . '/manifest.json';
$manifest = json_decode(file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    http_response_code(500);
    exit;
}

$config = [];
if (is_readable(__DIR__ . '/env.php')) {
    $config = require __DIR__ . '/env.php';
}
require_once __DIR__ . '/auth.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$isSecure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
$scheme = $isSecure ? 'https' : 'http';
$base = $scheme . '://' . $host;

$toAbsolute = function ($path) use ($base) {
    if (!is_string($path) || $path === '') {
        return $path;
    }
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    return $base . (strpos($path, '/') === 0 ? $path : '/' . $path);
};

$manifest['id'] = $toAbsolute(urlPath($config, $manifest['id'] ?? '/index.php'));
$manifest['start_url'] = $toAbsolute(urlPath($config, $manifest['start_url'] ?? '/index.php'));
if (!empty($manifest['scope'])) {
    $manifest['scope'] = $toAbsolute(urlPath($config, $manifest['scope']));
}

if (!empty($manifest['icons']) && is_array($manifest['icons'])) {
    foreach ($manifest['icons'] as &$icon) {
        if (empty($icon['src'])) {
            continue;
        }

        $icon['src'] = $toAbsolute(urlPath($config, $icon['src']));
    }
    unset($icon);
}

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
