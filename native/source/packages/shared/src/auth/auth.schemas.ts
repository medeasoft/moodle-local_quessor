import { z } from "zod";

export const loginSchema = z.object({
  email: z.string().email("email-validation"),
  password: z.string().min(6, "password_min")
});

export const refreshSchema = z.object({
  refreshToken: z.string().uuid("refresh_token_uuid")
});

export const revokeSchema = z.object({
  refreshToken: z.string().min(6, "refresh_token_required")
});

export const providerLoginSchema = z.object({
  token: z.string().min(1, "token_required"),
  firstName: z.string().optional(),
  lastName: z.string().optional()
});

export const twoFactorSchema = z.object({
  code: z.string().length(6, "token_length"),
  email: z.string().email("email-validation")
});

export const refreshTwoFactorSchema = z.object({
  email: z.string().email("email-validation")
});

export const activateAccountSchema = z.object({
  token: z.string().min(1, "token_required"),
  password: z.string().min(6, "password_min")
});

// Type exports
export type LoginDto = z.infer<typeof loginSchema>;
export type RefreshDto = z.infer<typeof refreshSchema>;
export type RevokeDto = z.infer<typeof revokeSchema>;
export type ProviderLoginDto = z.infer<typeof providerLoginSchema>;
export type TwoFactorDto = z.infer<typeof twoFactorSchema>;
export type RefreshTwoFactorDto = z.infer<typeof refreshTwoFactorSchema>;
export type ActivateAccountDto = z.infer<typeof activateAccountSchema>;
