<?php
declare(strict_types=1);

final class TelegramService
{
    public static function maskToken(string $token): string
    {
        if ($token === '') {
            return '';
        }
        if (strlen($token) <= 4) {
            return '****';
        }
        return str_repeat('*', max(strlen($token) - 4, 4)) . substr($token, -4);
    }

    private static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<string, mixed> */
    public static function getOrCreateSettings(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM telegram_settings ORDER BY created_at ASC LIMIT 1');
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $id = Cuid::generate();
        $insert = $pdo->prepare(
            'INSERT INTO telegram_settings (id, bot_token, chat_id, enabled, notify_on_down, notify_on_up, created_at, updated_at)
             VALUES (:id, \'\', \'\', 0, 1, 1, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))'
        );
        $insert->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT * FROM telegram_settings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    /** @param array<string, mixed> $settings */
    public static function sanitize(array $settings): array
    {
        return [
            'id' => $settings['id'],
            'botTokenMasked' => self::maskToken((string) ($settings['bot_token'] ?? '')),
            'hasBotToken' => !empty($settings['bot_token']),
            'chatId' => (string) ($settings['chat_id'] ?? ''),
            'enabled' => Response::bool($settings['enabled'] ?? false),
            'notifyOnDown' => Response::bool($settings['notify_on_down'] ?? true),
            'notifyOnUp' => Response::bool($settings['notify_on_up'] ?? true),
            'updatedAt' => Response::iso($settings['updated_at'] ?? null),
            'createdAt' => Response::iso($settings['created_at'] ?? null),
        ];
    }

    public static function getSettings(): array
    {
        return self::sanitize(self::getOrCreateSettings());
    }

    /** @param array<string, mixed> $input */
    public static function updateSettings(array $input, ?string $userId = null, ?array $meta = null): array
    {
        $existing = self::getOrCreateSettings();
        $pdo = Database::connection();

        $fields = [];
        $params = ['id' => $existing['id']];

        if (array_key_exists('botToken', $input) && $input['botToken'] !== '' && $input['botToken'] !== null) {
            $fields[] = 'bot_token = :bot_token';
            $params['bot_token'] = (string) $input['botToken'];
        }
        if (array_key_exists('chatId', $input)) {
            $fields[] = 'chat_id = :chat_id';
            $params['chat_id'] = (string) $input['chatId'];
        }
        if (array_key_exists('enabled', $input)) {
            $fields[] = 'enabled = :enabled';
            $params['enabled'] = $input['enabled'] ? 1 : 0;
        }
        if (array_key_exists('notifyOnDown', $input)) {
            $fields[] = 'notify_on_down = :notify_on_down';
            $params['notify_on_down'] = $input['notifyOnDown'] ? 1 : 0;
        }
        if (array_key_exists('notifyOnUp', $input)) {
            $fields[] = 'notify_on_up = :notify_on_up';
            $params['notify_on_up'] = $input['notifyOnUp'] ? 1 : 0;
        }

        if ($fields !== []) {
            $fields[] = 'updated_at = UTC_TIMESTAMP(3)';
            $sql = 'UPDATE telegram_settings SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $pdo->prepare($sql)->execute($params);
        }

        $stmt = $pdo->prepare('SELECT * FROM telegram_settings WHERE id = :id');
        $stmt->execute(['id' => $existing['id']]);
        $updated = $stmt->fetch() ?: $existing;

        AuditService::write([
            'userId' => $userId,
            'action' => 'telegram.update',
            'entityType' => 'TelegramSettings',
            'entityId' => $updated['id'],
            'metadata' => [
                'enabled' => Response::bool($updated['enabled']),
                'chatId' => $updated['chat_id'],
                'tokenUpdated' => !empty($input['botToken']),
            ],
            'ip' => $meta['ip'] ?? null,
            'userAgent' => $meta['userAgent'] ?? null,
        ]);

        return self::sanitize($updated);
    }

    public static function sendTest(?string $userId = null, ?array $meta = null): array
    {
        $settings = self::getOrCreateSettings();
        if (empty($settings['bot_token']) || empty($settings['chat_id'])) {
            throw new AppException('Bot token and chat ID are required', 400, 'VALIDATION_ERROR');
        }

        $result = self::sendMessage(
            (string) $settings['bot_token'],
            (string) $settings['chat_id'],
            self::formatTestMessage(),
        );

        AuditService::write([
            'userId' => $userId,
            'action' => 'telegram.test',
            'entityType' => 'TelegramSettings',
            'entityId' => $settings['id'],
            'metadata' => ['ok' => $result['ok'], 'error' => $result['error'] ?? null],
            'ip' => $meta['ip'] ?? null,
            'userAgent' => $meta['userAgent'] ?? null,
        ]);

        if (!$result['ok']) {
            throw new AppException($result['error'] ?? 'Failed to send Telegram message', 400, 'VALIDATION_ERROR');
        }

        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function sendMessage(string $botToken, string $chatId, string $text): array
    {
        if ($botToken === '' || $chatId === '') {
            return ['ok' => false, 'error' => 'Bot token or chat ID not configured'];
        }

        $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
        $payload = json_encode([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                ]);
                $raw = curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);
                if ($raw === false) {
                    return ['ok' => false, 'error' => $err ?: 'Telegram request failed'];
                }
            } else {
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $payload,
                        'timeout' => 15,
                        'ignore_errors' => true,
                    ],
                ]);
                $raw = file_get_contents($url, false, $ctx);
                if ($raw === false) {
                    return ['ok' => false, 'error' => 'Telegram request failed'];
                }
            }

            $data = json_decode((string) $raw, true);
            if (!is_array($data) || empty($data['ok'])) {
                return ['ok' => false, 'error' => $data['description'] ?? 'Telegram API error'];
            }
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string, mixed> $website */
    public static function formatDownMessage(array $website, ?string $errorMessage): string
    {
        $lines = [
            '🔴 <b>DOWN</b>: ' . self::escapeHtml((string) $website['name']),
            'URL: ' . self::escapeHtml((string) $website['url']),
        ];
        if ($errorMessage) {
            $lines[] = 'Error: ' . self::escapeHtml($errorMessage);
        }
        $lines[] = 'Time: ' . gmdate('c');
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $website */
    public static function formatUpMessage(array $website, string $previousStatus, ?int $responseMs): string
    {
        $lines = [
            '🟢 <b>RECOVERED</b>: ' . self::escapeHtml((string) $website['name']),
            'URL: ' . self::escapeHtml((string) $website['url']),
            'Was: ' . $previousStatus,
        ];
        if ($responseMs !== null) {
            $lines[] = 'Response: ' . $responseMs . 'ms';
        }
        $lines[] = 'Time: ' . gmdate('c');
        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $website */
    public static function formatDegradedMessage(array $website, ?int $responseMs): string
    {
        $lines = [
            '🟡 <b>DEGRADED</b>: ' . self::escapeHtml((string) $website['name']),
            'URL: ' . self::escapeHtml((string) $website['url']),
        ];
        if ($responseMs !== null) {
            $lines[] = 'Response: ' . $responseMs . 'ms';
        }
        $lines[] = 'Time: ' . gmdate('c');
        return implode("\n", $lines);
    }

    public static function formatTestMessage(): string
    {
        return "✅ <b>WebMonitor test</b>\nTelegram notifications are working.\n" . gmdate('c');
    }

    /**
     * @param array<string, mixed> $website
     * @param array{status:string,responseMs:?int,errorMessage:?string} $result
     */
    public static function notifyStatusChange(array $website, string $previous, array $result): void
    {
        try {
            $settings = self::getOrCreateSettings();
            if (
                !Response::bool($settings['enabled'] ?? false) ||
                empty($settings['bot_token']) ||
                empty($settings['chat_id'])
            ) {
                return;
            }

            $message = null;
            $status = $result['status'];
            $notifyDown = Response::bool($settings['notify_on_down'] ?? true);
            $notifyUp = Response::bool($settings['notify_on_up'] ?? true);

            if ($status === 'DOWN' && $previous !== 'DOWN' && $notifyDown) {
                $message = self::formatDownMessage($website, $result['errorMessage'] ?? null);
            } elseif ($status === 'DEGRADED' && $previous !== 'DEGRADED' && $notifyDown) {
                $message = self::formatDegradedMessage($website, $result['responseMs'] ?? null);
            } elseif (
                ($status === 'UP' || $status === 'DEGRADED') &&
                $previous === 'DOWN' &&
                $notifyUp
            ) {
                $message = self::formatUpMessage($website, $previous, $result['responseMs'] ?? null);
            } elseif ($status === 'UP' && $previous === 'DEGRADED' && $notifyUp) {
                $message = self::formatUpMessage($website, $previous, $result['responseMs'] ?? null);
            }

            if ($message) {
                self::sendMessage((string) $settings['bot_token'], (string) $settings['chat_id'], $message);
            }
        } catch (Throwable) {
            // swallow notification errors
        }
    }
}
