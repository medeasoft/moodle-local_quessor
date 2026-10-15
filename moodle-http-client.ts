/* This file is part of Moodle - http://moodle.org/.
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @copyright 2026 Medeasoft
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { HttpRequestConfig, IHttpClient, ResponseDto } from "@fullstack/shared";

export interface MoodleHttpClientOptions {
  endpoint: string;
  cmid: number;
  sesskey: string;
  locale: string;
}

declare global {
  interface Window {
    localQuessorExternalCall?: (action: string, args: Record<string, unknown>) => Promise<string>;
  }
}

async function callExternalService<T>(action: string, args: Record<string, unknown>): Promise<T> {
  if (!window.localQuessorExternalCall) {
    await new Promise<void>((resolve, reject) => {
      const timer = window.setTimeout(() => {
        window.removeEventListener("local-quessor:external-ready", onReady);
        reject(new Error("Moodle External Services could not be initialized."));
      }, 10000);
      const onReady = () => {
        window.clearTimeout(timer);
        resolve();
      };
      window.addEventListener("local-quessor:external-ready", onReady, { once: true });
      if (window.localQuessorExternalCall) onReady();
    });
  }
  const encoded = await window.localQuessorExternalCall!(action, args);
  return JSON.parse(encoded) as T;
}

export type MoodleSubmissionItem = {
  id: string;
  moodleUserId: string;
  studentName: string;
  status: "ready" | "evaluating" | "completed" | "failed" | string;
  grade: number | null;
  feedback: string | null;
};

export type MoodleSubmissionSummary = {
  total: number;
  ready: number;
  evaluating: number;
  completed: number;
  failed: number;
  items: MoodleSubmissionItem[];
};

type MoodleEvaluationStartResult = {
  state: "evaluating";
  queuedSubmissionCount: number;
};

type MoodleGradeRefreshResult = {
  updated: number;
  submissions: MoodleSubmissionSummary;
};

export class MoodleHttpError extends Error {
  response: { status: number; data: unknown };

  constructor(status: number, data: unknown) {
    const errors = (data as { error?: { errors?: unknown } } | null)?.error?.errors;
    const detail = Array.isArray(errors) && typeof errors[0] === "string" ? errors[0] : null;
    super(detail || `Quessor Moodle proxy request failed (${status})`);
    this.response = { status, data };
  }
}

export function createMoodleHttpClient(options: MoodleHttpClientOptions): IHttpClient {
  async function request<T>(
    method: "GET" | "POST" | "PUT" | "DELETE",
    rawPath: string,
    body?: unknown,
    config?: HttpRequestConfig
  ): Promise<ResponseDto<T>> {
    const pathUrl = new URL(rawPath, "https://quessor.invalid");
    for (const [name, value] of Object.entries(config?.params ?? {})) {
      if (value != null) pathUrl.searchParams.set(name, String(value));
    }
    const path = `${pathUrl.pathname}${pathUrl.search}`;
    const headers = new Headers(config?.headers ?? {});
    headers.set("Accept", "application/json");
    headers.set("Accept-Language", options.locale);
    const fetchOptions: RequestInit = {
      method,
      credentials: "same-origin",
      headers
    };
    const actualBody = method === "DELETE" && body === undefined ? config?.data : body;
    if (actualBody instanceof FormData) {
      fetchOptions.body = actualBody;
      headers.delete("Content-Type");
    } else if (actualBody !== undefined && actualBody !== null && method !== "GET") {
      headers.set("Content-Type", "application/json");
      fetchOptions.body = JSON.stringify(actualBody);
    }

    let payload: ResponseDto<T>;
    if (actualBody instanceof FormData) {
      const proxyUrl = new URL(options.endpoint, window.location.href);
      proxyUrl.searchParams.set("action", "proxy");
      proxyUrl.searchParams.set("cmid", String(options.cmid));
      proxyUrl.searchParams.set("sesskey", options.sesskey);
      proxyUrl.searchParams.set("method", method);
      proxyUrl.searchParams.set("path", path);
      const response = await fetch(proxyUrl, fetchOptions);
      const responseBody = await response.json().catch(() => ({
        data: null,
        statusCode: response.status,
        error: { errors: ["Quessor Moodle proxy returned invalid JSON."] }
      }));
      if (!response.ok) throw new MoodleHttpError(response.status, responseBody);
      payload = responseBody as ResponseDto<T>;
    } else {
      payload = await callExternalService<ResponseDto<T>>("proxy", {
        cmid: options.cmid,
        method,
        path,
        body: typeof fetchOptions.body === "string" ? fetchOptions.body : ""
      });
    }
    return payload as ResponseDto<T>;
  }

  return {
    get: <T>(url: string, config?: HttpRequestConfig) => request<T>("GET", url, undefined, config),
    post: <T>(url: string, body?: unknown, config?: HttpRequestConfig) => request<T>("POST", url, body, config),
    put: <T>(url: string, body?: unknown, config?: HttpRequestConfig) => request<T>("PUT", url, body, config),
    delete: <T>(url: string, config?: HttpRequestConfig) => request<T>("DELETE", url, undefined, config)
  };
}

export async function bootstrapMoodleEvaluation(options: MoodleHttpClientOptions) {
  const payload = await callExternalService<{ data?: { examId?: string }; statusCode?: number }>("bootstrap", {
    cmid: options.cmid
  });
  if (!payload?.data?.examId) throw new MoodleHttpError(payload.statusCode ?? 500, payload);
  return payload.data as {
    examId: string;
    courseId: string;
    availablePages: number | null;
    instituteName: string | null;
    questionExtractionPaperId: string | null;
    questionExtractionStatus: "pending" | "processing" | "completed" | "failed" | null;
    submissions: MoodleSubmissionSummary;
    expectedReadySubmissions: number;
  };
}

export async function startMoodleEvaluation(options: MoodleHttpClientOptions): Promise<MoodleEvaluationStartResult> {
  const payload = await callExternalService<{ data?: MoodleEvaluationStartResult; statusCode?: number }>("start_evaluation", {
    cmid: options.cmid
  });
  if (!payload?.data?.state) throw new MoodleHttpError(payload.statusCode ?? 500, payload);
  return payload.data as MoodleEvaluationStartResult;
}

export async function refreshMoodleGrades(options: MoodleHttpClientOptions): Promise<MoodleGradeRefreshResult> {
  const payload = await callExternalService<{ data?: MoodleGradeRefreshResult; statusCode?: number }>("refresh_grades", {
    cmid: options.cmid
  });
  if (!payload?.data?.submissions) throw new MoodleHttpError(payload.statusCode ?? 500, payload);
  return payload.data as MoodleGradeRefreshResult;
}

export async function publishMoodleGrades(
  options: MoodleHttpClientOptions,
  ids: string[],
  includeFeedback: boolean
): Promise<MoodleGradeRefreshResult> {
  const payload = await callExternalService<{ data?: MoodleGradeRefreshResult; statusCode?: number }>("publish_grades", {
    cmid: options.cmid,
    ids,
    include_feedback: includeFeedback
  });
  if (!payload?.data?.submissions) throw new MoodleHttpError(payload.statusCode ?? 500, payload);
  return payload.data as MoodleGradeRefreshResult;
}
