import { z } from "zod";

export const examPaperTypeSchema = z.enum(['QUESTION', 'ANSWER_KEY', 'TOGETHER']);

export const createExamPaperSchema = z.object({
    examId: z.uuid(),
    fileKey: z.string().min(1),
    paperType: examPaperTypeSchema,
});

export const updateExamPaperSchema = z.object({
    fileKey: z.string().min(1).optional(),
    paperType: examPaperTypeSchema.optional(),
});
