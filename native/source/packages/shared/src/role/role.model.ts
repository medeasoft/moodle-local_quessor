export interface PermissionDto {
  id: number;
  name: string;
}

export interface RoleDto {
  id: string;
  name: string;
  permissions: PermissionDto[];
  creationTime: number | null;
  creatorUserId: string | null;
  lastModificationTime: number | null;
  lastModifierUserId: string | null;
  deletionTime: number | null;
  deleterUserId: string | null;
}
