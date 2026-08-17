<?php
declare(strict_types=1);

function baseUrl(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        // Prefer configured APP_URL path when available (production-safe).
        $configured = null;
        if (class_exists('Env', false)) {
            try {
                $appUrl = Env::getString('APP_URL', '');
                if ($appUrl !== '') {
                    $configured = rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?? ''), '/');
                }
            } catch (Throwable $e) {
                $configured = null;
            }
        }

        if (is_string($configured) && $configured !== '' && stripos($configured, 'Webmonitor') !== false) {
            $base = $configured;
        } else {
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/Webmonitor/index.php'));
            $dir = dirname($script);
            if (str_ends_with($dir, '/actions')) {
                $dir = dirname($dir);
            }
            $dir = rtrim($dir, '/');
            if ($dir === '' || $dir === '.') {
                $dir = '/Webmonitor';
            }
            if (stripos($dir, 'Webmonitor') === false) {
                $dir = '/Webmonitor';
            }
            $base = $dir;
        }
    }

    $path = ltrim($path, '/');
    if ($path === '') {
        return $base . '/';
    }
    return $base . '/' . $path;
}

/** @deprecated Use baseUrl() */
function url(string $path = ''): string
{
    return baseUrl($path);
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }
    header('Location: ' . baseUrl(ltrim($path, '/')));
    exit;
}

function flash(string $type, string $msg): void
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $msg];
}

/** @return list<array{type:string,message:string}> */
function getFlashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($flashes) ? $flashes : [];
}

/** @deprecated Use getFlashes() */
function get_flashes(): array
{
    return getFlashes();
}

function currentUser(): ?array
{
    return Auth::user();
}

/** @deprecated Use currentUser() */
function current_user(): ?array
{
    return currentUser();
}

function isAdmin(?array $user = null): bool
{
    $user ??= currentUser();
    return $user !== null && ($user['role'] ?? '') === 'ADMIN';
}

/** @deprecated Use isAdmin() */
function is_admin(?array $user = null): bool
{
    return isAdmin($user);
}

function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $user;
}

/** @deprecated Use requireLogin() */
function require_login(): array
{
    return requireLogin();
}

function requireAdmin(): array
{
    $user = requireLogin();
    if (!isAdmin($user)) {
        flash('error', 'Admin access required.');
        redirect('dashboard.php');
    }
    return $user;
}

/** @deprecated Use requireAdmin() */
function require_admin(): array
{
    return requireAdmin();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrfToken']) || !is_string($_SESSION['csrfToken'])) {
        return Csrf::issue();
    }
    return (string) $_SESSION['csrfToken'];
}

/** @deprecated Use csrfToken() */
function csrf_token(): string
{
    return csrfToken();
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

/** @deprecated Use csrfField() */
function csrf_field(): string
{
    return csrfField();
}

function verifyCsrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session = $_SESSION['csrfToken'] ?? '';
    if (
        !is_string($token) ||
        $token === '' ||
        !is_string($session) ||
        $session === '' ||
        !hash_equals($session, $token)
    ) {
        flash('error', 'Invalid CSRF token. Please try again.');
        $referer = $_SERVER['HTTP_REFERER'] ?? baseUrl('dashboard.php');
        header('Location: ' . $referer);
        exit;
    }
}

/** @deprecated Use verifyCsrf() */
function verify_csrf(): void
{
    verifyCsrf();
}

/** @return array{ip:?string,userAgent:?string} */
function requestMeta(): array
{
    return [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ];
}

/** @deprecated Use requestMeta() */
function request_meta(): array
{
    return requestMeta();
}

function formatDatetime(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($iso);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') . ' UTC';
    } catch (Throwable) {
        return $iso;
    }
}

/** @deprecated Use formatDatetime() */
function format_datetime(?string $iso): string
{
    return formatDatetime($iso);
}

function formatMs(?int $ms): string
{
    if ($ms === null) {
        return '—';
    }
    return number_format($ms) . ' ms';
}

/** @deprecated Use formatMs() */
function format_ms(?int $ms): string
{
    return formatMs($ms);
}

function formatPercent(?float $pct): string
{
    if ($pct === null) {
        return '—';
    }
    return number_format($pct, 2) . '%';
}

/** @deprecated Use formatPercent() */
function format_percent(?float $pct): string
{
    return formatPercent($pct);
}

function activeNav(string $page): string
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $script === $page ? ' is-active' : '';
}

/** @deprecated Use activeNav() */
function active_nav(string $page): string
{
    return activeNav($page);
}

function paginationUrl(array $query, int $page): string
{
    $query['page'] = $page;
    $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
    $base = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return baseUrl($base) . ($qs !== '' ? '?' . $qs : '');
}

/** @deprecated Use paginationUrl() */
function pagination_url(array $query, int $page): string
{
    return paginationUrl($query, $page);
}

function statusBadge(string $status): string
{
    $status = strtoupper($status);
    $labels = [
        'UP' => 'Up',
        'DOWN' => 'Down',
        'DEGRADED' => 'Degraded',
        'UNKNOWN' => 'Unknown',
    ];
    $label = $labels[$status] ?? $status;
    $class = 'badge badge-' . strtolower($status);
    return '<span class="' . e($class) . '">' . e($label) . '</span>';
}

/**
 * Render a 90-day history bar from PublicService history segments.
 *
 * @param list<array{date:string,segment:string}> $history
 */
function uptimeBarHtml(array $history, string $label = '90-day history'): string
{
    $html = '<div class="uptime-bar">';
    $html .= '<div class="uptime-bar-track" role="img" aria-label="' . e($label) . '">';
    foreach ($history as $day) {
        $seg = $day['segment'] ?? 'empty';
        $title = ($day['date'] ?? '') . ': ' . $seg;
        $html .= '<span class="uptime-seg uptime-seg-' . e($seg) . '" title="' . e($title) . '"></span>';
    }
    $html .= '</div>';
    $html .= '<div class="uptime-bar-legend"><span>' . count($history) . ' days ago</span><span>' . e($label) . '</span><span>Today</span></div>';
    $html .= '</div>';
    return $html;
}
