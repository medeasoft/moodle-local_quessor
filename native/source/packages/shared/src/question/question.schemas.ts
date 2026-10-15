import { z } from "zod";
import { DEFAULT_FEEDBACK_LANGUAGE, feedbackLanguageSchema } from "../exam/exam.schemas";
import { QuestionType } from "./question.model";

export const questionOptionSchema = z.object({
  option: z.string().trim().min(1, "question_option_key_min").max(50),
  text: z.string()
});

export const createQuestionSchema = z.object({
  name: z.string().min(1, "question_name_min").max(255, "question_name_max"),
  text: z.string().min(2, "question_text_min"),
  order: z.coerce.number().int().min(1, "question_order_min"),
  point: z.coerce.number().min(0, "question_point_value_min"),
  type: z.enum(QuestionType),
  options: z.array(questionOptionSchema).optional().nullable(),
  correctAnswer: z.string().optional().nullable(),
  questionGradingInstructions: z.string().optional().nullable(),
  examId: z.uuid("exam_uuid_invalid"),
  pageIndex: z.coerce.number().int().default(1),
  contextId: z.string().uuid().optional().nullable(),
  parentId: z.string().uuid().optional().nullable(),
  isGroup: z.boolean().default(false),
  label: z.string().optional().nullable()
});

export const updateQuestionSchema = z.object({
  name: z.string().min(1, "question_name_min").max(255, "question_name_max").optional(),
  text: z.string().min(2, "question_text_min").optional(),
  order: z.coerce.number().int().min(1, "question_order_min").optional(),
  point: z.coerce.number().min(0, "question_point_min").optional(),
  type: z.enum(QuestionType).optional(),
  options: z.array(questionOptionSchema).optional().nullable(),
  correctAnswer: z.string().optional().nullable(),
  questionGradingInstructions: z.string().optional().nullable(),
  contextId: z.string().uuid().optional().nullable(),
  parentId: z.string().uuid().optional().nullable(),
  isGroup: z.boolean().optional(),
  label: z.string().optional().nullable()
});

export const rubricDetailSchema = z.enum(["concise", "detailed", "point_based"]);

/** Options selected by the teacher before AI prepares an answer key and/or rubric. */
export const generateQuestionAssessmentSchema = z
  .object({
    /** Present when generating materials for an already-saved question. */
    questionId: z.string().uuid().optional().nullable(),
    text: z.string().min(2, "question_text_min").max(50_000),
    type: z.enum(QuestionType),
    point: z.coerce.number().min(0, "question_point_min"),
    contextText: z.string().max(50_000).optional().nullable(),
    generateCorrectAnswer: z.boolean(),
    generateRubric: z.boolean(),
    rubricDetail: rubricDetailSchema.optional().default("detailed"),
    language: feedbackLanguageSchema.optional().default(DEFAULT_FEEDBACK_LANGUAGE),
    additionalInstructions: z.string().max(2_000).optional().nullable(),
    examId: z.string().uuid().optional().nullable()
  })
  .refine((value) => value.generateCorrectAnswer || value.generateRubric, {
    message: "ai_assessment_select_output",
    path: ["generateCorrectAnswer"]
  });

export type CreateQuestionDto = z.infer<typeof createQuestionSchema>;
export type UpdateQuestionDto = z.infer<typeof updateQuestionSchema>;
export type GenerateQuestionAssessmentDto = z.infer<typeof generateQuestionAssessmentSchema>;
export type RubricDetail = z.infer<typeof rubricDetailSchema>;

export interface GeneratedQuestionAssessmentDto {
  correctAnswer?: string;
  questionGradingInstructions?: string;
}
