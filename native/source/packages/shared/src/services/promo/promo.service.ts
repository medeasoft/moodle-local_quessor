import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import { PagedResponse } from "../../types/page.dto";
import {
  BulkPromoCodesDto,
  CreatePromoCampaignDto,
  CreatePromoCodeDto,
  RedeemPromoDto,
  UpdatePromoCampaignDto,
  ValidatePromoDto
} from "../../promo/promo.schemas";
import {
  CheckoutResultDto,
  InstitutePromoBenefitDto,
  PromoCampaignDto,
  PromoCodeDto,
  PromoRedeemResultDto,
  PromoRedemptionDto,
  PromoReferralDto,
  PromoValidateResultDto
} from "../../promo/promo.model";

export class PromoService {
  constructor(private readonly httpClient: IHttpClient) {}

  async Validate(dto: ValidatePromoDto, config?: HttpRequestConfig) {
    return this.httpClient.post<PromoValidateResultDto>("/promo/validate", dto, config);
  }

  async Redeem(dto: RedeemPromoDto, config?: HttpRequestConfig) {
    return this.httpClient.post<PromoRedeemResultDto>("/promo/redeem", dto, config);
  }

  async GetMyReferral(config?: HttpRequestConfig) {
    return this.httpClient.get<PromoReferralDto>("/promo/my-referral", config);
  }

  async GetMyBenefits(config?: HttpRequestConfig) {
    return this.httpClient.get<InstitutePromoBenefitDto[]>("/promo/my-benefits", config);
  }

  async AdminGetCampaigns(query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<PromoCampaignDto>>(
      `/promo/admin/campaigns${query ? `?${query}` : ""}`,
      config
    );
  }

  async AdminGetCampaign(id: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PromoCampaignDto>(`/promo/admin/campaigns/${id}`, config);
  }

  async AdminCreateCampaign(dto: CreatePromoCampaignDto, config?: HttpRequestConfig) {
    return this.httpClient.post<PromoCampaignDto>("/promo/admin/campaigns", dto, config);
  }

  async AdminUpdateCampaign(id: string, dto: UpdatePromoCampaignDto, config?: HttpRequestConfig) {
    return this.httpClient.put<PromoCampaignDto>(`/promo/admin/campaigns/${id}`, dto, config);
  }

  async AdminGetCodes(campaignId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PromoCodeDto[]>(`/promo/admin/campaigns/${campaignId}/codes`, config);
  }

  async AdminCreateCode(campaignId: string, dto: CreatePromoCodeDto, config?: HttpRequestConfig) {
    return this.httpClient.post<PromoCodeDto>(`/promo/admin/campaigns/${campaignId}/codes`, dto, config);
  }

  async AdminGenerateUniqueCodes(campaignId: string, dto: BulkPromoCodesDto, config?: HttpRequestConfig) {
    return this.httpClient.post<{ codes: string[] }>(`/promo/admin/campaigns/${campaignId}/unique-codes`, dto, config);
  }

  async AdminGetRedemptions(campaignId: string, query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<PromoRedemptionDto>>(
      `/promo/admin/campaigns/${campaignId}/redemptions${query ? `?${query}` : ""}`,
      config
    );
  }

  async AdminDeactivateCode(codeId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<PromoCodeDto>(`/promo/admin/codes/${codeId}/deactivate`, undefined, config);
  }

  async AdminActivateCode(codeId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<PromoCodeDto>(`/promo/admin/codes/${codeId}/activate`, undefined, config);
  }

  async AdminDeleteCode(codeId: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<boolean>(`/promo/admin/codes/${codeId}`, config);
  }

  async AdminCancelBenefit(instituteId: string, benefitId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(
      `/promo/admin/institutes/${instituteId}/benefits/${benefitId}/cancel`,
      undefined,
      config
    );
  }

  async AdminGetInstituteBenefits(instituteId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<InstitutePromoBenefitDto[]>(`/promo/admin/institutes/${instituteId}/benefits`, config);
  }
}

export type { CheckoutResultDto };
