export enum Permission {
  AccountDefault = 1,
  AccountUpdate,
  AccountDelete,
  EmailChange,
  ChangePassword,

  RoleDefault,
  RoleCreate,
  RoleUpdate,
  RoleDelete,

  UserCreate,
  UserUpdate,

  InstitutionUserCreate,
  InstitutionUserUpdate,

  NotificationDefault,
  NotificationCreate,
  UserNotificationDefault,

  InstituteCreate,
  InstituteGetForAdmin,
  InstituteGetAll,
  InstituteDelete,
  InstituteUpdateForAdmin,

  InstituteDefault,
  InstituteUpdate,

  CourseDefault,
  CourseGetAllInInstitute,
  CourseCreate,
  CourseUpdate,
  CourseDelete,

  ExamDefault,
  ExamCreate,
  ExamUpdate,
  ExamDelete,

  QuestionDefault,
  QuestionCreate,
  QuestionUpdate,
  QuestionDelete,

  AnswerDefault,
  AnswerCreate,
  AnswerUpdate,
  AnswerDelete,

  StudentDefault,
  StudentCreate,
  StudentUpdate,
  StudentDelete,

  PaymentCheckout,

  ProgramOutcomeDefault,
  ProgramOutcomeCreate,
  ProgramOutcomeUpdate,
  ProgramOutcomeDelete,

  CourseObjectiveDefault,
  CourseObjectiveCreate,
  CourseObjectiveUpdate,
  CourseObjectiveDelete,

  // Platform-level tenant access. Keep appended to preserve existing numeric IDs.
  TenantReadAll,
  TenantWriteAll,

  PromoCampaignManage,

  AuditLogRead
}
