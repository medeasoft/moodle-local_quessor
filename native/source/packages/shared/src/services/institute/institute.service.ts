import { IHttpClient, HttpRequestConfig } from "../../api";
import { CreateInstituteDto, UpdateInstituteDto, InstituteDto, CreditTransaction } from "../../institute";
import { PagedResponse } from "@fullstack/shared/types";

export interface CreditHistoryExamGroup {
  examId: string;
  examTitle: string | null;
  courseName: string | null;
  totalAmount: number;
  features: { feature: string; amount: number }[];
  latestAt: number;
}

export interface CreditHistoryItem {
  id: string;
  type: "load" | "exam_usage" | "standalone_usage";
  transactionType?: number;
  amount: number;
  reason: string | null;
  examGroup: CreditHistoryExamGroup | null;
  createdAt: number;
}


export class InstituteService {
  constructor(private readonly httpClient: IHttpClient) {}

  async Create(dto: CreateInstituteDto, config?: HttpRequestConfig) {
    return this.httpClient.post<InstituteDto>("/institutes", dto, config);
  }

  async GetAll(query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<InstituteDto>>(`/institutes/all${query ? `?${query}` : ""}`, config);
  }

  async GetById(id: string, config?: HttpRequestConfig) {
    return this.httpClient.get<InstituteDto>(`/institutes/${id}`, config);
  }

  async Delete(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/institutes/${id}`, config);
  }

  async Update(id: string, dto: UpdateInstituteDto, config?: HttpRequestConfig) {
    return this.httpClient.put<InstituteDto>(`/institutes/${id}`, dto, config);
  }

  async GetMy(config?: HttpRequestConfig) {
    return this.httpClient.get<InstituteDto>("/institutes", config);
  }

  async UpdateMy(dto: UpdateInstituteDto, config?: HttpRequestConfig) {
    return this.httpClient.put<InstituteDto>("/institutes", dto, config);
  }

  async GetCreditTransactions(query?: string, instituteId?: string, config?: HttpRequestConfig) {
    const params = new URLSearchParams(query ?? "");
    if (instituteId) {
      params.set("instituteId", instituteId);
    }
    const queryString = params.toString();
    return this.httpClient.get<PagedResponse<CreditTransaction>>(
      `/institutes/credit-transactions${queryString ? `?${queryString}` : ""}`,
      config
    );
  }

  async GetCreditHistory(instituteId?: string, config?: HttpRequestConfig) {
    const query = instituteId ? `?instituteId=${encodeURIComponent(instituteId)}` : "";
    return this.httpClient.get<CreditHistoryItem[]>(`/institutes/credit-history${query}`, config);
  }
}

