import { z } from "zod";
import { AuditAction, AuditActorType, AuditEntityType } from "./audit.enums";

export const auditLogQuerySchema = z.preprocess(
  (data: unknown) => {
    if (typeof data !== "object" || data === null) return data;

    const processed = { ...(data as Record<string, unknown>) };
    for (const key of ["page", "pageSize", "from", "to"]) {
      if (typeof processed[key] === "string" && processed[key] !== "") {
        processed[key] = Number(processed[key]);
      }
    }
    for (const key of ["entityType", "entityId", "examId", "studentId", "questionId", "actorUserId"]) {
      if (processed[key] === "") processed[key] = undefined;
    }

    return processed;
  },
  z.object({
    page: z.number().int().min(1).optional().default(1),
    pageSize: z.number().int().min(1).max(100).optional().default(25),
    entityType: z.enum([AuditEntityType.Answer]).optional(),
    entityId: z.string().uuid().optional(),
    examId: z.string().uuid().optional(),
    studentId: z.string().uuid().optional(),
    questionId: z.string().uuid().optional(),
    actorUserId: z.string().uuid().optional(),
    from: z.number().int().optional().nullable(),
    to: z.number().int().optional().nullable()
  })
);

export const auditLogDtoSchema = z.object({
  id: z.string().uuid(),
  entityType: z.string(),
  entityId: z.string().uuid(),
  action: z.enum([AuditAction.Insert, AuditAction.Update, AuditAction.SoftDelete]),
  changes: z.record(z.string(), z.object({ from: z.unknown(), to: z.unknown() })),
  actorUserId: z.string().uuid().nullable(),
  actorType: z.enum([AuditActorType.User, AuditActorType.Ai, AuditActorType.System]),
  actorSource: z.string().nullable(),
  actorDisplayName: z.string().nullable(),
  instituteId: z.string().uuid().nullable(),
  examId: z.string().uuid().nullable(),
  studentId: z.string().uuid().nullable(),
  questionId: z.string().uuid().nullable(),
  occurredAt: z.number().int()
});
