import { z } from "zod";

const optionalContactEmail = z.preprocess(
  (value) => (typeof value === "string" && value.trim() === "" ? null : value),
  z.email("email-validation").nullable().optional()
);

export const createInstituteSchema = z
  .object({
    name: z.string().min(2, "institute_create_name_length").max(100, "institute_create_name_length"),
    ownerEmail: z.email("email-validation")
  })

export const updateInstituteSchema = z.object({
  name: z.string().min(2, "institute_update_name_length").max(100, "institute_update_name_length").optional(),
  credit: z.number().int().min(0).optional(),
  contactEmail: optionalContactEmail
});

export const updateInstituteByInstituteSchema = z.object({
  name: z.string().min(2, "institute_update_name_length").max(100, "institute_update_name_length").optional(),
  contactEmail: optionalContactEmail
}).strict();

// Type exports
export type CreateInstituteDto = z.infer<typeof createInstituteSchema>;
export type UpdateInstituteDto = z.infer<typeof updateInstituteSchema>;
export type UpdateInstituteByInstituteDto = z.infer<typeof updateInstituteByInstituteSchema>;
