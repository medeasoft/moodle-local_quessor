export type ScoreBandId = "critical" | "developing" | "proficient";
export type QuartilePattern =
  | "class_wide"
  | "bottom_heavy"
  | "top_heavy"
  | "balanced"
  | "insufficient_data";
export type ObjectiveStatus = "critical" | "warning" | "moderate" | "strong";
export type ExamDesignIssueType =
  | "too_difficult"
  | "potential_ambiguity"
  | "low_discrimination"
  | "subquestion_imbalance";
export type ActionHorizon = "next_lecture" | "this_week" | "next_exam";
export type DifficultyLevel = "easy" | "moderate" | "hard" | "very_hard";

export interface ClassScoreBand {
  id: ScoreBandId;
  count: number;
  pct: number;
}

export interface ClassSnapshot {
  studentCount: number;
  averageScorePct: number;
  bands: ClassScoreBand[];
  smallSample: boolean;
}

export interface CommonMisconception {
  questionNumber: number;
  questionLabel?: string;
  topicOrConcept: string;
  affectedCount: number;
  totalCount: number;
  frequencyPct: number;
  description: string;
  wrongModel?: string;
  evidenceSnippet?: string;
  quartilePattern: QuartilePattern;
}

export interface LearningObjectiveInsight {
  objectiveCode: string;
  objectiveDescription: string;
  averageScorePct: number;
  status: ObjectiveStatus;
  reasoning: string;
}

export interface ExamDesignInsight {
  questionNumber: number;
  questionLabel?: string;
  issueType: ExamDesignIssueType;
  insight: string;
  averageScorePct?: number;
  discrimination?: number | null;
}

export interface ActionableRecommendation {
  horizon: ActionHorizon;
  title: string;
  action: string;
  questionNumber?: number;
  objectiveCode?: string;
}

export interface SubQuestionStatDto {
  id?: string;
  label: string;
  textSnippet?: string;
  maxPoints: number;
  averageScore: number;
  averageScorePct: number;
}

export interface QuestionRecommendationItem {
  questionNumber: number;
  questionLabel?: string;
  textSnippet: string;
  maxPoints: number;
  averageScore: number;
  averageScorePct: number;
  difficultyLevel: DifficultyLevel;
  discrimination?: number | null;
  lowScoreCount: number;
  totalAnswers: number;
  zeroOrVeryLowCount: number;
  topQuartileAvgPct?: number | null;
  bottomQuartileAvgPct?: number | null;
  quartilePattern: QuartilePattern;
  commonErrors: string[];
  keyPointsMissed: string[];
  relatedObjectiveCodes?: string[];
  subQuestions?: SubQuestionStatDto[];
}

export interface InstructorRecommendationDto {
  examId: string;
  generatedAt: string;
  summary: string;
  classSnapshot: ClassSnapshot;
  commonMisconceptions: CommonMisconception[];
  objectiveInsights: LearningObjectiveInsight[];
  examDesignInsights: ExamDesignInsight[];
  actionableRecommendations: ActionableRecommendation[];
  questionAnalyses?: QuestionRecommendationItem[];
}

export type InstructorRecommendationJobStatus = "idle" | "generating" | "failed";

export interface InstructorRecommendationView {
  recommendation: InstructorRecommendationDto | null;
  status: InstructorRecommendationJobStatus;
  gradesUpdatedAt: string | null;
  error: string | null;
}
