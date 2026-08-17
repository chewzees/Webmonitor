import { z } from 'zod';

export const updateTelegramSchema = z.object({
  botToken: z.string().optional(),
  chatId: z.string().optional(),
  enabled: z.boolean().optional(),
  notifyOnDown: z.boolean().optional(),
  notifyOnUp: z.boolean().optional(),
});

export type UpdateTelegramInput = z.infer<typeof updateTelegramSchema>;
