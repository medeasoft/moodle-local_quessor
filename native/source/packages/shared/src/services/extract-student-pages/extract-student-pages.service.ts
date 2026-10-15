import { IHttpClient, HttpRequestConfig } from "../../api";

/** Flow group item from extract-student-pages pipeline (student–pages mapping). */
export interface FlowLayerItem {
    student_id: string | null;
    student_uuid: string | null;
    student_name: string | null;
    pages: number[];
    ocr_student_id: string | null;
}

/** Exam PDF with flow_layer for evaluation UI. */
export interface ExamPdfWithFlowLayer {
    id: string;
    fileKey: string;
    displayName: string;
    uploadType: string;
    createdAt: string | null;
    sourceType: "exam_pdf" | "answer_paper";
    flow_layer: FlowLayerItem[];
    pagesMeta: Array<{ pageNumber: number; is_first_page: boolean }>;
}

export interface UploadExamPdfResult {
    pdfKey: string;
    examPdfId: string;
    jobCount: number;
    immediateComplete?: boolean;
}

/**
 * Client for the extract-student-pages AI pipeline (bulk PDF upload, page extraction, flow matching).
 * Use this instead of AnswerService for evaluation flow that uses ExamPdf + flow_layer.
 */
export class ExtractStudentPagesService {
    constructor(private readonly httpClient: IHttpClient) {}

    /** Upload bulk exam PDF; triggers extraction pipeline. Returns S3 pdfKey. When fixedPagesPerStudent is set (e.g. 4), groups are 1..P, P+1..2P, ... and only pages 1, P+1, 2P+1 run OCR/LLM. */
    async uploadBulkExamPdf(
        examId: string,
        file: File,
        options?: { fixedPagesPerStudent?: number } & HttpRequestConfig
    ) {
        const fixedPagesPerStudent = options?.fixedPagesPerStudent;
        const formData = new FormData();
        formData.append("file", file);
        if (fixedPagesPerStudent != null && fixedPagesPerStudent >= 1) {
            formData.append("fixedPagesPerStudent", String(fixedPagesPerStudent));
        }
        const { fixedPagesPerStudent: _drop, ...config } = options ?? {};
        return this.httpClient.post<UploadExamPdfResult>(
            `/exams/${examId}/ai/extract-student-pages/upload-bulk-exam-pdf`,
            formData,
            config
        );
    }

    /** Upload PDF for a single student; all pages are assigned to that student. */
    async uploadSingleStudentExamPdf(
        examId: string,
        file: File,
        studentId: string,
        config?: HttpRequestConfig
    ) {
        const formData = new FormData();
        formData.append("file", file);
        formData.append("studentId", studentId);
        return this.httpClient.post<UploadExamPdfResult>(
            `/exams/${examId}/ai/extract-student-pages/upload-single-student-exam-pdf`,
            formData,
            { ...config, headers: { ...config?.headers } }
        );
    }

    /** Lightweight check for wizard step completion: has this exam any extracted student pages? */
    async getHasStudentPages(examId: string, config?: HttpRequestConfig) {
        return this.httpClient.get<{ hasStudentPages: boolean }>(
            `/exams/${examId}/ai/extract-student-pages/has-student-pages`,
            config
        );
    }

    /** List exam PDFs for an exam with flow_layer (student–pages). Evaluation uses this instead of Answer list. */
    async getExamPdfs(examId: string, config?: HttpRequestConfig) {
        return this.httpClient.get<ExamPdfWithFlowLayer[]>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs`,
            config
        );
    }

    /** Get signed image URLs for exam-pdf pages (for student exam papers viewer). */
    async getExamPdfPageImageUrls(
        examId: string,
        examPdfId: string,
        pageNumbers: number[],
        config?: HttpRequestConfig
    ) {
        const query = new URLSearchParams({ pageNumbers: pageNumbers.join(",") });
        return this.httpClient.get<Array<{ pageNumber: number; imageUrl: string }>>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/page-images?${query}`,
            config
        );
    }

    /** Get extraction status for polling after upload (pending | processing | completed | failed). */
    async getExtractionStatus(
        examId: string,
        examPdfId: string,
        config?: HttpRequestConfig
    ) {
        return this.httpClient.get<{
            status: "pending" | "processing" | "completed" | "failed";
            progress: number;
            totalPages: number;
            errorReason?: string;
        }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/extraction-status`,
            config
        );
    }

    /** Match an unmatched flow group (OCR student number) to an existing student. */
    async matchFlowGroup(
        examId: string,
        examPdfId: string,
        body: { ocrStudentId: string; studentId: string; pageNumbers: number[] },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ updated: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/match-flow-group`,
            body,
            config
        );
    }

    /** Anonim grubu (numara yok) sayfa numaraları ile bir öğrenciye ata. */
    async assignPagesToStudent(
        examId: string,
        examPdfId: string,
        body: { pageNumbers: number[]; studentId: string },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ updated: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/assign-pages-to-student`,
            body,
            config
        );
    }

    /** Bu sayfayı "yeni grup başlangıcı" olarak işaretle; liste yenilendiğinde bu sayfadan itibaren yeni grup oluşur. */
    async markPageAsGroupStart(
        examId: string,
        examPdfId: string,
        body: { pageNumber: number },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ updated: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/mark-page-as-group-start`,
            body,
            config
        );
    }

    /** Bu sayfanın "yeni grup başlangıcı" işaretini kaldır; önceki grup ile birleştirilir. */
    async unmarkPageAsGroupStart(
        examId: string,
        examPdfId: string,
        body: { pageNumber: number },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ updated: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/unmark-page-as-group-start`,
            body,
            config
        );
    }

    /** OCR ile eşleşen grubun öğrenciden ayrılmasını (eşleşmeyi kaldır) sağlar. */
    async unmatchFlowGroup(
        examId: string,
        examPdfId: string,
        body: { ocrStudentId: string; pageNumbers: number[] },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ updated: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/unmatch-flow-group`,
            body,
            config
        );
    }

    /** Atanmış anonim sayfaların öğrenciden ayrılmasını (eşleşmeyi kaldır) sağlar. */
    async unassignPagesFromStudent(
        examId: string,
        examPdfId: string,
        body: { pageNumbers: number[] },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ updated: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/unassign-pages-from-student`,
            body,
            config
        );
    }

    /** Sayfa grubunu (flow group) sistemden tamamen siler. */
    async deleteFlowGroup(
        examId: string,
        examPdfId: string,
        body: { ocrStudentId?: string; studentId?: string; pageNumbers?: number[] },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post<{ deleted: number }>(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}/delete-flow-group`,
            body,
            config
        );
    }

    async deleteExamPdf(
        examId: string,
        examPdfId: string,
        config?: HttpRequestConfig
    ) {
        return this.httpClient.delete(
            `/exams/${examId}/ai/extract-student-pages/exam-pdfs/${examPdfId}`,
            config
        );
    }

    async createAndAssign(
        examId: string,
        body: {
            primaryId: string;
            firstName?: string;
            lastName?: string;
            courseId: string;
            ocrStudentId?: string;
            assignments: Array<{ examPdfId: string; pageNumbers: number[] }>;
        },
        config?: HttpRequestConfig
    ) {
        return this.httpClient.post(
            `/exams/${examId}/ai/extract-student-pages/create-and-assign`,
            body,
            config
        );
    }
}
