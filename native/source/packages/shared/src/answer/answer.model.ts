import { QuestionDto } from "../question/question.model";
import { StudentDto } from "../student/student.model";

export interface AnswerDto {
  id: string;

  answer: string | null;
  point: number | null;
  pointFromAi: number | null;
  feedback: string | null;
  feedbackFromAi: string | null;
  /** Epoch milliseconds of the latest completed AI evaluation. */
  evaluatedAt?: number | null;
  pageIds?: string[] | null; // All pages this answer spans (from answer_page)
  pageNumbers?: number[] | null; // Same order as pageIds (for sorting/labels)
  pageNumber?: number | null; // First page number (convenience)

  questionId: string;
  question?: QuestionDto;

  studentId: string | null; // Nullable to allow saving answers when student ID not found
  student?: StudentDto;
  ocrStudentId?: string | null; // OCR-detected student ID for manual matching

  deletionTime?: number | null;
  deleterUserId?: string | null;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}
