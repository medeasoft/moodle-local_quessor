import { IHttpClient, HttpRequestConfig } from "../../api";
import { SystemLogDto } from "../../system-log";
import { PagedResponse } from "../../types";

export class SystemLogService {
  constructor(private readonly httpClient: IHttpClient) {}

  async GetAll(query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<SystemLogDto>>(`/logs${query ? `?${query}` : ""}`, config);
  }

  async GetById(id: number, config?: HttpRequestConfig) {
    return this.httpClient.get<SystemLogDto>(`/logs/${id}`, config);
  }
}
