import type { AuditAction, AuditActorType, AuditEntityType } from "./audit.enums";

export type AuditChangeValue = {
  from: unknown;
  to: unknown;
};

export type AuditChanges = Record<string, AuditChangeValue>;

export interface AuditLogDto {
  id: string;
  entityType: AuditEntityType | string;
  entityId: string;
  action: AuditAction | string;
  changes: AuditChanges;
  actorUserId: string | null;
  actorType: AuditActorType | string;
  actorSource: string | null;
  actorDisplayName: string | null;
  instituteId: string | null;
  examId: string | null;
  studentId: string | null;
  questionId: string | null;
  occurredAt: number;
}

export interface AuditLogQueryDto {
  page?: number;
  pageSize?: number;
  entityType?: string;
  entityId?: string;
  examId?: string;
  studentId?: string;
  questionId?: string;
  actorUserId?: string;
  from?: number;
  to?: number;
}
