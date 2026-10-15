import { AnswerDto } from "../answer";
import { ExamDto } from "../exam";
import { ContextDto } from "../context";

export interface QuestionOptionDto {
  option: string;
  text: string;
}

export interface QuestionDto {
  id: string;

  name: string;
  text: string;
  order: number;
  point: number;
  type: QuestionType;
  options?: QuestionOptionDto[] | null;
  correctAnswer?: string | null;
  pageIndex: number;
  pageId?: string | null;
  
  parentId?: string | null;
  isGroup: boolean;
  label: string | null;
  parent?: QuestionDto | null;
  subQuestions?: QuestionDto[];

  /** Ortak metin (passage) – birden fazla soru bu context'i paylaşabilir */
  contextId?: string | null;
  context?: ContextDto | null;

  examId: string;
  exam: ExamDto;
  answers: AnswerDto[];
  questionGradingInstructions?: string | null;

  deletionTime?: number | null;
  deleterUserId?: string | null;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}

export enum QuestionType {
  MULTIPLE_CHOICE = "MULTIPLE_CHOICE",
  ESSAY = "ESSAY",
  TRUE_FALSE = "TRUE_FALSE",
  SHORT_ANSWER = "SHORT_ANSWER",
  MATCHING = "MATCHING",
  FILL_IN_THE_BLANK = "FILL_IN_THE_BLANK",
  MULTI_SELECT = "MULTI_SELECT"
}