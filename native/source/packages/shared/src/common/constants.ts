export const MAX_FILE_SIZE_MB = 100;
export const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

/**
 * Max size for parsed JSON / urlencoded request bodies.
 * Real file uploads go through multipart (multer) with their own limits,
 * so JSON bodies only ever carry small metadata. Keeping this low protects
 * the process from allocating huge buffers per request (OOM protection).
 */
export const MAX_JSON_BODY_MB = 10;
