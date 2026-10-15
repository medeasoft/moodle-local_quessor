import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import {
  RegisterDto,
  UpdateProfileDto,
  UpdateSecurityDto,
  LinkProviderDto,
  SocialAccountDto,
  UserDto
} from "../../account";

export class AccountService {
  constructor(private readonly httpClient: IHttpClient) {}

  async Register(dto: RegisterDto, config?: HttpRequestConfig) {
    return this.httpClient.post<UserDto>("/account/register", dto, config);
  }

  async GetProfile(config?: HttpRequestConfig) {
    return this.httpClient.get<UserDto>("/account/profile", config);
  }

  async UpdateProfile(dto: UpdateProfileDto, config?: HttpRequestConfig) {
    return this.httpClient.put<UserDto>("/account/profile", dto, config);
  }

  async UpdateSecuritySettings(dto: UpdateSecurityDto, config?: HttpRequestConfig) {
    return this.httpClient.put<UserDto>("/account/security-settings", dto, config);
  }

  async DeleteAccount(config?: HttpRequestConfig) {
    return this.httpClient.delete<void>("/account/profile", config);
  }

  async EmailConfirmationTokenRequest(email: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/email-confirmation-request", { email }, config);
  }

  async ResetPasswordRequest(email: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/reset-password-request", { email }, config);
  }

  async ResetPassword(email: string, token: string, newPassword: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/reset-password", { email, token, newPassword }, config);
  }

  async ConfirmEmail(email: string, token: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/confirm-email", { email, token }, config);
  }

  async ChangeEmailRequest(newEmail: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/change-email-request", { newEmail }, config);
  }

  async ChangeEmail(token: string, newEmail: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/change-email", { token, newEmail }, config);
  }

  async ChangePassword(currentPassword: string, newPassword: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/change-password", { currentPassword, newPassword }, config);
  }

  async SetNewPassword(newPassword: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/account/set-new-password", { newPassword }, config);
  }

  async LinkProvider(dto: LinkProviderDto, type: "apple" | "google" | "facebook", config?: HttpRequestConfig) {
    return this.httpClient.post<SocialAccountDto>(`/account/social-account/link/${type}`, dto, config);
  }

  async UnlinkProvider(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/account/social-account/unlink/${id}`, config);
  }
}
