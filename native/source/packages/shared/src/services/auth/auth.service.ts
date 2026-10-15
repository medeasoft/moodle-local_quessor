import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import {
  AccessTokenDto,
  MoodleSsoResponseDto,
  ProviderLoginDto,
  RefreshTokenDto,
  ActivateAccountDto
} from "../../auth";

export class AuthService {
  constructor(private readonly httpClient: IHttpClient) {}

  async login(email: string, password: string, config?: HttpRequestConfig) {
    return this.httpClient.post<AccessTokenDto>("/auth/login", { email, password }, config);
  }

  async verifyOtp(email: string, code: string, config?: HttpRequestConfig) {
    return this.httpClient.post<AccessTokenDto>("/auth/verify-otp", { email, code }, config);
  }

  async refreshOtp(email: string, config?: HttpRequestConfig) {
    return this.httpClient.post("auth/refresh-otp", { email }, config);
  }

  async refresh(dto: RefreshTokenDto, config?: HttpRequestConfig) {
    return this.httpClient.post<AccessTokenDto>("/auth/refresh", dto, config);
  }

  async revoke(refreshToken: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/auth/revoke", { refreshToken }, config);
  }

  async activate(dto: ActivateAccountDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/auth/activate", dto, config);
  }

  async socialLogin(dto: ProviderLoginDto, provider: "apple" | "google" | "facebook", config?: HttpRequestConfig) {
    return this.httpClient.post<AccessTokenDto>(`/auth/login/${provider}`, dto, config);
  }

  async exchangeMoodleSso(token: string, config?: HttpRequestConfig) {
    return this.httpClient.post<MoodleSsoResponseDto>("/moodle/sso/exchange", { token }, config);
  }
}
