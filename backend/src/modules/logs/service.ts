import { Prisma } from '@prisma/client';
import { prisma } from '../../lib/prisma';
import type { ListLogsInput } from './schemas';

export async function listLogs(query: ListLogsInput) {
  const where: Prisma.MonitorLogWhereInput = {};

  if (query.websiteId) where.websiteId = query.websiteId;
  if (query.status) where.status = query.status;
  if (query.from || query.to) {
    where.checkedAt = {};
    if (query.from) where.checkedAt.gte = new Date(query.from);
    if (query.to) where.checkedAt.lte = new Date(query.to);
  }
  if (query.search) {
    where.OR = [
      { errorMessage: { contains: query.search, mode: 'insensitive' } },
      { website: { name: { contains: query.search, mode: 'insensitive' } } },
    ];
  }

  const [items, total] = await Promise.all([
    prisma.monitorLog.findMany({
      where,
      include: {
        website: { select: { id: true, name: true, slug: true, url: true } },
      },
      orderBy: { [query.sortBy]: query.sortOrder },
      skip: (query.page - 1) * query.limit,
      take: query.limit,
    }),
    prisma.monitorLog.count({ where }),
  ]);

  return {
    items,
    total,
    page: query.page,
    limit: query.limit,
    totalPages: Math.ceil(total / query.limit),
  };
}

export async function exportLogsCsv(query: Omit<ListLogsInput, 'page' | 'limit'>) {
  const where: Prisma.MonitorLogWhereInput = {};
  if (query.websiteId) where.websiteId = query.websiteId;
  if (query.status) where.status = query.status;
  if (query.from || query.to) {
    where.checkedAt = {};
    if (query.from) where.checkedAt.gte = new Date(query.from);
    if (query.to) where.checkedAt.lte = new Date(query.to);
  }

  const logs = await prisma.monitorLog.findMany({
    where,
    include: { website: { select: { name: true, slug: true, url: true } } },
    orderBy: { checkedAt: 'desc' },
    take: 10000,
  });

  const headers = [
    'id',
    'websiteName',
    'websiteSlug',
    'url',
    'status',
    'statusCode',
    'responseMs',
    'errorMessage',
    'checkedAt',
  ];

  const escape = (v: unknown) => {
    const s = v == null ? '' : String(v);
    if (s.includes(',') || s.includes('"') || s.includes('\n')) {
      return `"${s.replace(/"/g, '""')}"`;
    }
    return s;
  };

  const rows = logs.map((l) =>
    [
      l.id,
      l.website.name,
      l.website.slug,
      l.website.url,
      l.status,
      l.statusCode ?? '',
      l.responseMs ?? '',
      l.errorMessage ?? '',
      l.checkedAt.toISOString(),
    ]
      .map(escape)
      .join(','),
  );

  return [headers.join(','), ...rows].join('\n');
}
