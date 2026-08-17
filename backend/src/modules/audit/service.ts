import { Prisma } from '@prisma/client';
import { prisma } from '../../lib/prisma';
import type { ListAuditInput } from './schemas';

export async function listAudit(query: ListAuditInput) {
  const where: Prisma.AuditLogWhereInput = {};

  if (query.action) {
    where.action = { contains: query.action, mode: 'insensitive' };
  }
  if (query.userId) where.userId = query.userId;
  if (query.from || query.to) {
    where.createdAt = {};
    if (query.from) where.createdAt.gte = new Date(query.from);
    if (query.to) where.createdAt.lte = new Date(query.to);
  }

  const [items, total] = await Promise.all([
    prisma.auditLog.findMany({
      where,
      include: {
        user: { select: { id: true, email: true, name: true } },
      },
      orderBy: { createdAt: 'desc' },
      skip: (query.page - 1) * query.limit,
      take: query.limit,
    }),
    prisma.auditLog.count({ where }),
  ]);

  return {
    items,
    total,
    page: query.page,
    limit: query.limit,
    totalPages: Math.ceil(total / query.limit),
  };
}
