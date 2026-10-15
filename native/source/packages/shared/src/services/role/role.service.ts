import { IHttpClient } from "../../api/http-client.interface";
import { HttpRequestConfig } from "../../api";
import { CreateRoleDto, UpdateRoleDto, RoleDto, PermissionDto } from "../../role";
import { PagedResponse } from "@fullstack/shared/types/page.dto";

export class RoleService {
  constructor(private readonly httpClient: IHttpClient) {}

  async GetAll(query?: string, config?: HttpRequestConfig) {
    return this.httpClient.get<PagedResponse<RoleDto>>(`/roles${query ? `?${query}` : ""}`, config);
  }

  async GetById(id: string, config?: HttpRequestConfig) {
    return this.httpClient.get<RoleDto>(`/roles/${id}`, config);
  }

  async Create(dto: CreateRoleDto, config?: HttpRequestConfig) {
    return this.httpClient.post<RoleDto>("/roles", dto, config);
  }

  async Update(id: string, dto: UpdateRoleDto, config?: HttpRequestConfig) {
    return this.httpClient.put<RoleDto>(`/roles/${id}`, dto, config);
  }

  async Delete(id: string, config?: HttpRequestConfig) {
    return this.httpClient.delete<void>(`/roles/${id}`, config);
  }

  async GetAllPermissions(config?: HttpRequestConfig) {
    return this.httpClient.get<PermissionDto[]>("/roles/permissions", config);
  }
}
