import { IHttpClient, HttpRequestConfig } from "../../api";
import { CreateStudentDto, UpdateStudentDto, StudentDto, BulkCreateStudentDto, BulkDeleteStudentDto, BulkMoveStudentDto, BulkCopyStudentDto } from "../../student";
import { PagedResponse } from "@fullstack/shared/types";

export class StudentService {
    constructor(private readonly httpClient: IHttpClient) { }

    async Create(dto: CreateStudentDto, config?: HttpRequestConfig) {
        return this.httpClient.post<StudentDto>("/students", dto, config);
    }

    async BulkCreate(dto: BulkCreateStudentDto, config?: HttpRequestConfig) {
        return this.httpClient.post<StudentDto[]>("/students/bulk", dto, config);
    }

    async BulkDelete(dto: BulkDeleteStudentDto, config?: HttpRequestConfig) {
        return this.httpClient.delete<void>("/students/bulk", { ...config, data: dto });
    }

    async BulkMove(dto: BulkMoveStudentDto, config?: HttpRequestConfig) {
        return this.httpClient.post<void>("/students/bulk-move", dto, config);
    }

    async BulkCopy(dto: BulkCopyStudentDto, config?: HttpRequestConfig) {
        return this.httpClient.post<void>("/students/bulk-copy", dto, config);
    }

    async GetAll(query?: string, config?: HttpRequestConfig) {
        return this.httpClient.get<PagedResponse<StudentDto>>(`/students${query ? `?${query}` : ""}`, config);
    }

    async GetById(id: string, config?: HttpRequestConfig) {
        return this.httpClient.get<StudentDto>(`/students/${id}`, config);
    }

    async Update(id: string, dto: UpdateStudentDto, config?: HttpRequestConfig) {
        return this.httpClient.put<StudentDto>(`/students/${id}`, dto, config);
    }

    async Delete(id: string, config?: HttpRequestConfig) {
        return this.httpClient.delete<void>(`/students/${id}`, config);
    }
}
