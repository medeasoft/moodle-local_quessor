export const AuditEntityType = {
  Answer: "Answer"
} as const;

export type AuditEntityType = (typeof AuditEntityType)[keyof typeof AuditEntityType];

export const AuditAction = {
  Insert: "INSERT",
  Update: "UPDATE",
  SoftDelete: "SOFT_DELETE"
} as const;

export type AuditAction = (typeof AuditAction)[keyof typeof AuditAction];

export const AuditActorType = {
  User: "user",
  Ai: "ai",
  System: "system"
} as const;

export type AuditActorType = (typeof AuditActorType)[keyof typeof AuditActorType];
