import { IHttpClient, HttpRequestConfig } from "../../api";
import { CreateExamDto, UpdateExamDto, ExamDto } from "../../exam";
import { PagedResponse } from "@fullstack/shared/types";

export class ExamService {
    constructor(private readonly httpClient: IHttpClient) { }

    async Create(dto: CreateExamDto, config?: HttpRequestConfig) {
        return this.httpClient.post<ExamDto>("/exams", dto, config);
    }

    async GetAll(query?: string, config?: HttpRequestConfig) {
        return this.httpClient.get<PagedResponse<ExamDto>>(`/exams${query ? `?${query}` : ""}`, config);
    }

    async GetById(id: string, config?: HttpRequestConfig) {
        return this.httpClient.get<ExamDto>(`/exams/${id}`, config);
    }

    async Update(id: string, dto: UpdateExamDto, config?: HttpRequestConfig) {
        return this.httpClient.put<ExamDto>(`/exams/${id}`, dto, config);
    }

    async Delete(id: string, config?: HttpRequestConfig) {
        return this.httpClient.delete<void>(`/exams/${id}`, config);
    }

    async getPageSignedUrl(pageId: string, config?: HttpRequestConfig) {
        return this.httpClient.get<{ url: string }>(`/exams/pages/${pageId}/url`, config);
    }

}
