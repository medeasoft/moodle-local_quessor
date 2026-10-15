import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  type ReactNode,
} from "react";
import { useQuery } from "@tanstack/react-query";
import type { AnswerDto, ExamDto, ExamPdfWithFlowLayer, QuestionDto } from "@fullstack/shared";
import qs from "qs";
import {
  EVALUATION_WIZARD_STEPS,
  canAccessEvaluationStepInFlow,
  type EvaluationStepCompletion,
  type EvaluationWizardStep,
} from "./core";
import type { EvaluationServices } from "./services";

export type FlowLayerItem = {
  student_id?: string | null;
  student_uuid?: string | null;
  student_name?: string | null;
  pages: number[];
  ocr_student_id?: string | null;
};

export interface SharedEvaluationWizardValue {
  examId: string;
  exam: ExamDto | null | undefined;
  isLoading: boolean;
  currentStep: EvaluationWizardStep;
  goToStep(step: EvaluationWizardStep): void;
  nextStep(): void;
  prevStep(): void;
  steps: EvaluationWizardStep[];
  isStepCompleted(step: EvaluationWizardStep): boolean;
  canGoToStep(step: EvaluationWizardStep): boolean;
  previewStepIsFullyActive: boolean;
}

export interface SharedEvaluationDataValue {
  exam: ExamDto | null | undefined;
  isLoading: boolean;
  questions: QuestionDto[];
  answers: AnswerDto[];
  matchedAnswers: AnswerDto[];
  hasStudentPages: boolean;
  previewStepIsFullyActive: boolean;
  completionStatus: EvaluationStepCompletion;
}

export interface SharedEvaluationWizardProviderProps {
  examId: string;
  currentStep: EvaluationWizardStep;
  onNavigate(step: EvaluationWizardStep): void;
  services: EvaluationServices;
  children: ReactNode;
  pollForStudentPages?: boolean;
  includeStudentPages?: boolean;
  onBlockedStep?(step: EvaluationWizardStep): void;
  onDataError?(error: unknown): void;
  steps?: readonly EvaluationWizardStep[];
}

const WizardContext = createContext<SharedEvaluationWizardValue | undefined>(undefined);
const DataContext = createContext<SharedEvaluationDataValue | undefined>(undefined);

