export interface ProgramOutcomeDto {
  id: string;
  code: string;
  description: string;
  order: number;
  courseId: string;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}

export interface QuestionProgramOutcomeMappingDto {
  questionId: string;
  programOutcomeId: string;
}
