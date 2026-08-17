<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        $u = $_SESSION['user'] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user ??= self::user();
        return $user !== null && ($user['role'] ?? '') === 'ADMIN';
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'] ?? 'USER',
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'] ?? '',
                'secure' => (bool) $p['secure'],
                'httponly' => (bool) $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function attempt(string $email, string $password): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            flash('error', 'Please sign in.');
            redirect('login.php');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (!self::isAdmin($user)) {
            flash('error', 'Admin access required.');
            redirect('dashboard.php');
        }
        return $user;
    }
}
