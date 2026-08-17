import { prisma } from '../../lib/prisma';
import { ValidationError } from '../../lib/errors';
import {
  maskToken,
  sendTelegramMessage,
  formatTestMessage,
} from '../../integrations/telegram';
import type { UpdateTelegramInput } from './schemas';
import { writeAuditLog } from '../auth/service';

async function getOrCreateSettings() {
  let settings = await prisma.telegramSettings.findFirst();
  if (!settings) {
    settings = await prisma.telegramSettings.create({
      data: {
        botToken: '',
        chatId: '',
        enabled: false,
        notifyOnDown: true,
        notifyOnUp: true,
      },
    });
  }
  return settings;
}

function sanitize(settings: Awaited<ReturnType<typeof getOrCreateSettings>>) {
  return {
    id: settings.id,
    botTokenMasked: maskToken(settings.botToken),
    hasBotToken: Boolean(settings.botToken),
    chatId: settings.chatId,
    enabled: settings.enabled,
    notifyOnDown: settings.notifyOnDown,
    notifyOnUp: settings.notifyOnUp,
    updatedAt: settings.updatedAt,
    createdAt: settings.createdAt,
  };
}

export async function getSettings() {
  const settings = await getOrCreateSettings();
  return sanitize(settings);
}

export async function updateSettings(
  input: UpdateTelegramInput,
  userId?: string,
  meta?: { ip?: string; userAgent?: string },
) {
  const existing = await getOrCreateSettings();

  const data: UpdateTelegramInput = { ...input };
  // Empty string botToken means "keep existing"
  if (data.botToken === '' || data.botToken === undefined) {
    delete data.botToken;
  }

  const updated = await prisma.telegramSettings.update({
    where: { id: existing.id },
    data,
  });

  await writeAuditLog({
    userId,
    action: 'telegram.update',
    entityType: 'TelegramSettings',
    entityId: updated.id,
    metadata: {
      enabled: updated.enabled,
      chatId: updated.chatId,
      tokenUpdated: Boolean(input.botToken),
    },
    ip: meta?.ip,
    userAgent: meta?.userAgent,
  });

  return sanitize(updated);
}

export async function sendTest(
  userId?: string,
  meta?: { ip?: string; userAgent?: string },
) {
  const settings = await getOrCreateSettings();
  if (!settings.botToken || !settings.chatId) {
    throw new ValidationError('Bot token and chat ID are required');
  }

  const result = await sendTelegramMessage(
    { botToken: settings.botToken, chatId: settings.chatId },
    formatTestMessage(),
  );

  await writeAuditLog({
    userId,
    action: 'telegram.test',
    entityType: 'TelegramSettings',
    entityId: settings.id,
    metadata: { ok: result.ok, error: result.error },
    ip: meta?.ip,
    userAgent: meta?.userAgent,
  });

  if (!result.ok) {
    throw new ValidationError(result.error ?? 'Failed to send Telegram message');
  }

  return { ok: true };
}
