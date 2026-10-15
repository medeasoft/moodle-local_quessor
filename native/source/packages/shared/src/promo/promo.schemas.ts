import { z } from "zod";
import {
  PromoAppliesTo,
  PromoCodeKind,
  PromoDuration,
  PromoRewardType,
  PROMO_CODE_PATTERN
} from "./promo.enums";

export const promoCodeInputSchema = z
  .string()
  .trim()
  .min(4, "promo.code_min_length")
  .max(32, "promo.code_max_length")
  .transform((value) => value.toUpperCase().replace(/\s+/g, ""));

export const validatePromoSchema = z.object({
  code: promoCodeInputSchema,
  packageId: z.number().int().optional(),
  planId: z.string().optional(),
  isYearly: z.boolean().optional(),
  quantity: z.number().int().min(1).max(100).optional()
});

export type ValidatePromoDto = z.infer<typeof validatePromoSchema>;

export const redeemPromoSchema = z.object({
  code: promoCodeInputSchema
});

export type RedeemPromoDto = z.infer<typeof redeemPromoSchema>;

const optionalStringArray = z.array(z.string().trim().min(1)).nullable().optional();
const optionalUuidArray = z.array(z.string().uuid()).nullable().optional();

function refineCampaignDates(
  data: { startsAt?: number | null; expiresAt?: number | null },
  ctx: z.RefinementCtx
) {
  if (data.startsAt != null && data.expiresAt != null && data.expiresAt <= data.startsAt) {
    ctx.addIssue({ code: z.ZodIssueCode.custom, message: "promo.date_range_invalid", path: ["expiresAt"] });
  }
}

export const createPromoCampaignSchema = z
  .object({
    name: z.string().trim().min(2, "promo.name_required").max(120, "promo.name_required"),
    description: z.string().trim().max(1000).nullable().optional(),
    rewardType: z.nativeEnum(PromoRewardType),
    rewardValue: z.number().positive("promo.reward_invalid"),
    bonusCredit: z.number().min(0).nullable().optional(),
    duration: z.nativeEnum(PromoDuration).default(PromoDuration.ONCE),
    durationCycles: z.number().int().min(1).max(120).nullable().optional(),
    appliesTo: z.nativeEnum(PromoAppliesTo).default(PromoAppliesTo.ALL),
    packageIds: z.array(z.number().int()).nullable().optional(),
    planIds: optionalStringArray,
    minAmountUsd: z.number().min(0).nullable().optional(),
    maxDiscountUsd: z.number().min(0).nullable().optional(),
    newCustomersOnly: z.boolean().optional().default(false),
    newInstituteMaxAgeDays: z.number().int().min(1).max(365).nullable().optional(),
    allowedEmailDomains: optionalStringArray,
    allowedInstituteIds: optionalUuidArray,
    deniedInstituteIds: optionalUuidArray,
    maxRedemptions: z.number().int().min(1).nullable().optional(),
    maxPerInstitute: z.number().int().min(1).max(100).optional().default(1),
    maxPerUser: z.number().int().min(1).max(100).nullable().optional(),
    startsAt: z.number().int().nullable().optional(),
    expiresAt: z.number().int().nullable().optional(),
    isReferralProgram: z.boolean().optional().default(false),
    referrerRewardType: z.nativeEnum(PromoRewardType).nullable().optional(),
    referrerRewardValue: z.number().positive().nullable().optional(),
    sharedCode: z
      .string()
      .trim()
      .optional()
      .transform((value) => (value ? value.toUpperCase().replace(/\s+/g, "") : undefined))
  })
  .superRefine((data, ctx) => {
    if (data.rewardType === PromoRewardType.PERCENT_OFF || data.rewardType === PromoRewardType.PERCENT_OFF_AND_CREDIT) {
      if (data.rewardValue < 1 || data.rewardValue > 100) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: "promo.percent_range", path: ["rewardValue"] });
      }
    }
    if (data.rewardType === PromoRewardType.PERCENT_OFF_AND_CREDIT && !(data.bonusCredit && data.bonusCredit > 0)) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, message: "promo.bonus_required", path: ["bonusCredit"] });
    }
    if (data.duration === PromoDuration.REPEATING && !data.durationCycles) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, message: "promo.duration_cycles_required", path: ["durationCycles"] });
    }
    if (data.sharedCode && !PROMO_CODE_PATTERN.test(data.sharedCode)) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, message: "promo.code_format_invalid", path: ["sharedCode"] });
    }
    if (data.isReferralProgram && data.referrerRewardType && !data.referrerRewardValue) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "promo.referrer_reward_required",
        path: ["referrerRewardValue"]
      });
    }
    refineCampaignDates(data, ctx);
  });

