export type SystemLogLevel = "error" | "warn" | "info" | "debug";

export interface SystemLogDto {
  id: number;
  level: string;
  message: string;
  meta: Record<string, unknown> | null;
  createdAt: number;
}

export interface SystemLogQueryDto {
  page?: number;
  pageSize?: number;
  level?: SystemLogLevel | "all";
  search?: string;
  path?: string;
  method?: string;
  userId?: string;
  instituteId?: string;
  from?: number;
  to?: number;
  includeMetaSearch?: boolean;
}
