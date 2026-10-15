import { QuestionType } from "../question";

export interface QuestionGenerationDto {
  id: string;
  title: string;
  examId?: string | null;
  sourceType: "text" | "file" | "url" | "mixed";
  sourceValue: string;
  options: {
    questionTypesBreakdown: Record<QuestionType, number>;
    difficulty: "EASY" | "MEDIUM" | "HARD";
    language: string;
    generateCorrectAnswer?: boolean;
    generateRubric?: boolean;
    rubricDetail?: "concise" | "detailed" | "point_based";
  };
  questions: GeneratedQuestionItem[];
  userId: string;
  creationTime: number;
}

export interface GeneratedQuestionItem {
  text: string;
  type: QuestionType;
  options?: { option: string; text: string }[] | null;
  correctAnswer?: string | null;
  rubric?: string | null;
  points?: number | null;
  matchingPairs?: {
    left_items: string[];
    right_items: string[];
  } | null;
}
