import { z } from 'zod';
import { MonitorStatus } from '@prisma/client';

export const listLogsSchema = z.object({
  page: z.coerce.number().int().positive().default(1),
  limit: z.coerce.number().int().min(1).max(200).default(50),
  websiteId: z.string().optional(),
  status: z.nativeEnum(MonitorStatus).optional(),
  from: z.string().datetime().optional(),
  to: z.string().datetime().optional(),
  search: z.string().optional(),
  sortBy: z.enum(['checkedAt', 'responseMs', 'status']).default('checkedAt'),
  sortOrder: z.enum(['asc', 'desc']).default('desc'),
});

export type ListLogsInput = z.infer<typeof listLogsSchema>;
