import { z } from "zod";

export enum InvitationRoleType {
  TEACHER = "TEACHER",
  INSTITUTE_ADMIN = "INSTITUTE_ADMIN"
}

export const inviteUserSchema = z.object({
  email: z.email("invalid_email"),
  role: z.enum([InvitationRoleType.TEACHER, InvitationRoleType.INSTITUTE_ADMIN])
});

export const acceptInvitationSchema = z.object({
  token: z.string().min(1, "token_required"),
  password: z.string().min(6, "password_min"),
  firstName: z.string().min(2),
  lastName: z.string().min(2)
});

export type InviteUserDto = z.infer<typeof inviteUserSchema>;
export type AcceptInvitationDto = z.infer<typeof acceptInvitationSchema>;

// Response DTOs
export interface InviteUserResponseDto {
  invitationId: string;
}

export interface AcceptInvitationResponseDto {
  userId: string;
}

export interface PendingInvitationDto {
  id: string;
  email: string;
  role: InvitationRoleType;
  expiresAt: number;
  creationTime: number | null;
}