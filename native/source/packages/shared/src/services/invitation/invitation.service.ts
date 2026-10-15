import { AcceptInvitationDto, InviteUserDto, PendingInvitationDto } from "../../invitation";
import { IHttpClient } from "../../api";
import { HttpRequestConfig } from "../../api";

export class InvitationService {
  constructor(private readonly httpClient: IHttpClient) { }

  async InviteUserToMyInstitute(dto: InviteUserDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/invitation/institute/me/invite", dto, config);
  }

  async InviteUser(instituteId: string, dto: InviteUserDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(`/invitation/institute/${instituteId}/invite`, dto, config);
  }

  async GetPendingInvitationsForMyInstitute(config?: HttpRequestConfig) {
    return this.httpClient.get<PendingInvitationDto[]>("/invitation/institute/me/pending", config);
  }

  async CancelInvitationForMyInstitute(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/invitation/institute/me/${id}`, config);
  }

  async GetPendingInvitations(instituteId: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PendingInvitationDto[]>(`/invitation/institute/${instituteId}/pending`, config);
  }

  async CancelInvitation(instituteId: string, id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/invitation/institute/${instituteId}/${id}`, config);
  }

  async ResendInvitationForMyInstitute(id: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(`/invitation/institute/me/${id}/resend`, {}, config);
  }

  async ResendInvitation(instituteId: string, id: string, config?: HttpRequestConfig) {
    return this.httpClient.post<void>(`/invitation/institute/${instituteId}/${id}/resend`, {}, config);
  }

  async AcceptInvitation(dto: AcceptInvitationDto, config?: HttpRequestConfig) {
    return this.httpClient.post("/invitation/accept", dto, config);
  }
}
