import { z } from "zod";

export const EXAM_RESULT_MAILING_MAX_STUDENTS = 1000;

export const examResultMailingOptionsSchema = z
  .object({
    includeClassAverage: z.boolean().optional(),
    includeExamPaper: z.boolean().optional(),
    includeAiFeedback: z.boolean().optional()
  })
  .strict();

export const sendExamResultsSchema = z
  .object({
    studentIds: z.array(z.string().uuid()).max(EXAM_RESULT_MAILING_MAX_STUDENTS).optional(),
    options: examResultMailingOptionsSchema.optional()
  })
  .strict()
  .superRefine((value, context) => {
    if (value.studentIds && new Set(value.studentIds).size !== value.studentIds.length) {
      context.addIssue({ code: "custom", message: "studentIds must be unique", path: ["studentIds"] });
    }
  });

export const sendExamResultSchema = z
  .object({
    options: examResultMailingOptionsSchema.optional()
  })
  .strict();

export type ExamResultMailingOptions = z.infer<typeof examResultMailingOptionsSchema>;
export type SendExamResultsDto = z.infer<typeof sendExamResultsSchema>;
export type SendExamResultDto = z.infer<typeof sendExamResultSchema>;
