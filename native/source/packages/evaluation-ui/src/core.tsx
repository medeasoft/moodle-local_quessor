import { createContext, useContext, type ReactNode } from "react";
import type { IHttpClient } from "@fullstack/shared";

export type EvaluationWizardStep =
  | "students"
  | "questions"
  | "evaluation-criteria"
  | "student-pages"
  | "preview"
  | "result";

export const EVALUATION_WIZARD_STEPS: EvaluationWizardStep[] = [
  "students",
  "questions",
  "evaluation-criteria",
  "student-pages",
  "preview",
  "result",
];

export type EvaluationStepCompletion = Record<EvaluationWizardStep, boolean>;

const STEP_REQUIREMENTS: Record<EvaluationWizardStep, EvaluationWizardStep[]> = {
  students: [],
  questions: [],
  "evaluation-criteria": ["questions"],
  "student-pages": ["evaluation-criteria"],
  preview: ["student-pages"],
  result: ["preview"],
};

export function canAccessEvaluationStep(
  step: EvaluationWizardStep,
  currentStep: EvaluationWizardStep,
  completion: EvaluationStepCompletion,
): boolean {
  if (step === currentStep || completion[step]) return true;
  return STEP_REQUIREMENTS[step].every((requirement) => completion[requirement]);
}

/**
 * Applies the same completion gating to a platform-specific subset of steps.
 * Hidden steps keep their data pipelines, but are not navigation prerequisites.
 */
export function canAccessEvaluationStepInFlow(
  step: EvaluationWizardStep,
  currentStep: EvaluationWizardStep,
  completion: EvaluationStepCompletion,
  steps: readonly EvaluationWizardStep[],
): boolean {
  if (step === currentStep || completion[step]) return true;
  const index = steps.indexOf(step);
  if (index <= 0) return index === 0;
  return completion[steps[index - 1]];
}

export interface EvaluationNavigationAdapter {
  currentStep: EvaluationWizardStep;
  navigate(step: EvaluationWizardStep): void;
}

export interface EvaluationPlatform {
  examId: string;
  locale: string;
  httpClient: IHttpClient;
  navigation: EvaluationNavigationAdapter;
  mode: "next" | "moodle";
}

const EvaluationPlatformContext = createContext<EvaluationPlatform | null>(null);

export function EvaluationPlatformProvider({
  platform,
  children,
}: {
  platform: EvaluationPlatform;
  children: ReactNode;
}) {
  return <EvaluationPlatformContext.Provider value={platform}>{children}</EvaluationPlatformContext.Provider>;
}

export function useEvaluationPlatform(): EvaluationPlatform {
  const platform = useContext(EvaluationPlatformContext);
  if (!platform) throw new Error("useEvaluationPlatform must be used inside EvaluationPlatformProvider");
  return platform;
}