export type CreatePromoCampaignDto = z.infer<typeof createPromoCampaignSchema>;

const campaignFieldsSchema = z.object({
  name: z.string().trim().min(2).max(120).optional(),
  description: z.string().trim().max(1000).nullable().optional(),
  rewardType: z.nativeEnum(PromoRewardType).optional(),
  rewardValue: z.number().positive().optional(),
  bonusCredit: z.number().min(0).nullable().optional(),
  duration: z.nativeEnum(PromoDuration).optional(),
  durationCycles: z.number().int().min(1).max(120).nullable().optional(),
  appliesTo: z.nativeEnum(PromoAppliesTo).optional(),
  packageIds: z.array(z.number().int()).nullable().optional(),
  planIds: z.array(z.string().trim().min(1)).nullable().optional(),
  minAmountUsd: z.number().min(0).nullable().optional(),
  maxDiscountUsd: z.number().min(0).nullable().optional(),
  newCustomersOnly: z.boolean().optional(),
  newInstituteMaxAgeDays: z.number().int().min(1).max(365).nullable().optional(),
  allowedEmailDomains: z.array(z.string().trim().min(1)).nullable().optional(),
  allowedInstituteIds: z.array(z.string().uuid()).nullable().optional(),
  deniedInstituteIds: z.array(z.string().uuid()).nullable().optional(),
  maxRedemptions: z.number().int().min(1).nullable().optional(),
  maxPerInstitute: z.number().int().min(1).max(100).optional(),
  maxPerUser: z.number().int().min(1).max(100).nullable().optional(),
  startsAt: z.number().int().nullable().optional(),
  expiresAt: z.number().int().nullable().optional(),
  isReferralProgram: z.boolean().optional(),
  referrerRewardType: z.nativeEnum(PromoRewardType).nullable().optional(),
  referrerRewardValue: z.number().positive().nullable().optional(),
  isActive: z.boolean().optional(),
  isArchived: z.boolean().optional()
});

export const updatePromoCampaignSchema = campaignFieldsSchema.superRefine(refineCampaignDates);

export type UpdatePromoCampaignDto = z.infer<typeof updatePromoCampaignSchema>;

export const createPromoCodeSchema = z.object({
  code: z
    .string()
    .trim()
    .min(4, "promo.code_min_length")
    .max(32, "promo.code_max_length")
    .transform((value) => value.toUpperCase().replace(/\s+/g, ""))
    .refine((value) => PROMO_CODE_PATTERN.test(value), "promo.code_format_invalid"),
  codeKind: z.nativeEnum(PromoCodeKind).optional().default(PromoCodeKind.SHARED),
  assignedInstituteId: z.string().uuid().nullable().optional(),
  assignedEmail: z.string().email().nullable().optional(),
  expiresAtOverride: z.number().int().nullable().optional()
});

export type CreatePromoCodeDto = z.infer<typeof createPromoCodeSchema>;

export const bulkPromoCodesSchema = z.object({
  count: z.number().int().min(1).max(5000),
  prefix: z
    .string()
    .trim()
    .min(2)
    .max(12)
    .transform((value) => value.toUpperCase().replace(/[^A-Z0-9]/g, ""))
});

export type BulkPromoCodesDto = z.infer<typeof bulkPromoCodesSchema>;
