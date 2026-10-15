import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import { UpdateUserDto, RegisterDeviceDto } from "../../user";
import { UserDto } from "../../account";

export class UserService {
  constructor(private readonly httpClient: IHttpClient) { }

  async GetById(id: string, config?: HttpRequestConfig) {
    return this.httpClient.get<UserDto>(`/users/${id}`, config);
  }

  async GetForSelect(config?: HttpRequestConfig) {
    return this.httpClient.get<{ id: string; fullName: string; email: string }[]>("/users/select", config);
  }

  async Update(id: string, dto: UpdateUserDto, config?: HttpRequestConfig) {
    return this.httpClient.put<UserDto>(`/users/institute/me/${id}`, dto, config);
  }

  async UpdateForAdmin(instituteId: string, id: string, dto: UpdateUserDto, config?: HttpRequestConfig) {
    return this.httpClient.put<UserDto>(`/users/institute/${instituteId}/${id}`, dto, config);
  }

  async RemoveFromInstitute(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/users/institute/me/${id}`, config);
  }

  async RegisterDevice(data: RegisterDeviceDto, config?: HttpRequestConfig) {
    return this.httpClient.post<void>("/users/device", data, config);
  }
}
