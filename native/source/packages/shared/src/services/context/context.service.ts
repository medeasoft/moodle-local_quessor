import { IHttpClient, HttpRequestConfig } from "../../api";
import { ContextDto, CreateContextDto, UpdateContextDto } from "../../context";

export class ContextService {
  constructor(private readonly httpClient: IHttpClient) {}

  async GetAllByExamId(examId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<ContextDto[]>(`/contexts?examId=${encodeURIComponent(examId)}`, config);
  }

  async GetById(id: string, config?: HttpRequestConfig) {
    return this.httpClient.get<ContextDto>(`/contexts/${id}`, config);
  }

  async Create(dto: CreateContextDto, config?: HttpRequestConfig) {
    return this.httpClient.post<ContextDto>("/contexts", dto, config);
  }

  async Update(id: string, dto: UpdateContextDto, config?: HttpRequestConfig) {
    return this.httpClient.put<ContextDto>(`/contexts/${id}`, dto, config);
  }

  async Delete(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/contexts/${id}`, config);
  }
}
