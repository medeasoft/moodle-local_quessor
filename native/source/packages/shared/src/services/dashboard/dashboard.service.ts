import { IHttpClient, HttpRequestConfig } from "../../api";
import { AdminStatsDto, InstituteStatsDto, MemberStatsDto } from "../../dashboard";

export class DashboardService {
  constructor(private readonly httpClient: IHttpClient) {}

  async getAdminStats(config?: HttpRequestConfig) {
    return this.httpClient.get<AdminStatsDto>("/dashboard/admin-stats", config);
  }

  async getInstituteStats(config?: HttpRequestConfig) {
    return this.httpClient.get<InstituteStatsDto>("/dashboard/institute-stats", config);
  }

  async getMemberStats(config?: HttpRequestConfig) {
    return this.httpClient.get<MemberStatsDto>("/dashboard/member-stats", config);
  }
}
