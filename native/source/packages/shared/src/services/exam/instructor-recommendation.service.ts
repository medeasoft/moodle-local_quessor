import { IHttpClient, HttpRequestConfig } from "../../api";
import { InstructorRecommendationView } from "../../exam/instructor-recommendation.model";

export class InstructorRecommendationService {
  constructor(private readonly httpClient: IHttpClient) {}

  async getRecommendation(examId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<InstructorRecommendationView>(
      `/instructor-recommendation/${examId}`,
      config
    );
  }

  async generateRecommendation(examId: string, config?: HttpRequestConfig) {
    return this.httpClient.post<InstructorRecommendationView>(
      `/instructor-recommendation/${examId}`,
      {},
      config
    );
  }
}
