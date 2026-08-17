<?php
declare(strict_types=1);

final class AuthService
{
    private const BCRYPT_COST = 12;

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /** @return array{id:string,email:string,name:string,role:string} */
    public static function authenticate(string $email, string $password): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !self::verifyPassword($password, $user['password_hash'])) {
            throw new AppException('Invalid email or password', 401, 'UNAUTHORIZED');
        }

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];
    }

    /** @return array{id:string,email:string,name:string,role:string}|null */
    public static function getUserById(string $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, email, name, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
