import { IHttpClient, HttpRequestConfig } from "../../api";

/**
 * Triggers evaluate-student-answers for an exam. All data (flow layer, rubric, etc.) comes from DB.
 */
export class EvaluateStudentAnswersService {
    constructor(private readonly httpClient: IHttpClient) {}

    /** POST run; studentIds and bypassCache in body. Returns how many jobs were enqueued (0 = nothing to grade). */
    async run(examId: string, studentId?: string, bypassCache?: boolean, config?: HttpRequestConfig, studentIds?: string[]) {
        const url = `/exams/${examId}/ai/evaluate-student-answers/run`;
        
        const allStudentIds = Array.isArray(studentIds) ? [...studentIds] : [];
        if (studentId) allStudentIds.push(studentId);

        return this.httpClient.post<{ count: number, runId: string }>(
            url,
            {
                studentIds: allStudentIds.length > 0 ? allStudentIds : undefined,
                bypassCache: bypassCache || false,
            },
            config
        );
    }

    /** GET progress (total/completed/pending/percentage). Poll e.g. every 10s while grading. */
    async getProgress(examId: string, config?: HttpRequestConfig) {
        return this.httpClient.get<{
            total: number;
            completed: number;
            pending: number;
            percentage: number;
            failed: number;
            processed: number;
            state: "pending" | "processing" | "completed" | "completed_with_errors" | "failed" | "cancelled" | "timed_out";
            updatedAt: number;
            runId: string;
        }>(`/exams/${examId}/ai/evaluate-student-answers/progress`, config);
    }
}
