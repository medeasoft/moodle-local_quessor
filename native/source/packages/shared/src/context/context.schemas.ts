import { z } from "zod";

export const createContextSchema = z.object({
  examId: z.string().uuid(),
  pageIndex: z.coerce.number().int().min(0),
  pageId: z.string().uuid().optional().nullable(),
  order: z.coerce.number().int().min(1).default(1),
  text: z.string().min(1, "context_text_required"),
});

export const updateContextSchema = z.object({
  pageIndex: z.coerce.number().int().min(0).optional(),
  pageId: z.string().uuid().optional().nullable(),
  order: z.coerce.number().int().min(1).optional(),
  text: z.string().min(1).optional(),
});

export type CreateContextDto = z.infer<typeof createContextSchema>;
export type UpdateContextDto = z.infer<typeof updateContextSchema>;
