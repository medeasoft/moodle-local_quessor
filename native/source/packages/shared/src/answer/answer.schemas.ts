import { z } from "zod";

export const createAnswerSchema = z.object({
    answer: z.string().optional().nullable(),
    point: z.coerce.number().min(0, "answer_point_min").optional().nullable(),
    feedback: z.string().max(2000, "answer_feedback_max").optional().nullable(),
    questionId: z.uuid("question_uuid_invalid"),
    studentId: z.uuid("student_uuid_invalid")
});

export const updateAnswerSchema = z.object({
    answer: z.string().optional().nullable(),
    point: z.coerce.number().min(0, "answer_point_min").optional().nullable(),
    feedback: z.string().max(2000, "answer_feedback_max").optional().nullable(),
    questionId: z.uuid("question_uuid_invalid").optional(),
});

export type CreateAnswerDto = z.infer<typeof createAnswerSchema>;
export type UpdateAnswerDto = z.infer<typeof updateAnswerSchema>;
