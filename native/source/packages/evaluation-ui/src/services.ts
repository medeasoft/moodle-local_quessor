import {
  AnswerService,
  CourseObjectiveService,
  ContextService,
  EvaluateStudentAnswersService,
  ExamResultMailingService,
  ExamService,
  ExtractStudentPagesService,
  InstructorRecommendationService,
  ProgramOutcomeService,
  QuestionService,
  StudentService,
  type IHttpClient,
} from "@fullstack/shared";

export function createEvaluationServices(httpClient: IHttpClient) {
  return {
    examService: new ExamService(httpClient),
    instructorRecommendationService: new InstructorRecommendationService(httpClient),
    questionService: new QuestionService(httpClient),
    answerService: new AnswerService(httpClient),
    studentService: new StudentService(httpClient),
    extractStudentPagesService: new ExtractStudentPagesService(httpClient),
    evaluateStudentAnswersService: new EvaluateStudentAnswersService(httpClient),
    examResultMailingService: new ExamResultMailingService(httpClient),
    programOutcomeService: new ProgramOutcomeService(httpClient),
    courseObjectiveService: new CourseObjectiveService(httpClient),
    contextService: new ContextService(httpClient),
  };
}

export type EvaluationServices = ReturnType<typeof createEvaluationServices>;
