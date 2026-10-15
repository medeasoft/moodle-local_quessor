import { z } from "zod";

export const checkoutPaymentSchema = z.object({
    idempotencyKey: z.string().uuid().optional(),
    cardName: z.string().max(100, "invalid_card_name").optional().or(z.literal("")),
    cardNumber: z.string().max(19, "invalid_card_number").optional().or(z.literal("")),
    expiry: z.string().optional().or(z.literal("")),
    cvc: z.string().max(4, "invalid_cvc").optional().or(z.literal("")),
    packageId: z.number(),
    quantity: z.number().int().min(1, "invalid_quantity").max(100, "invalid_quantity"),
    locale: z.enum(["en", "tr", "de", "fr", "es", "bg"]).default("en").optional(),
    promoCode: z.string().trim().max(32).optional().or(z.literal("")),

    // fatura bilgileri
    invoiceType: z.enum(["INDIVIDUAL", "CORPORATE"]).default("INDIVIDUAL").optional(),
    name: z.string().min(2, "invalid_name").max(100, "invalid_name"),
    surname: z.string().min(2, "invalid_surname").max(100, "invalid_surname"),
    phoneNumber: z.string().min(10, "invalid_phone_number").max(10, "invalid_phone_number"),
    address: z.string().min(5, "invalid_address").max(255, "invalid_address"),
    vknOrTckn: z.string().trim()
        .min(10, "invalid_vkn_or_tckn").max(11, "invalid_vkn_or_tckn").or(z.literal(""))
        .optional(),
    vergiDairesi: z.string().trim()
        .min(2, "invalid_vergi_dairesi").max(100, "invalid_vergi_dairesi").or(z.literal(""))
        .optional(),
    firmaAdi: z.string().trim()
        .min(2, "invalid_firma_adi").max(100, "invalid_firma_adi").or(z.literal(""))
        .optional(),
}).superRefine((data, ctx) => {
    if (data.invoiceType === "CORPORATE") {
        if (!data.vknOrTckn || data.vknOrTckn.length < 10) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "invalid_vkn_or_tckn",
                path: ["vknOrTckn"]
            });
        }
        if (!data.vergiDairesi || data.vergiDairesi.length < 2) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "invalid_vergi_dairesi",
                path: ["vergiDairesi"]
            });
        }
        if (!data.firmaAdi || data.firmaAdi.length < 2) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "invalid_firma_adi",
                path: ["firmaAdi"]
            });
        }
    }
});

export type CheckoutPaymentDto = z.infer<typeof checkoutPaymentSchema>;

export const subscribePaymentSchema = z.object({
    idempotencyKey: z.string().uuid().optional(),
    planId: z.string(),
    isYearly: z.boolean(),
    autoRenew: z.boolean().default(false),
    locale: z.enum(["en", "tr", "de", "fr", "es", "bg"]).default("en").optional(),
    promoCode: z.string().trim().max(32).optional().or(z.literal("")),

    // fatura bilgileri
    invoiceType: z.enum(["INDIVIDUAL", "CORPORATE"]).default("INDIVIDUAL").optional(),
    name: z.string().min(2, "invalid_name").max(100, "invalid_name"),
    surname: z.string().min(2, "invalid_surname").max(100, "invalid_surname"),
    phoneNumber: z.string().min(10, "invalid_phone_number").max(10, "invalid_phone_number"),
    address: z.string().min(5, "invalid_address").max(255, "invalid_address"),
    vknOrTckn: z.string().trim()
        .min(10, "invalid_vkn_or_tckn").max(11, "invalid_vkn_or_tckn").or(z.literal(""))
        .optional(),
    vergiDairesi: z.string().trim()
        .min(2, "invalid_vergi_dairesi").max(100, "invalid_vergi_dairesi").or(z.literal(""))
        .optional(),
    firmaAdi: z.string().trim()
        .min(2, "invalid_firma_adi").max(100, "invalid_firma_adi").or(z.literal(""))
        .optional(),
}).superRefine((data, ctx) => {
    if (data.invoiceType === "CORPORATE") {
        if (!data.vknOrTckn || data.vknOrTckn.length < 10) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "invalid_vkn_or_tckn",
                path: ["vknOrTckn"]
            });
        }
        if (!data.vergiDairesi || data.vergiDairesi.length < 2) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "invalid_vergi_dairesi",
                path: ["vergiDairesi"]
            });
        }
        if (!data.firmaAdi || data.firmaAdi.length < 2) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: "invalid_firma_adi",
                path: ["firmaAdi"]
            });
        }
    }
});

export type SubscribePaymentDto = z.infer<typeof subscribePaymentSchema>;

export interface PaymentHistoryItemDto {
    id: string;
    creationTime: number;
    credit: number;
    totalPrice: number;
    basePriceUsd: number;
    paymentCurrency: string;
    paymentAmount: number;
    exchangeRate: number | null;
    cardCountry: string | null;
    status: string;
    subscriptionPlanId?: string | null;
    isYearly?: boolean | null;
    isSubscriptionRenewal: boolean;
    originalPriceUsd?: number | null;
    discountUsd?: number | null;
    promoCode?: string | null;
    catalogVersion?: number | null;
}

export interface BillingCatalogOfferDto {
    id: string;
    offer_key: string;
    product_type: "CREDIT_PACKAGE" | "SUBSCRIPTION";
    product_id: string;
    billing_period_months: number;
    version: number;
    credits_per_period: number;
    price_usd_minor: number;
    price_try_minor: number | null;
    status: "DRAFT" | "PUBLISHED" | "RETIRED";
    created_at?: string;
    published_at?: string | null;
    retired_at?: string | null;
}
