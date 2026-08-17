import { MonitorStatus } from '@prisma/client';
import { prisma } from '../../lib/prisma';
import { isStale } from '../../monitoring/stale';
import { calculateUptimePercent } from '../../monitoring/uptime';

export async function getDashboard() {
  const since24h = new Date(Date.now() - 24 * 60 * 60 * 1000);

  const [
    totalSites,
    statusGroups,
    avgAgg,
    recentIncidents,
    checksLast24h,
    logStatusGroups,
    websites,
  ] = await Promise.all([
    prisma.website.count(),
    prisma.website.groupBy({
      by: ['currentStatus'],
      _count: { _all: true },
    }),
    prisma.website.aggregate({
      _avg: { lastResponseMs: true },
      where: { lastResponseMs: { not: null } },
    }),
    prisma.incident.findMany({
      orderBy: { startedAt: 'desc' },
      take: 10,
      include: {
        website: { select: { id: true, name: true, slug: true } },
      },
    }),
    prisma.monitorLog.count({
      where: { checkedAt: { gte: since24h } },
    }),
    prisma.monitorLog.groupBy({
      by: ['status'],
      where: { checkedAt: { gte: since24h } },
      _count: { _all: true },
    }),
    prisma.website.findMany({
      select: {
        id: true,
        lastCheckedAt: true,
        intervalSeconds: true,
        isActive: true,
      },
    }),
  ]);

  const counts = {
    UP: 0,
    DOWN: 0,
    UNKNOWN: 0,
    DEGRADED: 0,
  };
  for (const g of statusGroups) {
    counts[g.currentStatus] = g._count._all;
  }

  let up = 0;
  let degraded = 0;
  let totalLogs = 0;
  for (const g of logStatusGroups) {
    totalLogs += g._count._all;
    if (g.status === MonitorStatus.UP) up += g._count._all;
    if (g.status === MonitorStatus.DEGRADED) degraded += g._count._all;
  }

  const staleCount = websites.filter(
    (w) => w.isActive && isStale(w.lastCheckedAt, w.intervalSeconds),
  ).length;

  return {
    totalSites,
    statusCounts: counts,
    avgResponseMs: avgAgg._avg.lastResponseMs
      ? Math.round(avgAgg._avg.lastResponseMs)
      : null,
    overallUptime24h: calculateUptimePercent(totalLogs, up, degraded),
    recentIncidents,
    checksLast24h,
    staleCount,
  };
}
