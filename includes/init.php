<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/api/bootstrap.php';
require_once __DIR__ . '/helpers.php';

// Ensure CSRF token exists for forms
csrfToken();

return $config;
