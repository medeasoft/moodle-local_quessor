import { IHttpClient } from "../../api/http-client.interface";
import { ExamResultMailingOptions } from "../../exam-result-mailing";

export class ExamResultMailingService {
  constructor(private readonly httpClient: IHttpClient) {}

  async sendAll(examId: string, studentIds?: string[], options?: ExamResultMailingOptions): Promise<any> {
    return this.httpClient.post(`/exams/${examId}/results/send-all`, { studentIds, options });
  }

  async sendOne(examId: string, studentId: string, options?: ExamResultMailingOptions): Promise<any> {
    return this.httpClient.post(`/exams/${examId}/results/send/${studentId}`, { options });
  }

  async getStatus(examId: string): Promise<any> {
    return this.httpClient.get(`/exams/${examId}/results/mailing-status`);
  }

  async downloadClassReportPdf(examId: string): Promise<any> {
    return this.httpClient.get(`/exams/${examId}/results/class-report-pdf`, { responseType: "blob" });
  }

  async downloadStudentReportPdf(examId: string, studentId: string): Promise<any> {
    return this.httpClient.get(`/exams/${examId}/results/student-report-pdf/${studentId}`, { responseType: "blob" });
  }
}
