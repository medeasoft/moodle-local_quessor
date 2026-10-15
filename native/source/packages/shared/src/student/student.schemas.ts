import { z } from "zod";

export const createStudentSchema = z.object({
    firstName: z.string().trim()
        .min(2, "student_first_name_min")
        .max(100, "student_first_name_max")
        .or(z.literal(""))
        .optional(),

    lastName: z.string().trim()
        .min(2, "student_last_name_min")
        .max(100, "student_last_name_max")
        .or(z.literal(""))
        .optional(),

    email: z
        .email("email_validation")
        .or(z.literal(""))
        .optional(),

    primaryId: z.string().min(1, "student_primary_id_required"),
    secondaryId: z.string().optional().nullable(),
    courseId: z.uuid("course_uuid_invalid")
});

export const updateStudentSchema = z.object({
    firstName: z.string().trim()
        .min(2, "student_first_name_min")
        .max(100, "student_first_name_max")
        .or(z.literal(""))
        .optional(),

    lastName: z.string().trim()
        .min(2, "student_last_name_min")
        .max(100, "student_last_name_max")
        .or(z.literal(""))
        .optional(),

    email: z
        .email("email_validation")
        .or(z.literal(""))
        .optional(),

    primaryId: z.string().min(1, "student_primary_id_required"),
    secondaryId: z.string().optional().nullable()
});

export const bulkCreateStudentSchema = z.object({
    students: z.array(createStudentSchema).min(1, "students_array_min"),
    courseId: z.uuid("course_uuid_invalid")
})

export const bulkDeleteStudentSchema = z.object({
    ids: z.array(z.string().uuid()).min(1, "ids_array_min")
});

export const bulkMoveStudentSchema = z.object({
    ids: z.array(z.string().uuid()).min(1, "ids_array_min"),
    targetCourseId: z.string().uuid("course_uuid_invalid")
});

export const bulkCopyStudentSchema = z.object({
    ids: z.array(z.string().uuid()).min(1, "ids_array_min"),
    targetCourseId: z.string().uuid("course_uuid_invalid")
});

export type CreateStudentDto = z.infer<typeof createStudentSchema>;
export type UpdateStudentDto = z.infer<typeof updateStudentSchema>;
export type BulkCreateStudentDto = z.infer<typeof bulkCreateStudentSchema>;
export type BulkDeleteStudentDto = z.infer<typeof bulkDeleteStudentSchema>;
export type BulkMoveStudentDto = z.infer<typeof bulkMoveStudentSchema>;
export type BulkCopyStudentDto = z.infer<typeof bulkCopyStudentSchema>;