import { IHttpClient, HttpRequestConfig } from "../../api";
import {
  CourseObjectiveDto,
  CourseObjectiveProgramOutcomeMappingDto,
  QuestionCourseObjectiveMappingDto,
  CreateCourseObjectiveDto,
  UpdateCourseObjectiveDto,
  SaveCourseObjectiveProgramOutcomeMappingsDto,
  SaveExamCourseObjectiveMappingsDto,
} from "../../course-objective";

export class CourseObjectiveService {
  constructor(private readonly httpClient: IHttpClient) {}

  async GetByCourse(courseId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<CourseObjectiveDto[]>(`/course-objectives?courseId=${courseId}`, config);
  }

  async Create(dto: CreateCourseObjectiveDto, config?: HttpRequestConfig) {
    return this.httpClient.post<CourseObjectiveDto>("/course-objectives", dto, config);
  }

  async Update(id: string, dto: UpdateCourseObjectiveDto, config?: HttpRequestConfig) {
    return this.httpClient.put<CourseObjectiveDto>(`/course-objectives/${id}`, dto, config);
  }

  async Delete(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/course-objectives/${id}`, config);
  }

  async GetCoursePOMappings(courseId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<CourseObjectiveProgramOutcomeMappingDto[]>(
      `/course-objectives/course/${courseId}/po-mappings`,
      config
    );
  }

  async SaveCoursePOMappings(courseId: string, dto: SaveCourseObjectiveProgramOutcomeMappingsDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(`/course-objectives/course/${courseId}/po-mappings`, dto, config);
  }

  async GetExamMappings(examId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<QuestionCourseObjectiveMappingDto[]>(
      `/course-objectives/exam/${examId}/mappings`,
      config
    );
  }

  async SaveExamMappings(examId: string, dto: SaveExamCourseObjectiveMappingsDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(`/course-objectives/exam/${examId}/mappings`, dto, config);
  }

  async AiMatchExam(examId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<{ matches: Array<{ questionId: string; courseObjectiveId: string; reason?: string }> }>(
      `/course-objectives/exam/${examId}/ai-match`,
      {},
      config
    );
  }
}
