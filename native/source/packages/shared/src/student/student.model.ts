import { CourseDto } from "../course/course.model";
import { AnswerDto } from "../answer/answer.model";

export interface StudentDto {
    id: string;
    firstName?: string | null;
    lastName?: string | null;
    email?: string | null;
    primaryId: string;
    secondaryId?: string | null;

    courseId: string;
    course?: CourseDto;

    answers?: AnswerDto[];

    deletionTime?: number | null;
    deleterUserId?: string | null;
    creationTime?: number | null;
    creatorUserId?: string | null;
    lastModificationTime?: number | null;
    lastModifierUserId?: string | null;
}