export function SharedEvaluationWizardProvider({
  examId,
  currentStep,
  onNavigate,
  services,
  children,
  pollForStudentPages = false,
  includeStudentPages = true,
  onBlockedStep,
  onDataError,
  steps: configuredSteps = EVALUATION_WIZARD_STEPS,
}: SharedEvaluationWizardProviderProps) {
  const wizardSteps = useMemo(() => Array.from(configuredSteps), [configuredSteps]);
  const pollStartedAt = useRef(Date.now());
  const { data: exam, isLoading, error } = useQuery({
    queryKey: ["exam", examId],
    queryFn: async () => (await services.examService.GetById(examId)).data,
    enabled: Boolean(examId),
  });

  const { data: questions = [] } = useQuery<QuestionDto[]>({
    queryKey: ["questions", examId],
    queryFn: async () => {
      const query = qs.stringify(
        { page: 1, pageSize: 10000, filters: [{ field: "examId", operator: "eq", value: examId }] },
        { allowDots: true },
      );
      const response = await services.questionService.GetAll(query);
      return Array.isArray(response.data?.data) ? response.data.data : [];
    },
    enabled: Boolean(examId),
  });

  const { data: hasStudentPagesData } = useQuery({
    queryKey: ["has-student-pages", examId],
    queryFn: async () => {
      const response = await services.extractStudentPagesService.getHasStudentPages(examId);
      const value = (response as { data?: { hasStudentPages?: boolean }; hasStudentPages?: boolean }).data ?? response;
      return Boolean((value as { hasStudentPages?: boolean }).hasStudentPages);
    },
    enabled: Boolean(examId) && includeStudentPages,
    refetchInterval: (query) => {
      if (!includeStudentPages || !pollForStudentPages || query.state.data === true || Date.now() - pollStartedAt.current > 90_000) return false;
      return 3000;
    },
  });
  const hasStudentPages = Boolean(hasStudentPagesData);

  const { data: examPdfs = [] } = useQuery({
    queryKey: ["exam-pdfs", examId],
    queryFn: async (): Promise<ExamPdfWithFlowLayer[]> => {
      const response = await services.extractStudentPagesService.getExamPdfs(examId);
      const value = (response as { data?: ExamPdfWithFlowLayer[] }).data ?? response;
      return Array.isArray(value) ? value : [];
    },
    enabled: Boolean(examId) && includeStudentPages,
    refetchInterval: (query) => {
      if (!includeStudentPages || !pollForStudentPages) return false;
      const list = query.state.data as ExamPdfWithFlowLayer[] | undefined;
      const hasPages = Array.isArray(list) && list.some((pdf) => (pdf.flow_layer ?? []).length > 0);
      if (hasPages || Date.now() - pollStartedAt.current > 90_000) return false;
      return 3000;
    },
  });

  const studentPagesReady = useMemo(() => {
    const flowItems = examPdfs.flatMap((pdf) => pdf.flow_layer ?? []);
    if (flowItems.length === 0 || flowItems.some((item) => !item.student_uuid)) return false;
    const groupCountByStudent = new Map<string, number>();
    for (const item of flowItems) {
      const studentId = item.student_uuid!;
      groupCountByStudent.set(studentId, (groupCountByStudent.get(studentId) ?? 0) + 1);
    }
    return Array.from(groupCountByStudent.values()).every((count) => count === 1);
  }, [examPdfs]);

  const { data: answers = [] } = useQuery<AnswerDto[]>({
    queryKey: ["answers", examId],
    queryFn: async () => {
      const query = qs.stringify(
        { page: 1, pageSize: 10000, filters: [{ field: "question.examId", operator: "eq", value: examId }] },
        { allowDots: true },
      );
      const response = await services.answerService.GetAll(query);
      return Array.isArray(response.data?.data) ? response.data.data : [];
    },
    enabled: Boolean(examId),
  });

  const matchedAnswers = useMemo(() => answers.filter((answer) => answer.studentId != null), [answers]);
  const matchedStudentIds = useMemo(
    () => new Set(
      examPdfs
        .flatMap((pdf) => pdf.flow_layer ?? [])
        .map((item) => item.student_uuid)
        .filter((studentId): studentId is string => Boolean(studentId)),
    ),
    [examPdfs],
  );
  const gradableQuestionIds = useMemo(() => {
    const parentQuestionIds = new Set(questions.map((question) => question.parentId).filter(Boolean));
    return questions.filter((question) => !parentQuestionIds.has(question.id)).map((question) => question.id);
  }, [questions]);
  const previewStepIsFullyActive = useMemo(
    () => {
      if (!studentPagesReady || matchedStudentIds.size === 0 || gradableQuestionIds.length === 0) return false;

      const gradedQuestionIdsByStudent = new Map<string, Set<string>>();
      for (const answer of matchedAnswers) {
        if (!answer.studentId || answer.pointFromAi == null) continue;
        const gradedQuestionIds = gradedQuestionIdsByStudent.get(answer.studentId) ?? new Set<string>();
        gradedQuestionIds.add(answer.questionId);
        gradedQuestionIdsByStudent.set(answer.studentId, gradedQuestionIds);
      }

      return Array.from(matchedStudentIds).every((studentId) => {
        const gradedQuestionIds = gradedQuestionIdsByStudent.get(studentId);
        return Boolean(gradedQuestionIds && gradableQuestionIds.every((questionId) => gradedQuestionIds.has(questionId)));
      });
    },
    [gradableQuestionIds, matchedAnswers, matchedStudentIds, studentPagesReady],
  );
  const completionStatus = useMemo<EvaluationStepCompletion>(() => ({
    students: true,
    questions: questions.length > 0,
    "evaluation-criteria": true,
    "student-pages": hasStudentPages && studentPagesReady,
    preview: previewStepIsFullyActive,
    result: previewStepIsFullyActive,
  }), [hasStudentPages, previewStepIsFullyActive, questions.length, studentPagesReady]);

  useEffect(() => {
    if (error) onDataError?.(error);
  }, [error, onDataError]);

  const canGoToStep = useCallback(
    (step: EvaluationWizardStep) => canAccessEvaluationStepInFlow(step, currentStep, completionStatus, wizardSteps),
    [completionStatus, currentStep, wizardSteps],
  );
  const goToStep = useCallback((step: EvaluationWizardStep) => {
    if (!canGoToStep(step)) {
      onBlockedStep?.(step);
      return;
    }
    onNavigate(step);
  }, [canGoToStep, onBlockedStep, onNavigate]);
  const currentIndex = wizardSteps.indexOf(currentStep);
  const nextStep = useCallback(() => {
    if (currentIndex >= 0 && currentIndex < wizardSteps.length - 1) goToStep(wizardSteps[currentIndex + 1]);
  }, [currentIndex, goToStep, wizardSteps]);
  const prevStep = useCallback(() => {
    if (currentIndex > 0) goToStep(wizardSteps[currentIndex - 1]);
  }, [currentIndex, goToStep, wizardSteps]);

  const dataValue = useMemo<SharedEvaluationDataValue>(() => ({
    exam,
    isLoading,
    questions,
    answers,
    matchedAnswers,
    hasStudentPages,
    previewStepIsFullyActive,
    completionStatus,
  }), [answers, completionStatus, exam, hasStudentPages, isLoading, matchedAnswers, previewStepIsFullyActive, questions]);
  const wizardValue = useMemo<SharedEvaluationWizardValue>(() => ({
    examId,
    exam,
    isLoading,
    currentStep,
    goToStep,
    nextStep,
    prevStep,
    steps: wizardSteps,
    isStepCompleted: (step) => completionStatus[step],
    canGoToStep,
    previewStepIsFullyActive,
  }), [canGoToStep, completionStatus, currentStep, exam, examId, goToStep, isLoading, nextStep, prevStep, previewStepIsFullyActive, wizardSteps]);

  return (
    <DataContext.Provider value={dataValue}>
      <WizardContext.Provider value={wizardValue}>{children}</WizardContext.Provider>
    </DataContext.Provider>
  );
}

export function useSharedEvaluationWizard(): SharedEvaluationWizardValue {
  const context = useContext(WizardContext);
  if (!context) throw new Error("useSharedEvaluationWizard must be used inside SharedEvaluationWizardProvider");
  return context;
}

export function useSharedEvaluationData(): SharedEvaluationDataValue {
  const context = useContext(DataContext);
  if (!context) throw new Error("useSharedEvaluationData must be used inside SharedEvaluationWizardProvider");
  return context;
}
