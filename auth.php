<?php
/**
 * Shared authentication helpers for Aquarium Maintenance Tracker
 */

const AUTH_COOKIE_NAME = 'aquarium_auth';

function getBasePath($config = []) {
    $path = $config['BASE_PATH'] ?? getenv('AQUARIUM_BASE_PATH') ?: '';
    $path = trim((string) $path);
    if ($path === '' || $path === '/') {
        return '';
    }
    return '/' . trim($path, '/');
}

function urlPath($config, $path) {
    $path = (string) $path;
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    return getBasePath($config) . $path;
}

function cookiePath($config = []) {
    $basePath = getBasePath($config);
    return $basePath === '' ? '/' : $basePath . '/';
}

function serviceWorkerScope($config = []) {
    return cookiePath($config);
}

function isSecureRequest() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    return false;
}

function getAuthHmacSecret($config) {
    $secret = $config['AUTH_HMAC_SECRET'] ?? '';
    if (!is_string($secret) || $secret === '') {
        throw new RuntimeException('AUTH_HMAC_SECRET is not configured in env.php');
    }
    return $secret;
}

function getAuthToken($config) {
    return hash_hmac('sha256', $config['LOGIN_PASSWORD'], getAuthHmacSecret($config));
}

function getIncomingAuthToken() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = trim($_SERVER['HTTP_AUTHORIZATION']);
        if (stripos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return $header;
    }
    if (!empty($_SERVER['HTTP_X_APP_TOKEN'])) {
        return trim($_SERVER['HTTP_X_APP_TOKEN']);
    }
    if (!empty($_GET['auth_token'])) {
        return trim($_GET['auth_token']);
    }
    if (!empty($_COOKIE[AUTH_COOKIE_NAME])) {
        return trim($_COOKIE[AUTH_COOKIE_NAME]);
    }
    return null;
}

function isValidAuthToken($config, $token) {
    return is_string($token) && hash_equals(getAuthToken($config), $token);
}

function isAuthenticated($config) {
    $token = getIncomingAuthToken();
    return $token !== null && isValidAuthToken($config, $token);
}

function getAuthCookieDays($config) {
    return isset($config['AUTH_COOKIE_DAYS']) ? (int) $config['AUTH_COOKIE_DAYS'] : 90;
}

function setAuthCookie($token, $config = []) {
    $days = getAuthCookieDays($config);
    $maxAge = $days * 86400;
    $secure = isSecureRequest();
    $domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $path = cookiePath($config);

    if (PHP_VERSION_ID >= 70300) {
        setcookie(AUTH_COOKIE_NAME, $token, [
            'expires' => time() + $maxAge,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(
            AUTH_COOKIE_NAME,
            $token,
            time() + $maxAge,
            $path,
            $domain,
            $secure,
            true
        );
    }
}

function clearAuthCookie($config = []) {
    $secure = isSecureRequest();
    $domain = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $path = cookiePath($config);

    if (PHP_VERSION_ID >= 70300) {
        setcookie(AUTH_COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie(
            AUTH_COOKIE_NAME,
            '',
            time() - 3600,
            $path,
            $domain,
            $secure,
            true
        );
    }
}
