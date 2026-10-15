import { z } from "zod";

export const createAnswerPaperSchema = z.object({
    examId: z.string().uuid(),
    displayName: z.string().min(1).optional(), // Optional: if not provided, backend will use PDF filename
    fileKey: z.string().min(1),
});

export const updateAnswerPaperSchema = z.object({
    displayName: z.string().min(1).optional(),
    fileKey: z.string().min(1).optional(),
});
