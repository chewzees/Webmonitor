import { MonitorStatus, Website } from '@prisma/client';
import { prisma } from '../lib/prisma';
import { logger } from '../lib/logger';
import { env } from '../config/env';
import { checkWebsite, CheckResult } from './checker';
import { eventBus } from '../modules/events/bus';
import {
  sendTelegramMessage,
  formatDownMessage,
  formatUpMessage,
  formatDegradedMessage,
} from '../integrations/telegram';

const CONCURRENCY = 10;
let tickTimer: NodeJS.Timeout | null = null;
let ticking = false;

async function withConcurrency<T, R>(
  items: T[],
  limit: number,
  fn: (item: T) => Promise<R>,
): Promise<R[]> {
  const results: R[] = [];
  let index = 0;

  async function worker(): Promise<void> {
    while (index < items.length) {
      const current = index++;
      results[current] = await fn(items[current]);
    }
  }

  const workers = Array.from({ length: Math.min(limit, items.length) }, () =>
    worker(),
  );
  await Promise.all(workers);
  return results;
}

async function notifyStatusChange(
  website: Website,
  previous: MonitorStatus,
  result: CheckResult,
): Promise<void> {
  try {
    const settings = await prisma.telegramSettings.findFirst();
    if (!settings?.enabled || !settings.botToken || !settings.chatId) return;

    const config = { botToken: settings.botToken, chatId: settings.chatId };
    let message: string | null = null;

    if (
      result.status === MonitorStatus.DOWN &&
      previous !== MonitorStatus.DOWN &&
      settings.notifyOnDown
    ) {
      message = formatDownMessage(website, result.errorMessage);
    } else if (
      result.status === MonitorStatus.DEGRADED &&
      previous !== MonitorStatus.DEGRADED &&
      settings.notifyOnDown
    ) {
      message = formatDegradedMessage(website, result.responseMs);
    } else if (
      (result.status === MonitorStatus.UP ||
        result.status === MonitorStatus.DEGRADED) &&
      (previous === MonitorStatus.DOWN) &&
      settings.notifyOnUp
    ) {
      message = formatUpMessage(website, previous, result.responseMs);
    } else if (
      result.status === MonitorStatus.UP &&
      previous === MonitorStatus.DEGRADED &&
      settings.notifyOnUp
    ) {
      message = formatUpMessage(website, previous, result.responseMs);
    }

    if (message) {
      const sendResult = await sendTelegramMessage(config, message);
      if (!sendResult.ok) {
        logger.warn(
          { error: sendResult.error, websiteId: website.id },
          'Telegram notify failed',
        );
      }
    }
  } catch (err) {
    logger.error({ err, websiteId: website.id }, 'Telegram notify error');
  }
}

async function handleIncidents(
  websiteId: string,
  previous: MonitorStatus,
  next: MonitorStatus,
  errorMessage: string | null,
): Promise<void> {
  const isBad = (s: MonitorStatus) =>
    s === MonitorStatus.DOWN || s === MonitorStatus.DEGRADED;
  const isGood = (s: MonitorStatus) =>
    s === MonitorStatus.UP || s === MonitorStatus.UNKNOWN;

  if (isBad(next) && !isBad(previous)) {
    await prisma.incident.create({
      data: {
        websiteId,
        status: next,
        summary: errorMessage ?? `Status changed to ${next}`,
      },
    });
  } else if (isGood(next) && isBad(previous)) {
    await prisma.incident.updateMany({
      where: { websiteId, resolvedAt: null },
      data: { resolvedAt: new Date() },
    });
  } else if (isBad(next) && isBad(previous) && next !== previous) {
    await prisma.incident.updateMany({
      where: { websiteId, resolvedAt: null },
      data: { resolvedAt: new Date() },
    });
    await prisma.incident.create({
      data: {
        websiteId,
        status: next,
        summary: errorMessage ?? `Status changed to ${next}`,
      },
    });
  }
}

export async function processCheckResult(
  website: Website,
  result: CheckResult,
): Promise<Website> {
  const previous = website.currentStatus;
  const statusChanged = previous !== result.status;

  await prisma.monitorLog.create({
    data: {
      websiteId: website.id,
      status: result.status,
      statusCode: result.statusCode,
      responseMs: result.responseMs,
      errorMessage: result.errorMessage,
    },
  });

  const updated = await prisma.website.update({
    where: { id: website.id },
    data: {
      currentStatus: result.status,
      lastCheckedAt: new Date(),
      lastResponseMs: result.responseMs,
      lastStatusCode: result.statusCode,
      lastError: result.errorMessage,
    },
  });

  if (statusChanged) {
    await handleIncidents(
      website.id,
      previous,
      result.status,
      result.errorMessage,
    );
    eventBus.publish('status.changed', {
      websiteId: website.id,
      name: website.name,
      slug: website.slug,
      from: previous,
      to: result.status,
    });
    await notifyStatusChange(website, previous, result);
  }

  eventBus.publish('check.completed', {
    websiteId: website.id,
    name: website.name,
    status: result.status,
    statusCode: result.statusCode,
    responseMs: result.responseMs,
    errorMessage: result.errorMessage,
  });

  return updated;
}

export async function runCheckForWebsite(website: Website): Promise<Website> {
  const result = await checkWebsite({
    id: website.id,
    url: website.url,
    method: website.method,
    timeoutMs: website.timeoutMs,
    expectedStatus: website.expectedStatus,
    keyword: website.keyword,
    headersJson: website.headersJson,
  });
  return processCheckResult(website, result);
}

export async function getDueWebsites(): Promise<Website[]> {
  const now = new Date();
  const active = await prisma.website.findMany({
    where: { isActive: true },
  });

  return active.filter((w) => {
    if (!w.lastCheckedAt) return true;
    const dueAt = w.lastCheckedAt.getTime() + w.intervalSeconds * 1000;
    return dueAt <= now.getTime();
  });
}

export async function runDueChecks(): Promise<number> {
  const due = await getDueWebsites();
  if (due.length === 0) return 0;

  await withConcurrency(due, CONCURRENCY, async (website) => {
    try {
      await runCheckForWebsite(website);
    } catch (err) {
      logger.error({ err, websiteId: website.id }, 'Check failed unexpectedly');
    }
  });

  return due.length;
}

export async function runAllActiveChecks(): Promise<number> {
  const active = await prisma.website.findMany({ where: { isActive: true } });
  await withConcurrency(active, CONCURRENCY, async (website) => {
    try {
      await runCheckForWebsite(website);
    } catch (err) {
      logger.error({ err, websiteId: website.id }, 'Check failed unexpectedly');
    }
  });
  return active.length;
}

async function tick(): Promise<void> {
  if (ticking) return;
  ticking = true;
  try {
    const count = await runDueChecks();
    if (count > 0) {
      logger.debug({ count }, 'Monitoring tick completed');
    }
  } catch (err) {
    logger.error({ err }, 'Monitoring tick error');
  } finally {
    ticking = false;
  }
}

export function startMonitoringWorker(): void {
  logger.info({ tickMs: env.MONITOR_TICK_MS }, 'Starting monitoring worker');
  void tick();
  tickTimer = setInterval(() => {
    void tick();
  }, env.MONITOR_TICK_MS);
  tickTimer.unref?.();
}

export function stopMonitoringWorker(): void {
  if (tickTimer) {
    clearInterval(tickTimer);
    tickTimer = null;
  }
}
