<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            throw new AppException('Not authenticated', 401, 'UNAUTHORIZED');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if (($user['role'] ?? '') !== 'ADMIN') {
            throw new AppException('Admin access required', 403, 'FORBIDDEN');
        }
        return $user;
    }

    public static function setUser(array $user): void
    {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'] ?? 'ADMIN',
        ];
    }

    public static function clear(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
