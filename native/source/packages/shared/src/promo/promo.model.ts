import {
  PromoAppliesTo,
  PromoBenefitStatus,
  PromoCodeKind,
  PromoDuration,
  PromoRedemptionStatus,
  PromoRewardType
} from "./promo.enums";

export interface PromoCampaignDto {
  id: string;
  name: string;
  description: string | null;
  rewardType: PromoRewardType;
  rewardValue: number;
  bonusCredit: number | null;
  duration: PromoDuration;
  durationCycles: number | null;
  appliesTo: PromoAppliesTo;
  packageIds: number[] | null;
  planIds: string[] | null;
  minAmountUsd: number | null;
  maxDiscountUsd: number | null;
  newCustomersOnly: boolean;
  newInstituteMaxAgeDays: number | null;
  allowedEmailDomains: string[] | null;
  allowedInstituteIds: string[] | null;
  deniedInstituteIds: string[] | null;
  maxRedemptions: number | null;
  maxPerInstitute: number;
  maxPerUser: number | null;
  startsAt: number | null;
  expiresAt: number | null;
  isActive: boolean;
  isArchived: boolean;
  isReferralProgram: boolean;
  referrerRewardType: PromoRewardType | null;
  referrerRewardValue: number | null;
  stackGroup: string | null;
  redeemedCount: number;
  code: string | null;
  codeId: string | null;
  codesCount?: number;
  creationTime: number | null;
}

export interface PromoCodeDto {
  id: string;
  campaignId: string;
  code: string;
  codeKind: PromoCodeKind;
  ownerInstituteId: string | null;
  assignedInstituteId: string | null;
  assignedEmail: string | null;
  isActive: boolean;
  expiresAtOverride: number | null;
  creationTime: number | null;
}

export interface PromoRedemptionDto {
  id: string;
  campaignId: string;
  codeId: string;
  code?: string;
  instituteId: string;
  instituteName?: string | null;
  userId: string | null;
  paymentId: string | null;
  benefitId: string | null;
  discountUsd: number;
  creditGranted: number;
  originalUsd: number;
  payableUsd: number;
  status: PromoRedemptionStatus;
  creationTime: number;
}

export interface InstitutePromoBenefitDto {
  id: string;
  instituteId: string;
  campaignId: string;
  campaignName?: string;
  rewardType: PromoRewardType;
  rewardValue: number;
  remainingCycles: number | null;
  status: PromoBenefitStatus;
  startsAt: number;
  expiresAt: number | null;
}

export interface PromoValidateResultDto {
  valid: boolean;
  code: string | null;
  campaignName: string | null;
  rewardType: PromoRewardType | null;
  rewardPreview: string | null;
  originalUsd: number | null;
  discountUsd: number | null;
  payableUsd: number | null;
  creditsGranted: number;
  bonusCredits: number;
  zeroCheckout: boolean;
  messageKey: string;
  expiresAt: number | null;
}

export interface PromoRedeemResultDto {
  creditsGranted: number;
  newBalance: number;
  campaignName: string;
  code: string;
}

export interface PromoReferralDto {
  enabled: boolean;
  code: string | null;
  shareUrl: string | null;
  referredCount: number;
  creditsEarned: number;
}

export interface CheckoutResultDto {
  threeDSHtml: string | null;
  zeroCheckout: boolean;
  creditsGranted?: number;
  paymentStatus?: "PENDING" | "UNKNOWN" | "APPROVED" | "NOT_APPROVED";
  payableAmount?: number;
  payableCurrency?: string;
  exchangeRate?: number | null;
  paytrDirectForm?: {
    action: string;
    fields: Record<string, string>;
  } | null;
}

export interface PaymentFeaturesDto {
  paytrRecurringEnabled: boolean;
  yearlyCheckoutEnabled?: boolean;
}
