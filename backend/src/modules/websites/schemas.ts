import { z } from 'zod';
import { HttpMethod, MonitorStatus } from '@prisma/client';

const urlSchema = z
  .string()
  .url()
  .refine((u) => u.startsWith('http://') || u.startsWith('https://'), {
    message: 'URL must start with http:// or https://',
  });

export const createWebsiteSchema = z.object({
  name: z.string().min(1).max(200),
  url: urlSchema,
  slug: z
    .string()
    .min(1)
    .max(100)
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'Slug must be lowercase kebab-case')
    .optional(),
  description: z.string().max(1000).nullable().optional(),
  method: z.nativeEnum(HttpMethod).optional(),
  intervalSeconds: z.number().int().min(30).max(86400).optional(),
  timeoutMs: z.number().int().min(1000).max(60000).optional(),
  expectedStatus: z.number().int().min(100).max(599).optional(),
  keyword: z.string().max(500).nullable().optional(),
  headersJson: z.string().nullable().optional(),
  isActive: z.boolean().optional(),
  isPublic: z.boolean().optional(),
});

export const updateWebsiteSchema = createWebsiteSchema.partial().extend({
  name: z.string().min(1).max(200).optional(),
  url: urlSchema.optional(),
});

export const listWebsitesSchema = z.object({
  page: z.coerce.number().int().positive().default(1),
  limit: z.coerce.number().int().min(1).max(100).default(20),
  search: z.string().optional(),
  status: z.nativeEnum(MonitorStatus).optional(),
  isActive: z
    .enum(['true', 'false'])
    .optional()
    .transform((v) => (v === undefined ? undefined : v === 'true')),
  sortBy: z
    .enum([
      'name',
      'createdAt',
      'updatedAt',
      'lastCheckedAt',
      'currentStatus',
      'intervalSeconds',
    ])
    .default('name'),
  sortOrder: z.enum(['asc', 'desc']).default('asc'),
});

export const bulkActionSchema = z.object({
  ids: z.array(z.string().min(1)).min(1),
  action: z.enum(['activate', 'deactivate', 'delete', 'check']),
});

export const uptimeQuerySchema = z.object({
  days: z.coerce.number().int().min(1).max(90).default(90),
});

export type CreateWebsiteInput = z.infer<typeof createWebsiteSchema>;
export type UpdateWebsiteInput = z.infer<typeof updateWebsiteSchema>;
export type ListWebsitesInput = z.infer<typeof listWebsitesSchema>;
export type BulkActionInput = z.infer<typeof bulkActionSchema>;
