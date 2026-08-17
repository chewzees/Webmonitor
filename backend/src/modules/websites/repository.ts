import { Prisma, Website } from '@prisma/client';
import { prisma } from '../../lib/prisma';
import type { CreateWebsiteInput, ListWebsitesInput, UpdateWebsiteInput } from './schemas';

export async function findManyWebsites(query: ListWebsitesInput): Promise<{
  items: Website[];
  total: number;
}> {
  const where: Prisma.WebsiteWhereInput = {};

  if (query.search) {
    where.OR = [
      { name: { contains: query.search, mode: 'insensitive' } },
      { url: { contains: query.search, mode: 'insensitive' } },
      { slug: { contains: query.search, mode: 'insensitive' } },
    ];
  }
  if (query.status) where.currentStatus = query.status;
  if (query.isActive !== undefined) where.isActive = query.isActive;

  const [items, total] = await Promise.all([
    prisma.website.findMany({
      where,
      orderBy: { [query.sortBy]: query.sortOrder },
      skip: (query.page - 1) * query.limit,
      take: query.limit,
    }),
    prisma.website.count({ where }),
  ]);

  return { items, total };
}

export async function findWebsiteById(id: string): Promise<Website | null> {
  return prisma.website.findUnique({ where: { id } });
}

export async function findWebsiteBySlug(slug: string): Promise<Website | null> {
  return prisma.website.findUnique({ where: { slug } });
}

export async function createWebsite(
  data: CreateWebsiteInput & { slug: string },
): Promise<Website> {
  return prisma.website.create({
    data: {
      name: data.name,
      url: data.url,
      slug: data.slug,
      description: data.description ?? null,
      method: data.method ?? 'GET',
      intervalSeconds: data.intervalSeconds ?? 60,
      timeoutMs: data.timeoutMs ?? 10000,
      expectedStatus: data.expectedStatus ?? 200,
      keyword: data.keyword ?? null,
      headersJson: data.headersJson ?? null,
      isActive: data.isActive ?? true,
      isPublic: data.isPublic ?? true,
    },
  });
}

export async function updateWebsite(
  id: string,
  data: UpdateWebsiteInput,
): Promise<Website> {
  return prisma.website.update({
    where: { id },
    data: {
      ...(data.name !== undefined && { name: data.name }),
      ...(data.url !== undefined && { url: data.url }),
      ...(data.slug !== undefined && { slug: data.slug }),
      ...(data.description !== undefined && { description: data.description }),
      ...(data.method !== undefined && { method: data.method }),
      ...(data.intervalSeconds !== undefined && {
        intervalSeconds: data.intervalSeconds,
      }),
      ...(data.timeoutMs !== undefined && { timeoutMs: data.timeoutMs }),
      ...(data.expectedStatus !== undefined && {
        expectedStatus: data.expectedStatus,
      }),
      ...(data.keyword !== undefined && { keyword: data.keyword }),
      ...(data.headersJson !== undefined && { headersJson: data.headersJson }),
      ...(data.isActive !== undefined && { isActive: data.isActive }),
      ...(data.isPublic !== undefined && { isPublic: data.isPublic }),
    },
  });
}

export async function deleteWebsite(id: string): Promise<void> {
  await prisma.website.delete({ where: { id } });
}

export async function findWebsitesByIds(ids: string[]): Promise<Website[]> {
  return prisma.website.findMany({ where: { id: { in: ids } } });
}

export async function setActiveMany(
  ids: string[],
  isActive: boolean,
): Promise<number> {
  const result = await prisma.website.updateMany({
    where: { id: { in: ids } },
    data: { isActive },
  });
  return result.count;
}

export async function deleteMany(ids: string[]): Promise<number> {
  const result = await prisma.website.deleteMany({
    where: { id: { in: ids } },
  });
  return result.count;
}

export async function findAllForExport(): Promise<Website[]> {
  return prisma.website.findMany({ orderBy: { name: 'asc' } });
}

export async function slugExists(
  slug: string,
  excludeId?: string,
): Promise<boolean> {
  const existing = await prisma.website.findFirst({
    where: {
      slug,
      ...(excludeId ? { id: { not: excludeId } } : {}),
    },
    select: { id: true },
  });
  return Boolean(existing);
}
