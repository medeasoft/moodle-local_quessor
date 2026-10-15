import { IHttpClient, HttpRequestConfig } from "../../api";
import { CreateCourseDto, UpdateCourseDto, CourseDto } from "../../course";
import { PagedResponse } from "@fullstack/shared/types";

export class CourseService {
    constructor(private readonly httpClient: IHttpClient) { }

    async Create(dto: CreateCourseDto, config?: HttpRequestConfig) {
        return this.httpClient.post<CourseDto>("/courses", dto, config);
    }

    async GetAll(query?: string, config?: HttpRequestConfig) {
        return this.httpClient.get<PagedResponse<CourseDto>>(`/courses${query ? `?${query}` : ""}`, config);
    }

    async GetById(id: string, config?: HttpRequestConfig) {
        return this.httpClient.get<CourseDto>(`/courses/${id}`, config);
    }

    async Update(id: string, dto: UpdateCourseDto, config?: HttpRequestConfig) {
        return this.httpClient.put<CourseDto>(`/courses/${id}`, dto, config);
    }

    async Delete(id: string, config?: HttpRequestConfig) {
        return this.httpClient.delete<void>(`/courses/${id}`, config);
    }
}
