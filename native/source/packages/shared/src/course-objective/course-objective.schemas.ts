import { z } from "zod";

export const createCourseObjectiveSchema = z.object({
  courseId: z.string().uuid(),
  code: z.string().min(1, "course_objective_code_required").max(50, "course_objective_code_max"),
  description: z.string().min(1, "course_objective_description_required"),
  order: z.coerce.number().int().min(0).max(9999).default(0),
});

export const updateCourseObjectiveSchema = z.object({
  code: z.string().min(1, "course_objective_code_required").max(50, "course_objective_code_max").optional(),
  description: z.string().min(1, "course_objective_description_required").optional(),
  order: z.coerce.number().int().min(0).max(9999).optional(),
});

export const saveCourseObjectiveProgramOutcomeMappingsSchema = z.object({
  mappings: z.array(
    z.object({
      courseObjectiveId: z.string().uuid(),
      programOutcomeId: z.string().uuid(),
      weightBps: z.number().int().min(1).max(10000),
    })
  ),
}).superRefine((dto, ctx) => {
  validateMappingWeights(dto.mappings, "courseObjectiveId", "programOutcomeId", ctx);
});

export const saveExamCourseObjectiveMappingsSchema = z.object({
  mappings: z.array(
    z.object({
      questionId: z.string().uuid(),
      courseObjectiveId: z.string().uuid(),
      weightBps: z.number().int().min(1).max(10000).optional(),
    })
  ),
}).superRefine((dto, ctx) => {
  const pairs = new Set<string>();
  dto.mappings.forEach((mapping, index) => {
    const pair = `${mapping.questionId}|${mapping.courseObjectiveId}`;
    if (pairs.has(pair)) {
      ctx.addIssue({ code: "custom", path: ["mappings", index], message: "duplicate_objective_mapping" });
    }
    pairs.add(pair);
  });
});

function validateMappingWeights<T extends { weightBps: number }>(
  mappings: T[], groupKey: keyof T, itemKey: keyof T, ctx: z.RefinementCtx
) {
  const totals = new Map<unknown, number>();
  const pairs = new Set<string>();
  mappings.forEach((mapping, index) => {
    const pair = `${mapping[groupKey]}|${mapping[itemKey]}`;
    if (pairs.has(pair)) ctx.addIssue({ code: "custom", path: ["mappings", index], message: "duplicate_objective_mapping" });
    pairs.add(pair);
    totals.set(mapping[groupKey], (totals.get(mapping[groupKey]) ?? 0) + mapping.weightBps);
  });
  for (const total of totals.values()) {
    if (total !== 10000) ctx.addIssue({ code: "custom", path: ["mappings"], message: "objective_weights_must_total_100" });
  }
}

export type CreateCourseObjectiveDto = z.infer<typeof createCourseObjectiveSchema>;
export type UpdateCourseObjectiveDto = z.infer<typeof updateCourseObjectiveSchema>;
export type SaveCourseObjectiveProgramOutcomeMappingsDto = z.infer<typeof saveCourseObjectiveProgramOutcomeMappingsSchema>;
export type SaveExamCourseObjectiveMappingsDto = z.infer<typeof saveExamCourseObjectiveMappingsSchema>;
