import { QuestionCourseObjectiveMappingDto } from "./course-objective.model";

export const OBJECTIVE_WEIGHT_TOTAL = 10000;

/** Exact equal shares, including the rounding remainder, in the supplied order. */
export function equalObjectiveWeights(count: number): number[] {
  if (!Number.isInteger(count) || count < 1 || count > OBJECTIVE_WEIGHT_TOTAL) return [];
  return Array.from({ length: count }, (_, i) =>
    Math.floor(OBJECTIVE_WEIGHT_TOTAL / count) + (i < OBJECTIVE_WEIGHT_TOTAL % count ? 1 : 0));
}

/** Accept locale decimal separators, but never silently round an invalid percentage. */
export function parseObjectivePercentage(value: string): number | null {
  const normalized = value.trim().replace(",", ".");
  if (!normalized) return 0;
  if (!/^\d{1,3}(\.\d{1,2})?$/.test(normalized)) return null;
  const bps = Math.round(Number(normalized) * 100);
  return bps <= OBJECTIVE_WEIGHT_TOTAL ? bps : null;
}

export interface ObjectiveAssessmentQuestion {
  id: string;
  parentId?: string | null;
  point: number;
  subQuestions?: ObjectiveAssessmentQuestion[];
}

export function flattenObjectiveQuestions<T extends ObjectiveAssessmentQuestion>(questions: T[]): T[] {
  const result = new Map<string, T>();
  const visit = (question: T, parentId?: string) => {
    if (result.has(question.id)) return;
    const normalized = { ...question, parentId: question.parentId ?? parentId };
    result.set(question.id, normalized);
    for (const child of question.subQuestions ?? []) visit(child as T, question.id);
  };
  questions.forEach((question) => visit(question));
  return [...result.values()];
}

export interface ObjectiveQuestionAllocation {
  questionId: string;
  courseObjectiveId: string;
  weightBps: number;
  maxPoints: number;
}

/** Expand ancestor mappings for an editor that displays only scoreable leaf questions. */
export function resolveQuestionObjectiveMappings(input: ObjectiveAssessmentQuestion[], mappings: QuestionCourseObjectiveMappingDto[]) {
  const questions = flattenObjectiveQuestions(input);
  const byId = new Map(questions.map((q) => [q.id, q]));
  const parents = new Set(questions.map((q) => q.parentId));
  return questions.filter((q) => !parents.has(q.id)).flatMap((question) => {
    let current: ObjectiveAssessmentQuestion | undefined = question;
    const visited = new Set<string>();
    while (current && !visited.has(current.id)) {
      visited.add(current.id);
      const group = mappings.filter((m) => m.questionId === current!.id);
      if (group.length) return group.map((m) => ({ ...m, questionId: question.id }));
      current = current.parentId ? byId.get(current.parentId) : undefined;
    }
    return [];
  });
}

/** Score leaf questions once. A child's explicit distribution overrides its ancestor's. */
export function getObjectiveQuestionAllocations(
  input: ObjectiveAssessmentQuestion[], objectiveIds: string[], mappings: QuestionCourseObjectiveMappingDto[]
) {
  const questions = flattenObjectiveQuestions(input);
  const byId = new Map(questions.map((q) => [q.id, q]));
  const parentIds = new Set(questions.map((q) => q.parentId).filter(Boolean));
  const activeObjectives = new Set(objectiveIds);
  const byQuestion = new Map<string, QuestionCourseObjectiveMappingDto[]>();
  for (const mapping of mappings) {
    const group = byQuestion.get(mapping.questionId) ?? [];
    group.push(mapping);
    byQuestion.set(mapping.questionId, group);
  }
  const allocations: ObjectiveQuestionAllocation[] = [];
  const invalidQuestionIds: string[] = [];
  const unmappedQuestionIds: string[] = [];
  let totalPoints = 0;
  let allocatedPoints = 0;
  for (const question of questions.filter((q) => !parentIds.has(q.id))) {
    const maxPoints = Number(question.point);
    if (!Number.isFinite(maxPoints) || maxPoints <= 0) continue;
    totalPoints += maxPoints;
    let current: ObjectiveAssessmentQuestion | undefined = question;
    let group: QuestionCourseObjectiveMappingDto[] | undefined;
    const visited = new Set<string>();
    while (current && !visited.has(current.id)) {
      visited.add(current.id);
      group = byQuestion.get(current.id);
      if (group?.length) break;
      current = current.parentId ? byId.get(current.parentId) : undefined;
    }
    if (!group?.length) { unmappedQuestionIds.push(question.id); continue; }
    if (group.some((m) => !activeObjectives.has(m.courseObjectiveId))
      || new Set(group.map((m) => m.courseObjectiveId)).size !== group.length) {
      invalidQuestionIds.push(question.id);
      continue;
    }
    allocatedPoints += maxPoints;
    group.forEach((m) => allocations.push({
      questionId: question.id,
      courseObjectiveId: m.courseObjectiveId,
      weightBps: m.weightBps ?? OBJECTIVE_WEIGHT_TOTAL,
      maxPoints
    }));
  }
  return { allocations, totalPoints, allocatedPoints, invalidQuestionIds, unmappedQuestionIds };
}

/** Missing evidence remains null, never a zero achievement score. */
export function calculateWeightedObjectiveAchievement(
  contributions: { weightBps: number; achievementPct: number | null }[]
) {
  const total = contributions.reduce((sum, c) => sum + c.weightBps, 0);
  const valid = total > 0 && contributions.every((c) => Number.isInteger(c.weightBps) && c.weightBps > 0);
  if (!valid) return { valid: false, coveragePct: 0, achievementPct: null };
  const measured = contributions.filter((c) => c.achievementPct !== null && Number.isFinite(c.achievementPct));
  const measuredWeight = measured.reduce((sum, c) => sum + c.weightBps, 0);
  return {
    valid: true,
    coveragePct: total > 0 ? (measuredWeight / total) * 100 : 0,
    achievementPct: measuredWeight > 0
      ? measured.reduce((sum, c) => sum + c.achievementPct! * c.weightBps, 0) / measuredWeight
      : null
  };
}
