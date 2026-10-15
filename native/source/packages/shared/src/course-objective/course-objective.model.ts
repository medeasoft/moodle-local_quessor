export interface CourseObjectiveDto {
  id: string;
  code: string;
  description: string;
  order: number;
  courseId: string;
  programOutcomeIds?: string[];
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}

export interface CourseObjectiveProgramOutcomeMappingDto {
  courseObjectiveId: string;
  programOutcomeId: string;
  /** Percentage in basis points: 10000 = 100%. Sum per program outcome is 10000. */
  weightBps: number;
}

export interface QuestionCourseObjectiveMappingDto {
  questionId: string;
  courseObjectiveId: string;
  /** Optional weight in bps for backwards compatibility; matrix mappings are binary. */
  weightBps?: number;
}
