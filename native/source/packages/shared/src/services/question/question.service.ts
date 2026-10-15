import { IHttpClient, HttpRequestConfig } from "../../api";
import {
  CreateQuestionDto,
  UpdateQuestionDto,
  QuestionDto,
  GenerateQuestionAssessmentDto,
  GeneratedQuestionAssessmentDto
} from "../../question";
import { PagedResponse } from "@fullstack/shared/types";

export interface ExtractQuestionsSyncResult {
  success: boolean;
  examPaperId: string;
  pageCount: number;
  pagesProcessed: number;
  questionsCount: number;
  answersCount: number;
  errors: string[];
}

export type ExtractQuestionsResult =
  | { success: boolean; jobCount: number; flowJobId: string; examPaperId: string }
  | ExtractQuestionsSyncResult;

export class QuestionService {
  constructor(private readonly httpClient: IHttpClient) {}

  async Create(dto: CreateQuestionDto, config?: HttpRequestConfig) {
    return this.httpClient.post<QuestionDto>("/questions", dto, config);
  }

  async GetAll(query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<QuestionDto>>(`/questions${query ? `?${query}` : ""}`, config);
  }

  async GetById(id: string, config?: HttpRequestConfig) {
    return this.httpClient.get<QuestionDto>(`/questions/${id}`, config);
  }

  async Update(id: string, dto: UpdateQuestionDto, config?: HttpRequestConfig) {
    return this.httpClient.put<QuestionDto>(`/questions/${id}`, dto, config);
  }

  async generateAssessment(dto: GenerateQuestionAssessmentDto, config?: HttpRequestConfig) {
    return this.httpClient.post<GeneratedQuestionAssessmentDto>("/questions/ai/generate-assessment", dto, config);
  }

  async Delete(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/questions/${id}`, config);
  }

  async extractQuestions(
    examId: string,
    questionFile: File,
    answerKeyFile?: File,
    config?: HttpRequestConfig & { sync?: boolean }
  ) {
    const formData = new FormData();
    formData.append("questionFile", questionFile);
    if (answerKeyFile) {
      formData.append("answerKeyFile", answerKeyFile);
    }
    if (config?.sync) {
      formData.append("sync", "true");
    }
    return this.httpClient.post<ExtractQuestionsResult>(`/exams/${examId}/ai/extract-questions`, formData, {
      ...config,
      headers: {
        ...config?.headers,
        "Content-Type": "multipart/form-data"
      }
    });
  }

  async getExtractionStatus(examId: string, examPaperId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<{
      status: "pending" | "processing" | "completed" | "failed";
      progress: number;
      totalPages: number;
      progressDetail?: { currentPage: number; totalPages: number; stage: string };
      meta?: any;
    }>(`/exams/${examId}/ai/extract-questions/status/${examPaperId}`, config);
  }

  async extractSingleQuestion(examId: string, file: File, pageNumber?: number, config?: HttpRequestConfig) {
    const formData = new FormData();
    formData.append("file", file);
    if (pageNumber !== undefined) {
      formData.append("pageNumber", String(pageNumber));
    }
    return this.httpClient.post<{
      text: string;
      questionGradingInstructions: string | null;
      type: string;
      point: number;
      correctAnswer: string | null;
      name: string;
    }>(`/exams/${examId}/ai/extract-questions/single`, formData, {
      ...config,
      headers: {
        ...config?.headers,
        "Content-Type": "multipart/form-data"
      }
    });
  }
}
