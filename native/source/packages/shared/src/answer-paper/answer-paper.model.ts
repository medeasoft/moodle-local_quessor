export interface AnswerPaperDto {
    id: string;
    examId: string;
    displayName: string;
    fileKey: string;

    deletionTime?: number | null;
    deleterUserId?: string | null;
    creationTime?: number | null;
    creatorUserId?: string | null;
    lastModificationTime?: number | null;
    lastModifierUserId?: string | null;
}

export interface CreateAnswerPaperDto {
    examId: string;
    displayName?: string; // Optional: if not provided, backend will use PDF filename
    fileKey: string;
}

export interface UpdateAnswerPaperDto {
    displayName?: string;
    fileKey?: string;
}
