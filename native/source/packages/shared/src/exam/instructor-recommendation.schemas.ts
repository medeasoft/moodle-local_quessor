import { z } from "zod";
import {
  ActionableRecommendation,
  ClassScoreBand,
  ClassSnapshot,
  CommonMisconception,
  ExamDesignInsight,
  InstructorRecommendationDto,
  LearningObjectiveInsight,
  QuestionRecommendationItem,
  SubQuestionStatDto
} from "./instructor-recommendation.model";

export const classScoreBandSchema: z.ZodType<ClassScoreBand> = z.object({
  id: z.enum(["critical", "developing", "proficient"]),
  count: z.number().int().nonnegative(),
  pct: z.number()
});

export const classSnapshotSchema: z.ZodType<ClassSnapshot> = z.object({
  studentCount: z.number().int().nonnegative(),
  averageScorePct: z.number(),
  bands: z.array(classScoreBandSchema),
  smallSample: z.boolean()
});

export const quartilePatternSchema = z.enum([
  "class_wide",
  "bottom_heavy",
  "top_heavy",
  "balanced",
  "insufficient_data"
]);

export const commonMisconceptionSchema: z.ZodType<CommonMisconception> = z.object({
  questionNumber: z.number().int(),
  questionLabel: z.string().optional(),
  topicOrConcept: z.string(),
  affectedCount: z.number().int().nonnegative(),
  totalCount: z.number().int().nonnegative(),
  frequencyPct: z.number(),
  description: z.string(),
  wrongModel: z.string().optional(),
  evidenceSnippet: z.string().optional(),
  quartilePattern: quartilePatternSchema
});

export const learningObjectiveInsightSchema: z.ZodType<LearningObjectiveInsight> = z.object({
  objectiveCode: z.string(),
  objectiveDescription: z.string(),
  averageScorePct: z.number(),
  status: z.enum(["critical", "warning", "moderate", "strong"]),
  reasoning: z.string()
});

export const examDesignInsightSchema: z.ZodType<ExamDesignInsight> = z.object({
  questionNumber: z.number().int(),
  questionLabel: z.string().optional(),
  issueType: z.enum([
    "too_difficult",
    "potential_ambiguity",
    "low_discrimination",
    "subquestion_imbalance"
  ]),
  insight: z.string(),
  averageScorePct: z.number().optional(),
  discrimination: z.union([z.number(), z.null()]).optional()
});

export const actionableRecommendationSchema: z.ZodType<ActionableRecommendation> = z.object({
  horizon: z.enum(["next_lecture", "this_week", "next_exam"]),
  title: z.string(),
  action: z.string(),
  questionNumber: z.number().int().positive().optional(),
  objectiveCode: z.string().min(1).optional()
});

export const subQuestionStatSchema: z.ZodType<SubQuestionStatDto> = z.object({
  id: z.string().optional(),
  label: z.string(),
  textSnippet: z.string().optional(),
  maxPoints: z.number(),
  averageScore: z.number(),
  averageScorePct: z.number()
});

export const questionRecommendationItemSchema: z.ZodType<QuestionRecommendationItem> = z.object({
  questionNumber: z.number().int(),
  questionLabel: z.string().optional(),
  textSnippet: z.string(),
  maxPoints: z.number(),
  averageScore: z.number(),
  averageScorePct: z.number(),
  difficultyLevel: z.enum(["easy", "moderate", "hard", "very_hard"]),
  discrimination: z.union([z.number(), z.null()]),
  lowScoreCount: z.number().int().nonnegative(),
  totalAnswers: z.number().int().nonnegative(),
  zeroOrVeryLowCount: z.number().int().nonnegative(),
  topQuartileAvgPct: z.union([z.number(), z.null()]),
  bottomQuartileAvgPct: z.union([z.number(), z.null()]),
  quartilePattern: quartilePatternSchema,
  commonErrors: z.array(z.string()),
  keyPointsMissed: z.array(z.string()),
  relatedObjectiveCodes: z.array(z.string()).optional(),
  subQuestions: z.array(subQuestionStatSchema).optional()
});

export const instructorRecommendationSchema: z.ZodType<InstructorRecommendationDto> = z.object({
  examId: z.string(),
  generatedAt: z.string(),
  summary: z.string(),
  classSnapshot: classSnapshotSchema,
  commonMisconceptions: z.array(commonMisconceptionSchema),
  objectiveInsights: z.array(learningObjectiveInsightSchema),
  examDesignInsights: z.array(examDesignInsightSchema),
  actionableRecommendations: z.array(actionableRecommendationSchema),
  questionAnalyses: z.array(questionRecommendationItemSchema).optional()
});
