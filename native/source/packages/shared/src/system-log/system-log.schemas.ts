import { z } from "zod";

export const systemLogQuerySchema = z.preprocess(
  (data: any) => {
    if (typeof data !== "object" || data === null) return data;

    const processed = { ...data };
    for (const key of ["page", "pageSize", "from", "to"]) {
      if (typeof processed[key] === "string" && processed[key] !== "") {
        processed[key] = Number(processed[key]);
      }
    }

    if (typeof processed.includeMetaSearch === "string") {
      processed.includeMetaSearch = processed.includeMetaSearch === "true";
    }

    return processed;
  },
  z.object({
    page: z.number().int().min(1).optional().default(1),
    pageSize: z.number().int().min(1).max(100).optional().default(25),
    level: z.enum(["error", "warn", "info", "debug", "all"]).optional().default("all"),
    search: z.string().trim().max(200).optional().nullable(),
    path: z.string().trim().max(300).optional().nullable(),
    method: z.string().trim().max(20).optional().nullable(),
    userId: z.string().trim().max(100).optional().nullable(),
    instituteId: z.string().trim().max(100).optional().nullable(),
    from: z.number().int().optional().nullable(),
    to: z.number().int().optional().nullable(),
    includeMetaSearch: z.boolean().optional().default(false)
  })
);
