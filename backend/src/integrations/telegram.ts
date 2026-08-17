import { MonitorStatus, Website } from '@prisma/client';

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

export interface TelegramConfig {
  botToken: string;
  chatId: string;
}

export async function sendTelegramMessage(
  config: TelegramConfig,
  text: string,
  parseMode: 'HTML' | 'Markdown' = 'HTML',
): Promise<{ ok: boolean; error?: string }> {
  if (!config.botToken || !config.chatId) {
    return { ok: false, error: 'Bot token or chat ID not configured' };
  }

  const url = `https://api.telegram.org/bot${config.botToken}/sendMessage`;

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: config.chatId,
        text,
        parse_mode: parseMode,
        disable_web_page_preview: true,
      }),
    });

    const data = (await res.json()) as { ok: boolean; description?: string };
    if (!data.ok) {
      return { ok: false, error: data.description ?? `HTTP ${res.status}` };
    }
    return { ok: true };
  } catch (err) {
    return {
      ok: false,
      error: err instanceof Error ? err.message : 'Telegram request failed',
    };
  }
}

export function formatDownMessage(
  website: Pick<Website, 'name' | 'url' | 'currentStatus'>,
  errorMessage: string | null,
): string {
  return [
    `🔴 <b>DOWN</b>: ${escapeHtml(website.name)}`,
    `URL: ${escapeHtml(website.url)}`,
    errorMessage ? `Error: ${escapeHtml(errorMessage)}` : null,
    `Time: ${new Date().toISOString()}`,
  ]
    .filter(Boolean)
    .join('\n');
}

export function formatUpMessage(
  website: Pick<Website, 'name' | 'url'>,
  previousStatus: MonitorStatus,
  responseMs: number | null,
): string {
  return [
    `🟢 <b>RECOVERED</b>: ${escapeHtml(website.name)}`,
    `URL: ${escapeHtml(website.url)}`,
    `Was: ${previousStatus}`,
    responseMs != null ? `Response: ${responseMs}ms` : null,
    `Time: ${new Date().toISOString()}`,
  ]
    .filter(Boolean)
    .join('\n');
}

export function formatDegradedMessage(
  website: Pick<Website, 'name' | 'url'>,
  responseMs: number | null,
): string {
  return [
    `🟡 <b>DEGRADED</b>: ${escapeHtml(website.name)}`,
    `URL: ${escapeHtml(website.url)}`,
    responseMs != null ? `Response: ${responseMs}ms` : null,
    `Time: ${new Date().toISOString()}`,
  ]
    .filter(Boolean)
    .join('\n');
}

export function formatTestMessage(): string {
  return `✅ <b>WebMonitor test</b>\nTelegram notifications are working.\n${new Date().toISOString()}`;
}

export function maskToken(token: string): string {
  if (!token) return '';
  if (token.length <= 4) return '****';
  return `${'*'.repeat(Math.max(token.length - 4, 4))}${token.slice(-4)}`;
}
