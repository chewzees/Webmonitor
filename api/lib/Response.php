<?php
declare(strict_types=1);

final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        mixed $details = null,
    ): void {
        $body = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if ($details !== null) {
            $body['error']['details'] = $details;
        }
        self::json($body, $status);
    }

    public static function text(string $body, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): void
    {
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        echo $body;
        exit;
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function iso(?string $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s.v\Z');
        } catch (Throwable) {
            try {
                $dt = new DateTimeImmutable($datetime);
                return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
            } catch (Throwable) {
                return $datetime;
            }
        }
    }

    public static function bool(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    public static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (int) $v;
    }
}
