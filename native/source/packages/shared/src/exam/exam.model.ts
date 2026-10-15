import { CourseDto } from "../course/course.model";
import { QuestionDto } from "../question/question.model";
import { ExamPaperDto } from "./exam-paper.model";
import { AnswerPaperDto } from "../answer-paper/answer-paper.model";

export type EvaluationStatus = "not_started" | "in_progress" | "completed";

export interface ExamDto {
  id: string;
  title: string;
  maxScore: number;
  // settings?: any | null;
  targetLevel?: string;
  examGradingInstructions?: string | null;

  feedbackLanguage: string;
  scoreIncrement: number;

  courseId: string;
  course?: CourseDto;

  questions?: QuestionDto[];
  examPapers?: ExamPaperDto[];
  answerPapers?: AnswerPaperDto[];

  // Evaluation status (optional, computed field)
  evaluationStatus?: EvaluationStatus;

  deletionTime?: number | null;
  deleterUserId?: string | null;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}
