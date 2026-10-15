import { z } from "zod";

// Registration schema (with confirmPassword for frontend)
export const registerSchema = z
  .object({
    email: z.string().email("email-validation"),
    password: z.string().min(6, "password_min"),
    confirmPassword: z.string().min(6, "password_min"),
    firstName: z.string().min(3, "firstname_min"),
    lastName: z.string().min(3, "lastname_min")
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: "password_mismatch",
    path: ["confirmPassword"]
  });

// Backend registration schema (without confirmPassword)
export const registerBackendSchema = z.object({
  email: z.string().email("email-validation"),
  password: z.string().min(6, "password_min"),
  firstName: z.string().min(3, "firstname_min"),
  lastName: z.string().min(3, "lastname_min")
});

export const updateProfileSchema = z.object({
  firstName: z.string().min(3, "firstname_min"),
  lastName: z.string().min(3, "lastname_min")
});

export const updateSecuritySettingsSchema = z.object({
  isTwoFactorEnabled: z.boolean()
});

// Alias for frontend compatibility
export const updateSecuritySchema = updateSecuritySettingsSchema;

export const passwordResetTokenSchema = z.object({
  email: z.string().email("email-validation")
});

export const resetPasswordSchema = z
  .object({
    email: z.string().email("email-validation"),
    token: z.string().length(6, "token_length"),
    newPassword: z.string().min(6, "password_min"),
    confirmPassword: z.string().min(6, "password_min")
  })
  .refine((data) => data.newPassword === data.confirmPassword, {
    message: "password_mismatch",
    path: ["confirmPassword"]
  });

// Backend reset password schema (without confirmPassword)
export const resetPasswordBackendSchema = z.object({
  email: z.string().email("email-validation"),
  token: z.string().length(6, "token_length"),
  newPassword: z.string().min(6, "password_min")
});

export const confirmEmailSchema = z.object({
  email: z.string().email("email-validation"),
  token: z.string().length(6, "token_length")
});

export const sendEmailChangeEmailSchema = z.object({
  newEmail: z.string().email("email-validation")
});

export const confirmNewEmailSchema = z.object({
  newEmail: z.string().email("email-validation"),
  token: z.string().length(6, "token_length")
});

export const changePasswordSchema = z
  .object({
    currentPassword: z.string().min(6, "password_min"),
    newPassword: z.string().min(6, "password_min"),
    confirmPassword: z.string().min(6, "password_min")
  })
  .refine((data) => data.newPassword === data.confirmPassword, {
    message: "password_mismatch",
    path: ["confirmPassword"]
  });

// Backend change password schema (without confirmPassword)
export const changePasswordBackendSchema = z.object({
  currentPassword: z.string().min(6, "password_min"),
  newPassword: z.string().min(6, "password_min")
});

export const setNewPasswordSchema = z
  .object({
    newPassword: z.string().min(6, "password_min"),
    confirmPassword: z.string().min(6, "password_min")
  })
  .refine((data) => data.newPassword === data.confirmPassword, {
    message: "password_mismatch",
    path: ["confirmPassword"]
  });

// Backend set new password schema (without confirmPassword)
export const setNewPasswordBackendSchema = z.object({
  newPassword: z.string().min(6, "password_min")
});

export const linkProviderSchema = z.object({
  token: z.string().min(1, "token_required")
});

export const unlinkProviderSchema = z.object({
  socialAccountId: z.string().min(1, "social_account_id_required")
});

// Frontend specific schemas
export const forgotPasswordSchema = z.object({
  email: z.string().email("email-validation")
});

export const changeEmailSchema = z.object({
  email: z.string().email("email-validation")
});

export const setPasswordSchema = z
  .object({
    newPassword: z.string().min(6, "password_min"),
    confirmPassword: z.string().min(6, "password_min")
  })
  .refine((data) => data.newPassword === data.confirmPassword, {
    message: "password_mismatch",
    path: ["confirmPassword"]
  });

// Type exports
export type RegisterDto = z.infer<typeof registerSchema>;
export type RegisterBackendDto = z.infer<typeof registerBackendSchema>;
export type UpdateProfileDto = z.infer<typeof updateProfileSchema>;
export type UpdateSecuritySettingsDto = z.infer<typeof updateSecuritySettingsSchema>;
// Alias for frontend compatibility
export type UpdateSecurityDto = UpdateSecuritySettingsDto;
export type PasswordResetTokenDto = z.infer<typeof passwordResetTokenSchema>;
export type ResetPasswordDto = z.infer<typeof resetPasswordSchema>;
export type ResetPasswordBackendDto = z.infer<typeof resetPasswordBackendSchema>;
export type ConfirmEmailDto = z.infer<typeof confirmEmailSchema>;
export type SendEmailChangeEmailDto = z.infer<typeof sendEmailChangeEmailSchema>;
export type ConfirmNewEmailDto = z.infer<typeof confirmNewEmailSchema>;
export type ChangePasswordDto = z.infer<typeof changePasswordSchema>;
export type ChangePasswordBackendDto = z.infer<typeof changePasswordBackendSchema>;
export type SetNewPasswordDto = z.infer<typeof setNewPasswordSchema>;
export type SetNewPasswordBackendDto = z.infer<typeof setNewPasswordBackendSchema>;
export type LinkProviderDto = z.infer<typeof linkProviderSchema>;
export type UnlinkProviderDto = z.infer<typeof unlinkProviderSchema>;
export type ForgotPasswordDto = z.infer<typeof forgotPasswordSchema>;
export type ChangeEmailDto = z.infer<typeof changeEmailSchema>;
export type SetPasswordDto = z.infer<typeof setPasswordSchema>;
