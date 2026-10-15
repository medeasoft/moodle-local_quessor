import { z } from "zod";
import { QuestionType } from "../question";

export const QUESTION_GENERATION_LIMITS = {
  MAX_FILES: 5,
  MAX_FILE_SIZE_MB: 10,
  MAX_URLS: 5,
  MAX_TEXT_CHARS: 50000
};

export const questionGenerationRubricDetailSchema = z.enum(["concise", "detailed", "point_based"]);

export const createQuestionGenerationSchema = z
  .object({
    examId: z.string().uuid("invalid_exam_uuid").optional().nullable(),
    title: z.string().min(1, "title_required").max(255, "title_too_long"),
    textValue: z.string().max(QUESTION_GENERATION_LIMITS.MAX_TEXT_CHARS, "text_too_long").optional().nullable(),
    urlValues: z
      .preprocess(
        (val) => {
          if (typeof val === "string") {
            try {
              return JSON.parse(val);
            } catch {
              return val
                .split(",")
                .map((v) => v.trim())
                .filter(Boolean);
            }
          }
          return val;
        },
        z.array(z.string().url("invalid_url")).max(QUESTION_GENERATION_LIMITS.MAX_URLS, "too_many_urls")
      )
      .optional()
      .default([]),
    questionTypesBreakdown: z.preprocess(
      (val) => {
        if (typeof val === "string") {
          try {
            return JSON.parse(val);
          } catch {
            return {};
          }
        }
        return val || {};
      },
      z.record(z.nativeEnum(QuestionType), z.coerce.number().int().min(0))
    ),
    difficulty: z.enum(["EASY", "MEDIUM", "HARD"]).default("MEDIUM"),
    language: z.string().trim().max(32).default("tr"),
    generateCorrectAnswer: z
      .preprocess((value) => (value === "true" ? true : value === "false" ? false : value), z.boolean())
      .default(true),
    generateRubric: z
      .preprocess((value) => (value === "true" ? true : value === "false" ? false : value), z.boolean())
      .default(true),
    rubricDetail: questionGenerationRubricDetailSchema.default("detailed")
  })
  .refine(
    (data) => {
      const total = Object.values(data.questionTypesBreakdown).reduce((sum, count) => sum + count, 0);
      return total > 0 && total <= 50;
    },
    {
      message: "Soru sayısı toplamda en az 1, en fazla 50 olmalıdır.",
      path: ["questionTypesBreakdown"]
    }
  );

export type CreateQuestionGenerationDto = z.infer<typeof createQuestionGenerationSchema>;

export const exportQuestionsToExamSchema = z.object({
  examId: z.uuid("invalid_exam_uuid"),
  questionGenerationId: z.uuid("invalid_generation_uuid"),
  selectedIndexes: z.array(z.number().int().min(0)).min(1, "select_at_least_one_question")
});

export type ExportQuestionsToExamDto = z.infer<typeof exportQuestionsToExamSchema>;

export const updateGeneratedQuestionsSchema = z.object({
  questions: z
    .array(
      z.object({
        text: z.string().min(2).max(50_000),
        type: z.enum(QuestionType),
        options: z
          .array(z.object({ option: z.string().min(1), text: z.string().min(1) }))
          .nullable()
          .optional(),
        correctAnswer: z.string().nullable().optional(),
        rubric: z.string().nullable().optional(),
        points: z.coerce.number().positive().max(1_000).nullable().optional(),
        matchingPairs: z
          .object({ left_items: z.array(z.string()), right_items: z.array(z.string()) })
          .nullable()
          .optional()
      })
    )
    .min(1)
    .max(50)
});

export type UpdateGeneratedQuestionsDto = z.infer<typeof updateGeneratedQuestionsSchema>;
