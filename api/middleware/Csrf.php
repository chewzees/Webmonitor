<?php
declare(strict_types=1);

final class Csrf
{
    public const COOKIE = 'webmonitor.csrf';

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function issue(): string
    {
        $token = self::generateToken();
        $_SESSION['csrfToken'] = $token;

        $secure = Env::getBool('COOKIE_SECURE', false);
        setcookie(self::COOKIE, $token, [
            'expires' => time() + 7 * 24 * 60 * 60,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        return $token;
    }

    public static function clearCookie(): void
    {
        $secure = Env::getBool('COOKIE_SECURE', false);
        setcookie(self::COOKIE, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    public static function protect(Request $request): void
    {
        $method = $request->method();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $path = $request->path();
        if (
            $path === '/api/auth/login' ||
            str_starts_with($path, '/api/public') ||
            $path === '/api/health'
        ) {
            return;
        }

        if (Auth::user() === null) {
            return;
        }

        $headerToken = $request->header('x-csrf-token') ?? $request->header('csrf-token');
        $cookieToken = $_COOKIE[self::COOKIE] ?? null;
        $sessionToken = $_SESSION['csrfToken'] ?? null;
        $xrw = $request->header('x-requested-with');

        $doubleSubmitOk =
            $headerToken &&
            $cookieToken &&
            $sessionToken &&
            hash_equals((string) $cookieToken, (string) $headerToken) &&
            hash_equals((string) $sessionToken, (string) $headerToken);

        $spaHeaderOk = $xrw === 'XMLHttpRequest' || $xrw === 'fetch';

        if (!$doubleSubmitOk && !$spaHeaderOk) {
            throw new AppException('CSRF validation failed', 403, 'FORBIDDEN');
        }
    }
}
