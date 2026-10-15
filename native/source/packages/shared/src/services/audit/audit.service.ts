import { IHttpClient, HttpRequestConfig } from "../../api";
import { AuditLogDto } from "../../audit";
import { PagedResponse } from "../../types";

export class AuditLogService {
  constructor(private readonly httpClient: IHttpClient) {}

  async GetAll(query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<AuditLogDto>>(`/audit-logs${query ? `?${query}` : ""}`, config);
  }
}
