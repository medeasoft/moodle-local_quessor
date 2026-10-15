export interface ResponseDto<T> {
  data: T | null;
  statusCode: number;
  error: ErrorDto | null;
}

export interface ErrorDto {
  errors: string[];
  isShow: boolean;
}
