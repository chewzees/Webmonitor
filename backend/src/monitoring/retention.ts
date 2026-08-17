import { prisma } from '../lib/prisma';
import { logger } from '../lib/logger';
import { env } from '../config/env';

export async function purgeOldLogs(): Promise<number> {
  const cutoff = new Date();
  cutoff.setDate(cutoff.getDate() - env.LOG_RETENTION_DAYS);

  const result = await prisma.monitorLog.deleteMany({
    where: { checkedAt: { lt: cutoff } },
  });

  if (result.count > 0) {
    logger.info(
      { deleted: result.count, cutoff: cutoff.toISOString() },
      'Purged old monitor logs',
    );
  }

  return result.count;
}

let retentionTimer: NodeJS.Timeout | null = null;

export function startRetentionJob(): void {
  const DAY_MS = 24 * 60 * 60 * 1000;

  const run = () => {
    purgeOldLogs().catch((err) => {
      logger.error({ err }, 'Retention job failed');
    });
  };

  // Run once shortly after start, then daily
  setTimeout(run, 60_000);
  retentionTimer = setInterval(run, DAY_MS);
  retentionTimer.unref?.();
}

export function stopRetentionJob(): void {
  if (retentionTimer) {
    clearInterval(retentionTimer);
    retentionTimer = null;
  }
}
