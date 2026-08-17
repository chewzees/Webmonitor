<?php
declare(strict_types=1);

final class EventsController
{
    public static function stream(Request $request): void
    {
        Auth::requireUser();

        // Disable buffering for SSE
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $send = static function (array $event): void {
            $type = $event['type'] ?? 'message';
            echo 'event: ' . $type . "\n";
            echo 'data: ' . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            if (function_exists('flush')) {
                flush();
            }
        };

        $send(['type' => 'connected', 'at' => gmdate('c')]);

        $offset = EventBus::currentOffset();
        $started = time();
        $lastHeartbeat = time();
        $maxSeconds = 55; // stay under typical PHP/Apache timeouts; client reconnects

        while (!connection_aborted() && (time() - $started) < $maxSeconds) {
            $batch = EventBus::readSince($offset);
            $offset = $batch['offset'];
            foreach ($batch['events'] as $event) {
                $send($event);
            }

            if (time() - $lastHeartbeat >= 25) {
                echo ': heartbeat ' . gmdate('c') . "\n\n";
                if (function_exists('flush')) {
                    flush();
                }
                $lastHeartbeat = time();
            }

            usleep(500_000);
        }

        exit;
    }
}
