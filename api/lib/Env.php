<?php
declare(strict_types=1);

final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path)) {
            throw new RuntimeException("Env file not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Unable to read env file: {$path}");
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            self::$vars[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return $default;
    }

    public static function getString(string $key, string $default = ''): string
    {
        return (string) (self::get($key, $default) ?? $default);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return (int) $v;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return list<string> */
    public static function getList(string $key, string $default = ''): array
    {
        $raw = self::getString($key, $default);
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($s) => $s !== ''));
    }
}
