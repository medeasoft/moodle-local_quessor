import { z } from "zod";
import { InvitationRoleType } from "../invitation/invitation.schemas";

export const updateUserSchema = z.object({
  firstName: z.string().min(3, "firstname_min"),
  lastName: z.string().min(3, "lastname_min"),
  isActive: z.boolean(),
  courseIds: z.array(z.string().uuid()).optional(),
  role: z.enum([InvitationRoleType.TEACHER, InvitationRoleType.INSTITUTE_ADMIN]).optional()
});

export const registerDeviceSchema = z.object({
  fcmToken: z.string().min(1, "fcm_token_required"),
  deviceType: z.string().optional()
});

export const createUserSchema = z.object({
  email: z.string().email("email_validation"),
  firstName: z.string().min(3, "firstname_min"),
  lastName: z.string().min(3, "lastname_min"),
  isActive: z.boolean(),
  roleIds: z.array(z.string().uuid("role_uuid")).min(1, "role_required")
});

export const createSystemUserSchema = createUserSchema.extend({
  institutionId: z.string().uuid("institute_id_required")
});

// Type exports
export type UpdateUserDto = z.infer<typeof updateUserSchema>;
export type CreateUserDto = z.infer<typeof createUserSchema>;
export type CreateSystemUserDto = z.infer<typeof createSystemUserSchema>;
export type RegisterDeviceDto = z.infer<typeof registerDeviceSchema>;
