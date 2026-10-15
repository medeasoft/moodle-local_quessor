import { IHttpClient, HttpRequestConfig } from "../../api";
import { ProgramOutcomeDto, QuestionProgramOutcomeMappingDto, CreateProgramOutcomeDto, UpdateProgramOutcomeDto, SaveExamMappingsDto } from "../../program-outcome";

export class ProgramOutcomeService {
  constructor(private readonly httpClient: IHttpClient) {}

  async GetByCourse(courseId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<ProgramOutcomeDto[]>(`/program-outcomes?courseId=${courseId}`, config);
  }

  async Create(dto: CreateProgramOutcomeDto, config?: HttpRequestConfig) {
    return this.httpClient.post<ProgramOutcomeDto>("/program-outcomes", dto, config);
  }

  async Update(id: string, dto: UpdateProgramOutcomeDto, config?: HttpRequestConfig) {
    return this.httpClient.put<ProgramOutcomeDto>(`/program-outcomes/${id}`, dto, config);
  }

  async Delete(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/program-outcomes/${id}`, config);
  }

  async GetExamMappings(examId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<QuestionProgramOutcomeMappingDto[]>(`/program-outcomes/exam/${examId}/mappings`, config);
  }

  async SaveExamMappings(examId: string, dto: SaveExamMappingsDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(`/program-outcomes/exam/${examId}/mappings`, dto, config);
  }
}
