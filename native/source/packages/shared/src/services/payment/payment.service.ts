import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import { BillingCatalogOfferDto, CheckoutPaymentDto, SubscribePaymentDto, PaymentHistoryItemDto } from "@fullstack/shared/payment";
import { CheckoutResultDto, PaymentFeaturesDto } from "../../promo/promo.model";

export class PaymentService {
  constructor(private readonly httpClient: IHttpClient) { }

  async Checkout(dto: CheckoutPaymentDto, config?: HttpRequestConfig) {
    return this.httpClient.post<CheckoutResultDto>("/payment/checkout", dto, config);
  }

  async SubscribeCheckout(dto: SubscribePaymentDto, config?: HttpRequestConfig) {
    return this.httpClient.post<CheckoutResultDto>("/payment/subscribe-checkout", dto, config);
  }

  async GetFeatures(config?: HttpRequestConfig) {
    return this.httpClient.get<PaymentFeaturesDto>("/payment/features", config);
  }

  async GetCatalog(config?: HttpRequestConfig) {
    return this.httpClient.get<BillingCatalogOfferDto[]>("/payment/catalog", config);
  }

  async AdminGetCatalog(config?: HttpRequestConfig) {
    return this.httpClient.get<BillingCatalogOfferDto[]>("/payment/admin/catalog", config);
  }

  async AdminCreateCatalogDraft(dto: { offerKey: string; usdMinor: number; tryMinor: number; credits: number }, config?: HttpRequestConfig) {
    return this.httpClient.post<{ id: string; offerKey: string; version: number }>("/payment/admin/catalog/drafts", dto, config);
  }

  async AdminPublishCatalogDraft(offerId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<{ published: boolean }>(`/payment/admin/catalog/${offerId}/publish`, {}, config);
  }

  async CancelAutoRenew(config?: HttpRequestConfig) {
    return this.httpClient.post<{ cancelled: boolean }>("/payment/subscription/cancel-auto-renew", {}, config);
  }

  async GetSubscriptionPriceChangeNotice(config?: HttpRequestConfig) {
    return this.httpClient.get<{
      id: string; status: "PENDING" | "ACCEPTED" | "DECLINED"; notice_deadline_at: string;
      offer_key: string; version: number; credits_per_period: number;
      price_usd_minor: number; price_try_minor: number | null;
    } | null>("/payment/subscription/price-change-notice", config);
  }

  async RespondToSubscriptionPriceChangeNotice(noticeId: string, accept: boolean, config?: HttpRequestConfig) {
    return this.httpClient.post<{ accepted: boolean }>(`/payment/subscription/price-change-notice/${noticeId}/respond`, { accept }, config);
  }

  async GetHistory(instituteId?: string, config?: HttpRequestConfig) {
    const query = instituteId ? `?instituteId=${encodeURIComponent(instituteId)}` : "";
    return this.httpClient.get<PaymentHistoryItemDto[]>(`/payment/history${query}`, config);
  }

  async AdminGetOperations(query = "", config?: HttpRequestConfig) {
    return this.httpClient.get<{
      items: Array<{
        id: string; created_at: number; status: number; amount: number; currency: string; credit: number;
        plan_id: string | null; catalog_version: number | null; is_renewal: boolean; merchant_oid: string | null;
        manual_resolution_outcome: string | null; manual_resolution_evidence: string | null; manual_resolved_at: string | null;
        institute_id: string; institute_name: string; refund_id: string | null; refund_status: string | null;
        refund_amount_minor: number | null; refund_currency: string | null; refund_reference: string | null;
        chargeback_id: string | null; chargeback_status: string | null; chargeback_receivable_minor: number | null;
      }>;
      total: number; page: number; pageSize: number;
    }>(`/payment/admin/operations${query ? `?${query}` : ""}`, config);
  }

  async AdminRefundPayment(paymentId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<{ refundId: string; status: string }>(`/payment/admin/refunds/${paymentId}`, {}, config);
  }

  async AdminResolveUnknownPayment(paymentId: string, dto: { outcome: "APPROVED" | "NOT_APPROVED"; evidence: string }, config?: HttpRequestConfig) {
    return this.httpClient.post<{ paymentId: string; status: string }>(`/payment/admin/payments/${paymentId}/resolve-unknown`, dto, config);
  }

  async AdminResolveUnknownRefund(refundId: string, dto: { succeeded: boolean; evidence: string }, config?: HttpRequestConfig) {
    return this.httpClient.post<{ refundId: string; status: string }>(`/payment/admin/refunds/${refundId}/resolve-unknown`, dto, config);
  }

  async AdminGetRefundOperations(query = "", config?: HttpRequestConfig) {
    return this.httpClient.get<{
      items: Array<{
        id: string; payment_id: string; reference_no: string; amount_minor: number; currency: string;
        status: string; created_at: string; updated_at: string; resolved_at: string | null;
        manual_resolution_evidence: string | null; manual_resolved_at: string | null;
        provider_request_started_at: string | null; merchant_oid: string | null; payment_amount: number;
        payment_currency: string; institute_id: string; institute_name: string;
      }>;
      total: number; page: number; pageSize: number;
    }>(`/payment/admin/refunds${query ? `?${query}` : ""}`, config);
  }

  async AdminGetChargebackOperations(query = "", config?: HttpRequestConfig) {
    return this.httpClient.get<{
      items: Array<{
        id: string; payment_id: string; external_case_id: string; provider_net_reversed_minor: number;
        confirmed_refunds_minor: number; recoveries_minor: number; receivable_minor: number; status: string;
        created_at: string; resolved_at: string | null; payment_amount: number; payment_currency: string;
        merchant_oid: string | null; institute_id: string; institute_name: string;
      }>;
      total: number; page: number; pageSize: number;
    }>(`/payment/admin/chargebacks${query ? `?${query}` : ""}`, config);
  }

  async AdminApplyChargeback(dto: {
    paymentId: string; externalCaseId: string; providerNetReversedMinor: number;
    confirmedRefundsMinor: number; recoveriesMinor: number;
  }, config?: HttpRequestConfig) {
    return this.httpClient.post<{ caseId: string; receivableMinor: number; applied: boolean }>("/payment/admin/chargebacks", dto, config);
  }

  async AdminResolveChargeback(caseId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<{ resolved: boolean }>(`/payment/admin/chargebacks/${caseId}/resolve`, {}, config);
  }

}
