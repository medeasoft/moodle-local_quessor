export interface ExamPaperFile {
    type: 'QUESTION' | 'ANSWER_KEY';
    key: string;
    pages: number;
}

export interface ExamPaperDto {
    id: string;
    examId: string;
    files: ExamPaperFile[];

    answerPhaseStarted?: boolean;
    questionsReady?: boolean;

    deletionTime?: number | null;
    deleterUserId?: string | null;
    creationTime?: number | null;
    creatorUserId?: string | null;
    lastModificationTime?: number | null;
    lastModifierUserId?: string | null;
}

export interface CreateExamPaperDto {
    examId: string;
    files: ExamPaperFile[];
}

export interface UpdateExamPaperDto {
    files?: ExamPaperFile[];
    answerPhaseStarted?: boolean;
    questionsReady?: boolean;
}
