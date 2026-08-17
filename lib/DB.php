<?php
declare(strict_types=1);

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', 'webmonitor');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed. Edit api/.env (DB_HOST, DB_NAME, DB_USER, DB_PASS). ' . $e->getMessage(),
                0,
                $e
            );
        }

        return self::$pdo;
    }

    public static function ok(): bool
    {
        try {
            self::pdo()->query('SELECT 1');
            return true;
        } catch (Throwable) {
            self::$pdo = null;
            return false;
        }
    }
}
