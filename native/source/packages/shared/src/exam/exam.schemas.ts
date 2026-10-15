import { z } from "zod";

export const FEEDBACK_LANGUAGE_CODES = ["tr", "en", "bg"] as const;
export type FeedbackLanguageCode = (typeof FEEDBACK_LANGUAGE_CODES)[number];
export const DEFAULT_FEEDBACK_LANGUAGE: FeedbackLanguageCode = "tr";
export const feedbackLanguageSchema = z.enum(FEEDBACK_LANGUAGE_CODES);

export function parseFeedbackLanguage(value: string | null | undefined): FeedbackLanguageCode {
  const normalized = value?.trim().toLowerCase();
  if (normalized && (FEEDBACK_LANGUAGE_CODES as readonly string[]).includes(normalized)) {
    return normalized as FeedbackLanguageCode;
  }
  return DEFAULT_FEEDBACK_LANGUAGE;
}

export const createExamSchema = z.object({
  title: z.string().min(2, "exam_title_min").max(255, "exam_title_max"),
  maxScore: z.coerce.number().int().min(0, "exam_max_score_min").default(100),
  targetLevel: z.string().optional().nullable(),
  examGradingInstructions: z.string().optional().nullable(),
  feedbackLanguage: feedbackLanguageSchema.default(DEFAULT_FEEDBACK_LANGUAGE),
  scoreIncrement: z.coerce.number().positive().default(0.1),
  courseId: z.string().uuid("course_uuid_invalid")
});

export const updateExamSchema = z.object({
  title: z.string().min(2, "exam_title_min").max(255, "exam_title_max").optional(),
  maxScore: z.coerce.number().int().min(0, "exam_max_score_min").optional(),
  targetLevel: z.string().optional().nullable(),
  examGradingInstructions: z.string().optional().nullable(),
  feedbackLanguage: feedbackLanguageSchema.optional(),
  scoreIncrement: z.coerce.number().positive().optional()
});

export type CreateExamDto = z.infer<typeof createExamSchema>;
export type UpdateExamDto = z.infer<typeof updateExamSchema>;
