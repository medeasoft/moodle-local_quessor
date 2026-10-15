import { z } from "zod";

export const runEvaluationSchema = z
  .object({
    studentIds: z.array(z.string().trim().min(1).max(100)).max(1000).optional(),
    bypassCache: z.boolean().optional()
  })
  .strict()
  .superRefine((value, context) => {
    if (value.studentIds && new Set(value.studentIds).size !== value.studentIds.length) {
      context.addIssue({ code: "custom", message: "studentIds must be unique", path: ["studentIds"] });
    }
  });

export type RunEvaluationDto = z.infer<typeof runEvaluationSchema>;

export const extractAnswersBodySchema = z
  .object({
    uploadType: z.enum(["bulk", "student"]).default("bulk"),
    studentId: z.string().uuid().optional()
  })
  .strict()
  .superRefine((value, context) => {
    if (value.uploadType === "student" && !value.studentId) {
      context.addIssue({ code: "custom", path: ["studentId"], message: "studentId is required for student upload" });
    }
    if (value.uploadType === "bulk" && value.studentId) {
      context.addIssue({ code: "custom", path: ["studentId"], message: "studentId is not allowed for bulk upload" });
    }
  });

export type ExtractAnswersBodyDto = z.infer<typeof extractAnswersBodySchema>;
