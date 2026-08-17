import { MonitorStatus } from '@prisma/client';
import { prisma } from '../../lib/prisma';
import { NotFoundError } from '../../lib/errors';
import {
  getMultiPeriodUptime,
  getSparklineData,
  getDailyHistory,
} from '../../monitoring/uptime';
import { isStale } from '../../monitoring/stale';

function overallFromStatuses(statuses: MonitorStatus[]): MonitorStatus {
  if (statuses.length === 0) return MonitorStatus.UNKNOWN;
  if (statuses.some((s) => s === MonitorStatus.DOWN)) return MonitorStatus.DOWN;
  if (statuses.some((s) => s === MonitorStatus.DEGRADED))
    return MonitorStatus.DEGRADED;
  if (statuses.every((s) => s === MonitorStatus.UNKNOWN))
    return MonitorStatus.UNKNOWN;
  return MonitorStatus.UP;
}

export async function getPublicStatus() {
  const websites = await prisma.website.findMany({
    where: { isPublic: true },
    orderBy: { name: 'asc' },
  });

  const items = await Promise.all(
    websites.map(async (w) => {
      const [uptime, openIncidents, history] = await Promise.all([
        getMultiPeriodUptime(w.id),
        prisma.incident.findMany({
          where: { websiteId: w.id, resolvedAt: null },
          orderBy: { startedAt: 'desc' },
          take: 5,
        }),
        getDailyHistory(w.id, 90),
      ]);

      return {
        id: w.id,
        name: w.name,
        slug: w.slug,
        url: w.url,
        description: w.description,
        currentStatus: w.currentStatus,
        lastCheckedAt: w.lastCheckedAt,
        lastResponseMs: w.lastResponseMs,
        lastStatusCode: w.lastStatusCode,
        isStale: isStale(w.lastCheckedAt, w.intervalSeconds),
        uptime: {
          h24: uptime.h24.uptimePercent,
          d7: uptime.d7.uptimePercent,
          d30: uptime.d30.uptimePercent,
          d90: uptime.d90.uptimePercent,
        },
        avgResponse: {
          h24: uptime.h24.avgResponseMs,
          d7: uptime.d7.avgResponseMs,
          d30: uptime.d30.avgResponseMs,
          d90: uptime.d90.avgResponseMs,
        },
        history,
        openIncidents,
      };
    }),
  );

  return {
    overall: overallFromStatuses(items.map((i) => i.currentStatus)),
    websites: items,
    generatedAt: new Date().toISOString(),
  };
}

export async function getPublicSiteStatus(slug: string) {
  const website = await prisma.website.findFirst({
    where: { slug, isPublic: true },
  });
  if (!website) throw new NotFoundError('Website not found');

  const [uptime, sparkline, incidents, history] = await Promise.all([
    getMultiPeriodUptime(website.id),
    getSparklineData(website.id, 48),
    prisma.incident.findMany({
      where: { websiteId: website.id },
      orderBy: { startedAt: 'desc' },
      take: 20,
    }),
    getDailyHistory(website.id, 90),
  ]);

  return {
    website: {
      id: website.id,
      name: website.name,
      slug: website.slug,
      url: website.url,
      description: website.description,
      currentStatus: website.currentStatus,
      lastCheckedAt: website.lastCheckedAt,
      lastResponseMs: website.lastResponseMs,
      lastStatusCode: website.lastStatusCode,
      isStale: isStale(website.lastCheckedAt, website.intervalSeconds),
    },
    uptime,
    sparkline,
    history,
    incidents,
    generatedAt: new Date().toISOString(),
  };
}
