import { Website } from '@prisma/client';
import { ConflictError, NotFoundError, ValidationError } from '../../lib/errors';
import * as repo from './repository';
import type {
  BulkActionInput,
  CreateWebsiteInput,
  ListWebsitesInput,
  UpdateWebsiteInput,
} from './schemas';
import { runCheckForWebsite, runAllActiveChecks } from '../../monitoring/worker';
import { getUptimeStats, getResponseTimeSeries, getMultiPeriodUptime } from '../../monitoring/uptime';
import { isStale } from '../../monitoring/stale';
import { eventBus } from '../events/bus';
import { writeAuditLog } from '../auth/service';

export function slugify(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80) || 'website';
}

export async function ensureUniqueSlug(
  base: string,
  excludeId?: string,
): Promise<string> {
  let slug = base;
  let i = 2;
  while (await repo.slugExists(slug, excludeId)) {
    slug = `${base}-${i}`;
    i += 1;
  }
  return slug;
}

function withStale(website: Website) {
  return {
    ...website,
    isStale: isStale(website.lastCheckedAt, website.intervalSeconds),
  };
}

export async function listWebsites(query: ListWebsitesInput) {
  const { items, total } = await repo.findManyWebsites(query);
  return {
    items: items.map(withStale),
    total,
    page: query.page,
    limit: query.limit,
    totalPages: Math.ceil(total / query.limit),
  };
}

export async function getWebsite(id: string) {
  const website = await repo.findWebsiteById(id);
  if (!website) throw new NotFoundError('Website not found');
  return withStale(website);
}

export async function createWebsite(
  input: CreateWebsiteInput,
  userId?: string,
  meta?: { ip?: string; userAgent?: string },
) {
  if (input.headersJson) {
    try {
      JSON.parse(input.headersJson);
    } catch {
      throw new ValidationError('headersJson must be valid JSON');
    }
  }

  const baseSlug = input.slug ?? slugify(input.name);
  const slug = await ensureUniqueSlug(baseSlug);

  const website = await repo.createWebsite({ ...input, slug });

  await writeAuditLog({
    userId,
    action: 'website.create',
    entityType: 'Website',
    entityId: website.id,
    metadata: { name: website.name, url: website.url },
    ip: meta?.ip,
    userAgent: meta?.userAgent,
  });

  eventBus.publish('website.updated', {
    action: 'create',
    websiteId: website.id,
  });

  return withStale(website);
}

export async function updateWebsite(
  id: string,
  input: UpdateWebsiteInput,
  userId?: string,
  meta?: { ip?: string; userAgent?: string },
) {
  const existing = await repo.findWebsiteById(id);
  if (!existing) throw new NotFoundError('Website not found');

  if (input.headersJson) {
    try {
      JSON.parse(input.headersJson);
    } catch {
      throw new ValidationError('headersJson must be valid JSON');
    }
  }

  let slug = input.slug;
  if (slug && slug !== existing.slug) {
    if (await repo.slugExists(slug, id)) {
      throw new ConflictError('Slug already in use');
    }
  } else if (input.name && !input.slug) {
    // keep existing slug unless explicitly changed
    slug = undefined;
  }

  const website = await repo.updateWebsite(id, { ...input, slug });

  await writeAuditLog({
    userId,
    action: 'website.update',
    entityType: 'Website',
    entityId: website.id,
    metadata: input,
    ip: meta?.ip,
    userAgent: meta?.userAgent,
  });

  eventBus.publish('website.updated', {
    action: 'update',
    websiteId: website.id,
  });

  return withStale(website);
}

export async function deleteWebsite(
  id: string,
  userId?: string,
  meta?: { ip?: string; userAgent?: string },
) {
  const existing = await repo.findWebsiteById(id);
  if (!existing) throw new NotFoundError('Website not found');

  await repo.deleteWebsite(id);

  await writeAuditLog({
    userId,
    action: 'website.delete',
    entityType: 'Website',
    entityId: id,
    metadata: { name: existing.name },
    ip: meta?.ip,
    userAgent: meta?.userAgent,
  });

  eventBus.publish('website.updated', { action: 'delete', websiteId: id });
}

export async function checkNow(id: string) {
  const website = await repo.findWebsiteById(id);
  if (!website) throw new NotFoundError('Website not found');
  const updated = await runCheckForWebsite(website);
  return withStale(updated);
}

export async function checkAll() {
  const count = await runAllActiveChecks();
  return { checked: count };
}

export async function bulkAction(
  input: BulkActionInput,
  userId?: string,
  meta?: { ip?: string; userAgent?: string },
) {
  const websites = await repo.findWebsitesByIds(input.ids);
  if (websites.length === 0) {
    throw new NotFoundError('No websites found for given ids');
  }

  let affected = 0;

  switch (input.action) {
    case 'activate':
      affected = await repo.setActiveMany(input.ids, true);
      break;
    case 'deactivate':
      affected = await repo.setActiveMany(input.ids, false);
      break;
    case 'delete':
      affected = await repo.deleteMany(input.ids);
      break;
    case 'check': {
      for (const w of websites) {
        try {
          await runCheckForWebsite(w);
          affected += 1;
        } catch {
          // continue
        }
      }
      break;
    }
  }

  await writeAuditLog({
    userId,
    action: `website.bulk.${input.action}`,
    entityType: 'Website',
    metadata: { ids: input.ids, affected },
    ip: meta?.ip,
    userAgent: meta?.userAgent,
  });

  eventBus.publish('website.updated', {
    action: `bulk.${input.action}`,
    ids: input.ids,
  });

  return { action: input.action, affected };
}

export async function getUptime(id: string, days: number) {
  const website = await repo.findWebsiteById(id);
  if (!website) throw new NotFoundError('Website not found');
  const stats = await getUptimeStats(id, { days });
  const multi = days === 90 ? await getMultiPeriodUptime(id) : null;
  return { websiteId: id, days, stats, multi };
}

export async function getStats(id: string) {
  const website = await repo.findWebsiteById(id);
  if (!website) throw new NotFoundError('Website not found');

  const [uptime, series] = await Promise.all([
    getMultiPeriodUptime(id),
    getResponseTimeSeries(id, 7),
  ]);

  return {
    website: withStale(website),
    uptime,
    responseTimeSeries: series,
  };
}

export async function exportCsv(): Promise<string> {
  const websites = await repo.findAllForExport();
  const headers = [
    'id',
    'name',
    'url',
    'slug',
    'method',
    'intervalSeconds',
    'timeoutMs',
    'expectedStatus',
    'isActive',
    'isPublic',
    'currentStatus',
    'lastCheckedAt',
    'lastResponseMs',
    'lastStatusCode',
  ];

  const escape = (v: unknown) => {
    const s = v == null ? '' : String(v);
    if (s.includes(',') || s.includes('"') || s.includes('\n')) {
      return `"${s.replace(/"/g, '""')}"`;
    }
    return s;
  };

  const rows = websites.map((w) =>
    [
      w.id,
      w.name,
      w.url,
      w.slug,
      w.method,
      w.intervalSeconds,
      w.timeoutMs,
      w.expectedStatus,
      w.isActive,
      w.isPublic,
      w.currentStatus,
      w.lastCheckedAt?.toISOString() ?? '',
      w.lastResponseMs ?? '',
      w.lastStatusCode ?? '',
    ]
      .map(escape)
      .join(','),
  );

  return [headers.join(','), ...rows].join('\n');
}
