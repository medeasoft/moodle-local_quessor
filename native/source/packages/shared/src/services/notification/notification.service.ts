import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import { CreateNotificationDto, NotificationDto, UserNotificationDto } from "../../notification";

export class NotificationService {
  constructor(private readonly httpClient: IHttpClient) {}

  async Create(dto: CreateNotificationDto, config?: HttpRequestConfig) {
    return this.httpClient.post<NotificationDto>("notifications", dto, config);
  }

  async GetAll(config?: HttpRequestConfig) {
    return this.httpClient.get<NotificationDto[]>("notifications", config);
  }

  async GetMy(config?: HttpRequestConfig) {
    return this.httpClient.get<UserNotificationDto[]>("notifications/my", config);
  }

  async MarkAsRead(id: string, config?: HttpRequestConfig) {
    return this.httpClient.put<void>(`notifications/${id}/read`, {}, config);
  }
}
