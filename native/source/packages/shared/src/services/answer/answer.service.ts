import { IHttpClient, HttpRequestConfig } from "../../api";
import { CreateAnswerDto, UpdateAnswerDto, AnswerDto } from "../../answer";
import { AuditLogDto } from "../../audit";
import { PagedResponse } from "@fullstack/shared/types";

export class AnswerService {
    constructor(private readonly httpClient: IHttpClient) { }

    async Create(dto: CreateAnswerDto, config?: HttpRequestConfig) {
        return this.httpClient.post<AnswerDto>("/answers", dto, config);
    }

    async GetAll(query?: string, config?: HttpRequestConfig) {
        return this.httpClient.get<PagedResponse<AnswerDto>>(`/answers${query ? `?${query}` : ""}`, config);
    }

    async GetById(id: string, config?: HttpRequestConfig) {
        return this.httpClient.get<AnswerDto>(`/answers/${id}`, config);
    }

    async GetHistory(id: string, config?: HttpRequestConfig) {
        return this.httpClient.get<PagedResponse<AuditLogDto>>(`/answers/${id}/history`, config);
    }

    async Update(id: string, dto: UpdateAnswerDto, config?: HttpRequestConfig) {
        return this.httpClient.put<AnswerDto>(`/answers/${id}`, dto, config);
    }

    async Delete(id: string, config?: HttpRequestConfig) {
        return this.httpClient.delete<void>(`/answers/${id}`, config);
    }

    async extractAnswers(
        examId: string,
        file: File,
        options?: { singleStudent?: boolean; studentId?: string },
        config?: HttpRequestConfig
    ) {
        const formData = new FormData();
        formData.append("file", file);
        if (options?.singleStudent) {
            formData.append("singleStudent", "true");
        }
        if (options?.studentId) {
            formData.append("studentId", options.studentId);
        }

        const query = new URLSearchParams();
        if (options?.singleStudent) query.set("singleStudent", "true");
        const queryStr = query.toString();

        return this.httpClient.post<any>(
            `/exams/${examId}/ai/extract-answers${queryStr ? `?${queryStr}` : ""}`,
            formData,
            {
                ...config,
                headers: {
                    ...config?.headers,
                }
            }
        );
    }

    async getExtractionStatus(
        examId: string,
        answerPaperId: string,
        config?: HttpRequestConfig
    ) {
        return this.httpClient.get<{
            status: "pending" | "processing" | "completed" | "failed";
            progress: number;
        }>(`/exams/${examId}/ai/extract-answers/status/${answerPaperId}`, config);
    }

    async GradeExam(examId: string, config?: HttpRequestConfig) {
        return this.httpClient.post<any>(`/grade-exam/${examId}`, {}, config);
    }

    async MatchAnswer(answerId: string, studentId: string, config?: HttpRequestConfig) {
        return this.httpClient.post<AnswerDto>(`/answers/${answerId}/match`, { studentId }, config);
    }
}
