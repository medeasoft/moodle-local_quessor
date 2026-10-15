import { InstituteDto } from "../institute/institute.model";
import { ExamDto } from "../exam/exam.model";
import { StudentDto } from "../student/student.model";
import { UserDto } from "../account";

export interface CourseDto {
  id: string;
  name: string;
  description?: string | null;
  code?: string | null;
  isActive: boolean;

  instituteId: string;
  institute?: InstituteDto;

  exams?: ExamDto[];
  students?: StudentDto[];
  teachers?: UserDto[];

  deletionTime?: number | null;
  deleterUserId?: string | null;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}
