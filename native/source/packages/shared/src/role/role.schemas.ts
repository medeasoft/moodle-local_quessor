import { z } from "zod";
import { Permission } from "../permission";

// Create a Zod enum from Permission enum
const PermissionEnum = z.enum(Permission);

export const createRoleSchema = z.object({
  name: z.string().min(3, "role_create_name_length").max(50, "role_create_name_length"),
  permissionIds: z.array(PermissionEnum).min(1, "role_create_permissions_required")
});

export const updateRoleSchema = z.object({
  name: z.string().min(3, "role_create_name_length").max(50, "role_create_name_length"),
  permissionIds: z.array(PermissionEnum).min(1, "role_create_permissions_required")
});

// Type exports
export type CreateRoleDto = z.infer<typeof createRoleSchema>;
export type UpdateRoleDto = z.infer<typeof updateRoleSchema>;
