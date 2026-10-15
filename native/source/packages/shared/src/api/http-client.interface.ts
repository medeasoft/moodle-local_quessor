import { ResponseDto } from "../common";

/**
 * Generic HTTP request configuration
 * Platform-specific implementations can extend this
 */
export interface HttpRequestConfig {
  headers?: Record<string, string>;
  params?: Record<string, any>;
  [key: string]: any; // Allow platform-specific config options
}

/**
 * HTTP Client Interface
 * Platform-agnostic interface for making HTTP requests.
 * Returns ResponseDto<T> — callers access the data via `.data`.
 */
export interface IHttpClient {
  /**
   * GET request
   */
  get<T>(url: string, config?: HttpRequestConfig): Promise<ResponseDto<T>>;

  /**
   * POST request
   */
  post<T>(url: string, body?: any, config?: HttpRequestConfig): Promise<ResponseDto<T>>;

  /**
   * PUT request
   */
  put<T>(url: string, body?: any, config?: HttpRequestConfig): Promise<ResponseDto<T>>;

  /**
   * DELETE request
   */
  delete<T>(url: string, config?: HttpRequestConfig): Promise<ResponseDto<T>>;
}
