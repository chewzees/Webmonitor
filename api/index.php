<?php
declare(strict_types=1);

/**
 * Front controller for /api/*
 */

$config = require __DIR__ . '/bootstrap.php';

// ---- CORS ----
$origins = $config['cors_origin'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Authorization');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Path parsing (supports /Webmonitor/api/... and /api/...) ----
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);

$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// e.g. /Webmonitor/api
$baseCandidates = array_unique(array_filter([
    $scriptName,
    rtrim($scriptName, '/'),
    preg_replace('#/api$#', '', $scriptName) . '/api',
]));

$path = $uri;
foreach ($baseCandidates as $base) {
    if ($base && $base !== '/' && str_starts_with($uri, $base)) {
        $path = substr($uri, strlen($base)) ?: '/';
        break;
    }
}

// Also strip project folder prefix if still present
if (preg_match('#^/(?:Webmonitor/)?api(/.*)?$#i', $uri, $m)) {
    $path = $m[1] ?? '/';
}

$path = '/' . ltrim($path, '/');
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

// Normalize to /api/... for route matching
$apiPath = str_starts_with($path, '/api') ? $path : '/api' . ($path === '/' ? '' : $path);
if ($apiPath === '/api') {
    $apiPath = '/api/health';
}

$request = Request::fromGlobals($apiPath);

try {
    Csrf::protect($request);

    $routes = require __DIR__ . '/routes.php';
    $matched = false;

    foreach ($routes as [$method, $pattern, $handler]) {
        if (strtoupper($method) !== $request->method()) {
            continue;
        }

        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $apiPath, $matches)) {
            continue;
        }

        $params = [];
        foreach ($matches as $k => $v) {
            if (!is_int($k)) {
                $params[$k] = $v;
            }
        }

        $matched = true;
        $handler($request->withParams($params));
        break;
    }

    if (!$matched) {
        Response::error('NOT_FOUND', 'Route not found', 404);
    }
} catch (AppException $e) {
    Response::error($e->errorCode, $e->getMessage(), $e->statusCode, $e->details);
} catch (Throwable $e) {
    $message = Env::getString('APP_DEBUG', 'false') === 'true'
        ? $e->getMessage()
        : 'Internal server error';
    Response::error('INTERNAL_ERROR', $message, 500);
}
