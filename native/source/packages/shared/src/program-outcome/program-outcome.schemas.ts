import { z } from "zod";

export const createProgramOutcomeSchema = z.object({
  courseId: z.string().uuid(),
  code: z.string().min(1, "program_outcome_code_required").max(50, "program_outcome_code_max"),
  description: z.string().min(1, "program_outcome_description_required"),
  order: z.coerce.number().int().min(0).max(9999).default(0),
});

export const updateProgramOutcomeSchema = z.object({
  code: z.string().min(1, "program_outcome_code_required").max(50, "program_outcome_code_max").optional(),
  description: z.string().min(1, "program_outcome_description_required").optional(),
  order: z.coerce.number().int().min(0).max(9999).optional(),
});

export const saveExamMappingsSchema = z.object({
  mappings: z.array(
    z.object({
      questionId: z.string().uuid(),
      programOutcomeId: z.string().uuid(),
    })
  ),
});

export type CreateProgramOutcomeDto = z.infer<typeof createProgramOutcomeSchema>;
export type UpdateProgramOutcomeDto = z.infer<typeof updateProgramOutcomeSchema>;
export type SaveExamMappingsDto = z.infer<typeof saveExamMappingsSchema>;
