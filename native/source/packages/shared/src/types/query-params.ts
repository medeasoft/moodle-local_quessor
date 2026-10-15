import { z } from "zod";

export enum FilterOperator {
  ILIKE = "iLike",
  NOT_ILIKE = "notILike",
  EQ = "eq",
  NE = "ne",
  IN_ARRAY = "inArray",
  NOT_IN_ARRAY = "notInArray",
  IS_EMPTY = "isEmpty",
  IS_NOT_EMPTY = "isNotEmpty",
  LT = "lt",
  LTE = "lte",
  GT = "gt",
  GTE = "gte",
  IS_BETWEEN = "isBetween",
  IS_RELATIVE_TO_TODAY = "isRelativeToToday"
}

export const FilterSchema = z.object({
  field: z.string(),
  operator: z.nativeEnum(FilterOperator),
  value: z.string().optional().nullable()
});

export type Filter = z.infer<typeof FilterSchema>;

export const QueryParametersSchema = z.preprocess(
  (data: any) => {
    if (typeof data !== "object" || data === null) return data;

    // Create a copy to avoid mutating original if needed, or just mutate
    const processed = { ...data };

    // Coerce page and pageSize to numbers
    if (typeof processed.page === "string") {
      processed.page = parseInt(processed.page, 10);
    }
    if (typeof processed.pageSize === "string") {
      processed.pageSize = parseInt(processed.pageSize, 10);
    }

    if (processed.filters && typeof processed.filters === "object" && !Array.isArray(processed.filters)) {
      processed.filters = Object.values(processed.filters);
    }

    return processed;
  },
  z.object({
    page: z.number().int().min(1).optional().default(1),
    pageSize: z.number().int().min(1).max(10000).optional().default(10),
    sortBy: z.string().optional().nullable(),
    sortDirection: z.enum(["ASC", "DESC"]).optional().nullable(),
    filters: z.array(FilterSchema).optional().default([]),
    searchTerm: z.string().optional().nullable(),
    searchFields: z.string().optional().nullable()
  })
);

export type QueryParameters = z.infer<typeof QueryParametersSchema>;
