/**
 * Credit transaction types used throughout the system.
 *
 * - **INITIAL**: Initial free credit given when a new institute is created.
 * - **PURCHASE**: Credit added via a purchase (e.g., buying a credit package).
 * - **SUBSCRIPTION**: Credit added via subscription renewal.
 * - **REFUND**: Credit returned to the institution (e.g., payment reversal).
 * - **PROMO**: Credit granted by a campaign or checkout bonus.
 * - **REFERRAL**: Credit granted to a referring institute.
 */
export enum CreditTransactionType {
  /** Initial free credit (e.g., FREE_CREDIT_FOR_NEW_INSTITUTE) */
  INITIAL = 1,

  /** Credit added by a purchase */
  PURCHASE = 2,

  /** Credit added by subscription */
  SUBSCRIPTION = 3,

  /** Credit refunded */
  REFUND = 4,

  /** Credit granted by a promo campaign */
  PROMO = 5,

  /** Credit granted by a referral reward */
  REFERRAL = 6,
}

/** Frontend-facing credit transaction record (matches backend CreditTransaction entity). */
export interface CreditTransaction {
  id: string;
  instituteId: string;
  amount: number;
  type: CreditTransactionType;
  reason?: string;
  referenceId?: string;
  creationTime: number;
}
