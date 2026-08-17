<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $fromEnv = rtrim((string) (parse_url(Env::get('APP_URL', ''), PHP_URL_PATH) ?? ''), '/');
    if ($fromEnv !== '' && stripos($fromEnv, 'Webmonitor') !== false) {
        return $base = $fromEnv;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/Webmonitor/index.php'));
    $dir = rtrim(dirname($script), '/');
    if ($dir === '' || $dir === '.' || stripos($dir, 'Webmonitor') === false) {
        $dir = '/Webmonitor';
    }
    return $base = $dir;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? base_path() . '/' : base_path() . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : url($path)));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($f) ? $f : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($token === '' || empty($_SESSION['_csrf']) || !hash_equals((string) $_SESSION['_csrf'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function cuid(): string
{
    return 'c' . bin2hex(random_bytes(12));
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'site';
}

function format_ms(?int $ms): string
{
    return $ms === null ? '—' : number_format($ms) . ' ms';
}

function format_pct(?float $pct): string
{
    return $pct === null ? '—' : number_format($pct, 2) . '%';
}

function format_dt(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($dt))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') . ' UTC';
    } catch (Throwable) {
        return $dt;
    }
}

function status_badge(string $status): string
{
    $status = strtoupper($status);
    $labels = ['UP' => 'Up', 'DOWN' => 'Down', 'DEGRADED' => 'Degraded', 'UNKNOWN' => 'Unknown'];
    $label = $labels[$status] ?? $status;
    return '<span class="badge badge-' . e(strtolower($status)) . '">' . e($label) . '</span>';
}

function is_active(string $page): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $page ? ' is-active' : '';
}
