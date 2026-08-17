import { MonitorStatus } from '@prisma/client';
import { prisma } from '../lib/prisma';

export interface UptimePeriod {
  hours?: number;
  days?: number;
}

export interface UptimeStats {
  total: number;
  up: number;
  down: number;
  degraded: number;
  unknown: number;
  uptimePercent: number;
  avgResponseMs: number | null;
}

function periodStart(period: UptimePeriod, now = new Date()): Date {
  const start = new Date(now);
  if (period.hours) {
    start.setTime(start.getTime() - period.hours * 60 * 60 * 1000);
  } else if (period.days) {
    start.setTime(start.getTime() - period.days * 24 * 60 * 60 * 1000);
  }
  return start;
}

/** Count UP + DEGRADED as "available" for uptime percentage. */
export function calculateUptimePercent(
  total: number,
  up: number,
  degraded: number,
): number {
  if (total === 0) return 100;
  return Math.round(((up + degraded) / total) * 10000) / 100;
}

export async function getUptimeStats(
  websiteId: string,
  period: UptimePeriod,
): Promise<UptimeStats> {
  const from = periodStart(period);

  const logs = await prisma.monitorLog.groupBy({
    by: ['status'],
    where: {
      websiteId,
      checkedAt: { gte: from },
    },
    _count: { _all: true },
    _avg: { responseMs: true },
  });

  let total = 0;
  let up = 0;
  let down = 0;
  let degraded = 0;
  let unknown = 0;
  let responseSum = 0;
  let responseCount = 0;

  for (const row of logs) {
    const count = row._count._all;
    total += count;
    switch (row.status) {
      case MonitorStatus.UP:
        up += count;
        break;
      case MonitorStatus.DOWN:
        down += count;
        break;
      case MonitorStatus.DEGRADED:
        degraded += count;
        break;
      default:
        unknown += count;
    }
    if (row._avg.responseMs != null) {
      responseSum += row._avg.responseMs * count;
      responseCount += count;
    }
  }

  return {
    total,
    up,
    down,
    degraded,
    unknown,
    uptimePercent: calculateUptimePercent(total, up, degraded),
    avgResponseMs:
      responseCount > 0 ? Math.round(responseSum / responseCount) : null,
  };
}

export async function getMultiPeriodUptime(websiteId: string): Promise<{
  h24: UptimeStats;
  d7: UptimeStats;
  d30: UptimeStats;
  d90: UptimeStats;
}> {
  const [h24, d7, d30, d90] = await Promise.all([
    getUptimeStats(websiteId, { hours: 24 }),
    getUptimeStats(websiteId, { days: 7 }),
    getUptimeStats(websiteId, { days: 30 }),
    getUptimeStats(websiteId, { days: 90 }),
  ]);
  return { h24, d7, d30, d90 };
}

export async function getResponseTimeSeries(
  websiteId: string,
  days = 7,
): Promise<Array<{ checkedAt: Date; responseMs: number | null; status: MonitorStatus }>> {
  const from = periodStart({ days });
  return prisma.monitorLog.findMany({
    where: { websiteId, checkedAt: { gte: from } },
    select: { checkedAt: true, responseMs: true, status: true },
    orderBy: { checkedAt: 'asc' },
    take: 5000,
  });
}

export async function getSparklineData(
  websiteId: string,
  points = 48,
): Promise<Array<{ t: string; ms: number | null; s: MonitorStatus }>> {
  const from = periodStart({ hours: 24 });
  const logs = await prisma.monitorLog.findMany({
    where: { websiteId, checkedAt: { gte: from } },
    select: { checkedAt: true, responseMs: true, status: true },
    orderBy: { checkedAt: 'asc' },
  });

  if (logs.length === 0) return [];
  if (logs.length <= points) {
    return logs.map((l) => ({
      t: l.checkedAt.toISOString(),
      ms: l.responseMs,
      s: l.status,
    }));
  }

  const step = Math.ceil(logs.length / points);
  const sampled = [];
  for (let i = 0; i < logs.length; i += step) {
    const l = logs[i];
    sampled.push({
      t: l.checkedAt.toISOString(),
      ms: l.responseMs,
      s: l.status,
    });
  }
  return sampled;
}

export type DaySegment = 'up' | 'down' | 'degraded' | 'unknown' | 'empty';

const STATUS_RANK: Record<MonitorStatus, number> = {
  [MonitorStatus.DOWN]: 4,
  [MonitorStatus.DEGRADED]: 3,
  [MonitorStatus.UNKNOWN]: 2,
  [MonitorStatus.UP]: 1,
};

function toSegment(status: MonitorStatus | null): DaySegment {
  if (!status) return 'empty';
  switch (status) {
    case MonitorStatus.UP:
      return 'up';
    case MonitorStatus.DOWN:
      return 'down';
    case MonitorStatus.DEGRADED:
      return 'degraded';
    default:
      return 'unknown';
  }
}

/** Worst-status-per-day history for status-page uptime bars (default 90 days). */
export async function getDailyHistory(
  websiteId: string,
  days = 90,
): Promise<Array<{ date: string; segment: DaySegment }>> {
  const now = new Date();
  const start = new Date(now);
  start.setUTCHours(0, 0, 0, 0);
  start.setUTCDate(start.getUTCDate() - (days - 1));

  const logs = await prisma.monitorLog.findMany({
    where: { websiteId, checkedAt: { gte: start } },
    select: { checkedAt: true, status: true },
    orderBy: { checkedAt: 'asc' },
  });

  const byDay = new Map<string, MonitorStatus>();
  for (const log of logs) {
    const key = log.checkedAt.toISOString().slice(0, 10);
    const existing = byDay.get(key);
    if (!existing || STATUS_RANK[log.status] > STATUS_RANK[existing]) {
      byDay.set(key, log.status);
    }
  }

  const result: Array<{ date: string; segment: DaySegment }> = [];
  for (let i = 0; i < days; i += 1) {
    const d = new Date(start);
    d.setUTCDate(start.getUTCDate() + i);
    const key = d.toISOString().slice(0, 10);
    result.push({
      date: key,
      segment: toSegment(byDay.get(key) ?? null),
    });
  }
  return result;
}
