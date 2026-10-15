export enum PromoRewardType {
  PERCENT_OFF = "PERCENT_OFF",
  FIXED_OFF = "FIXED_OFF",
  CREDIT_GRANT = "CREDIT_GRANT",
  CREDIT_BONUS = "CREDIT_BONUS",
  PERCENT_OFF_AND_CREDIT = "PERCENT_OFF_AND_CREDIT"
}

export enum PromoDuration {
  ONCE = "ONCE",
  REPEATING = "REPEATING",
  FOREVER = "FOREVER"
}

export enum PromoAppliesTo {
  CREDIT_PACKAGE = "CREDIT_PACKAGE",
  SUBSCRIPTION = "SUBSCRIPTION",
  ALL = "ALL"
}

export enum PromoCodeKind {
  SHARED = "SHARED",
  UNIQUE = "UNIQUE",
  REFERRAL = "REFERRAL"
}

export enum PromoRedemptionStatus {
  PENDING = "PENDING",
  APPLIED = "APPLIED",
  RELEASED = "RELEASED"
}

export enum PromoBenefitStatus {
  ACTIVE = "ACTIVE",
  EXPIRED = "EXPIRED",
  CANCELED = "CANCELED"
}

export const PROMO_CODE_PATTERN = /^[A-Z0-9][A-Z0-9-]{3,31}$/;
export const MIN_CHARGEABLE_USD = 0.5;
export const PROMO_PENDING_TTL_MS = 30 * 60 * 1000;
export const RESERVED_PROMO_CODES = ["NULL", "UNDEFINED"] as const;
