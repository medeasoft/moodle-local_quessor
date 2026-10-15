import { RoleDto } from "../role";
import { InstituteDto } from "../institute/institute.model";

export interface UserDto {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  isActive?: boolean;
  isPasswordTemporary: boolean;
  isTwoFactorEnabled: boolean;
  roles: RoleDto[];
  socialAccounts: SocialAccountDto[];
  institute: InstituteDto;
  isInstituteAdmin?: boolean;
  assignedCourseNames?: string[];
  assignedCourseIds?: string[];
}

export interface SocialAccountDto {
  id: string;
  provider: string;
  providerUserId: string;
}

export interface AccountProfileDto {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  isEmailVerified: boolean;
  isPasswordTemporary: boolean;
  isTwoFactorEnabled: boolean;
  roles: RoleDto[];
  socialAccounts: SocialAccountDto[];
  institute: InstituteDto;
}

export interface AccountSettingsDto {
  isTwoFactorEnabled: boolean;
  emailNotifications: boolean;
  pushNotifications: boolean;
}
