import { z } from "zod";

export const createCourseSchema = z.object({
  name: z.string().min(2, "course_name_min").max(255, "course_name_max"),
  description: z.string().max(1000, "course_description_max").optional(),
  code: z.string().max(50, "course_code_max").optional(),
  isActive: z.boolean().default(true),
  teacherIds: z.array(z.uuid("teacher_uuid_invalid")).optional()
});

export const updateCourseSchema = z.object({
  name: z.string().min(2, "course_name_min").max(255, "course_name_max").optional(),
  description: z.string().max(1000, "course_description_max").optional(),
  code: z.string().max(50, "course_code_max").optional(),
  isActive: z.boolean().optional(),
  teacherIds: z.array(z.uuid("teacher_uuid_invalid")).optional()
});

export type CreateCourseDto = z.infer<typeof createCourseSchema>;
export type UpdateCourseDto = z.infer<typeof updateCourseSchema>;
