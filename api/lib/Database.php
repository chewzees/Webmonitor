<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::getString('DB_HOST', '127.0.0.1');
        $port = Env::getString('DB_PORT', '3306');
        $name = Env::getString('DB_NAME', 'webmonitor');
        $user = Env::getString('DB_USER', 'root');
        $pass = Env::getString('DB_PASS', '');

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
                'Database connection failed. Check api/.env (DB_HOST, DB_NAME, DB_USER, DB_PASS) and that schema.sql was imported. ' .
                'Underlying error: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
