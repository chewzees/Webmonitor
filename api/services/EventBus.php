<?php
declare(strict_types=1);

/**
 * Simple file-based event bus for SSE (JSON lines).
 */
final class EventBus
{
    private static function file(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/events';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/events.jsonl';
    }

    /** @param array<string, mixed> $payload */
    public static function publish(string $type, array $payload = []): void
    {
        $event = array_merge(['type' => $type, 'at' => gmdate('c')], $payload);
        $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents(self::file(), $line, FILE_APPEND | LOCK_EX);

        // Keep file small
        clearstatcache(true, self::file());
        if (is_file(self::file()) && filesize(self::file()) > 512_000) {
            $lines = file(self::file(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $keep = array_slice($lines, -200);
            file_put_contents(self::file(), implode("\n", $keep) . "\n", LOCK_EX);
        }
    }

    /**
     * Read events newer than offset (byte offset into file).
     * @return array{events: list<array<string,mixed>>, offset: int}
     */
    public static function readSince(int $offset): array
    {
        $file = self::file();
        if (!is_file($file)) {
            return ['events' => [], 'offset' => 0];
        }

        $size = filesize($file) ?: 0;
        if ($offset > $size) {
            $offset = 0;
        }

        $fh = fopen($file, 'rb');
        if ($fh === false) {
            return ['events' => [], 'offset' => $size];
        }

        fseek($fh, $offset);
        $chunk = stream_get_contents($fh);
        $newOffset = ftell($fh) ?: $size;
        fclose($fh);

        $events = [];
        if ($chunk !== false && $chunk !== '') {
            foreach (explode("\n", $chunk) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $events[] = $decoded;
                }
            }
        }

        return ['events' => $events, 'offset' => $newOffset];
    }

    public static function currentOffset(): int
    {
        $file = self::file();
        return is_file($file) ? (int) filesize($file) : 0;
    }
}
