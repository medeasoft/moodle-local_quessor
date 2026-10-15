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

import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  DEFAULT_FEEDBACK_LANGUAGE,
  QuestionType,
  parseFeedbackLanguage,
  type AnswerDto,
  type ExamPdfWithFlowLayer,
  type FeedbackLanguageCode,
  type QuestionDto
} from "@fullstack/shared";
import {
  SharedEvaluationWizardProvider,
  createEvaluationServices,
  useSharedEvaluationData,
  useSharedEvaluationWizard,
  type EvaluationServices,
  type EvaluationWizardStep
} from "@quessor/evaluation-ui";
import {
  bootstrapMoodleEvaluation,
  createMoodleHttpClient,
  refreshMoodleGrades,
  publishMoodleGrades,
  startMoodleEvaluation,
  type MoodleHttpClientOptions,
  type MoodleSubmissionSummary
} from "./moodle-http-client";
import { Check, ChevronRight, LoaderCircle, Minus, X } from "lucide-react";

interface NativeConfig extends MoodleHttpClientOptions {
  labels: Record<string, string>;
  questionSourceAttached?: boolean;
}

const MOODLE_EVALUATION_STEPS: EvaluationWizardStep[] = ["questions", "evaluation-criteria", "preview"];

declare global {
  interface Window {
    QuessorEvaluation?: { mount: (selector: string) => void };
  }
}

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false, staleTime: 15_000, refetchOnWindowFocus: false }
  }
});

function mount(selector: string) {
  const element = document.querySelector<HTMLElement>(selector);
  if (!element || element.dataset.qevMounted === "1" || !element.dataset.config) return;
  const config = JSON.parse(element.dataset.config) as NativeConfig;
  element.dataset.qevMounted = "1";
  createRoot(element).render(
    <QueryClientProvider client={queryClient}>
      <MoodleEvaluationBootstrap config={config} />
    </QueryClientProvider>
  );
}

function MoodleEvaluationBootstrap({ config }: { config: NativeConfig }) {
  const queryCache = useQueryClient();
  const [bootstrap, setBootstrap] = useState<Awaited<ReturnType<typeof bootstrapMoodleEvaluation>> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const requestedStep = window.location.hash.replace(/^#/, "") as EvaluationWizardStep;
  const initialStep = requestedStep === "result" ? "preview" : requestedStep;
  const [currentStep, setCurrentStep] = useState<EvaluationWizardStep>(
    MOODLE_EVALUATION_STEPS.includes(initialStep) ? initialStep : "questions"
  );
  const client = useMemo(() => createMoodleHttpClient(config), [config]);
  const services = useMemo(() => createEvaluationServices(client), [client]);
  const refreshGrades = useCallback(async () => {
    const result = await refreshMoodleGrades(config);
    setBootstrap((current) => (current ? { ...current, submissions: result.submissions } : current));
    return result;
  }, [config]);
  const publishGrades = useCallback(
    async (ids: string[], includeFeedback: boolean) => {
      const result = await publishMoodleGrades(config, ids, includeFeedback);
      setBootstrap((current) => (current ? { ...current, submissions: result.submissions } : current));
      return result;
    },
    [config]
  );

  useEffect(() => {
    let active = true;
    bootstrapMoodleEvaluation(config)
      .then((value) => active && setBootstrap(value))
      .catch((reason) => active && setError(reason instanceof Error ? reason.message : String(reason)));
    return () => {
      active = false;
    };
  }, [config]);

  // The first page request synchronously imports one submission and queues
  // the rest. Keep the initial view in sync with Moodle while those queued
  // imports are being processed instead of requiring a manual refresh.
  useEffect(() => {
    if (!bootstrap || bootstrap.submissions.total >= bootstrap.expectedReadySubmissions) return;
    let active = true;
    let attempts = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const poll = async () => {
      if (!active || attempts >= 40) return;
      attempts += 1;
      try {
        const next = await bootstrapMoodleEvaluation(config);
        if (!active) return;
        const receivedNewSubmissions = next.submissions.total > bootstrap.submissions.total;
        setBootstrap(next);
        if (receivedNewSubmissions) {
          await queryCache.invalidateQueries({ queryKey: ["moodle-evaluation-papers", next.examId] });
        }
        if (next.submissions.total < next.expectedReadySubmissions) {
          timer = setTimeout(poll, 1500);
        }
      } catch {
        if (active) timer = setTimeout(poll, 1500);
      }
    };
    timer = setTimeout(poll, 1000);
    return () => {
      active = false;
      if (timer) clearTimeout(timer);
    };
  }, [bootstrap?.expectedReadySubmissions, bootstrap?.submissions.total, config, queryCache]);

  if (error) return <NativeState kind="error" message={`${config.labels.loadError}: ${error}`} />;
  if (!bootstrap) return <NativeState kind="loading" message={config.labels.loading} />;

  const navigate = (step: EvaluationWizardStep) => {
    setCurrentStep(step);
    window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}#${step}`);
  };
  return (
    <SharedEvaluationWizardProvider
      examId={bootstrap.examId}
      currentStep={currentStep}
      onNavigate={navigate}
      services={services}
      steps={MOODLE_EVALUATION_STEPS}
      includeStudentPages={false}
      onBlockedStep={() => setNotice(config.labels.stepUnavailable)}
      onDataError={(reason) => setError(reason instanceof Error ? reason.message : config.labels.loadError)}
    >
      {notice && (
        <div className="qev-native-notice" role="status">
          <span>{notice}</span>
          <button type="button" aria-label={config.labels.cancel} onClick={() => setNotice(null)}>
            <X size={17} aria-hidden="true" />
          </button>
        </div>
      )}
      <MoodleEvaluationWizard
        services={services}
        submissions={bootstrap.submissions}
        startEvaluation={() => startMoodleEvaluation(config)}
        refreshGrades={refreshGrades}
        publishGrades={publishGrades}
        labels={config.labels}
        questionSourceAttached={config.questionSourceAttached === true}
        questionExtractionPaperId={bootstrap.questionExtractionPaperId}
        sesskey={config.sesskey}
      />
    </SharedEvaluationWizardProvider>
  );
}

function NativeState({ kind, message }: { kind: "loading" | "error"; message: string }) {
  return (
    <div className={`qev-native-state qev-native-state-${kind}`}>
      {kind === "loading" && <span className="qev-native-spinner" />}
      <span>{message}</span>
    </div>
  );
}

function MoodleEvaluationWizard({
  services,
  submissions,
  startEvaluation,
  refreshGrades,
  publishGrades,
  labels,
  questionSourceAttached,
  questionExtractionPaperId,
  sesskey
}: {
  services: EvaluationServices;
  submissions: MoodleSubmissionSummary;
  startEvaluation: () => Promise<{ state: "evaluating"; queuedSubmissionCount: number }>;
  refreshGrades: () => Promise<unknown>;
  publishGrades: (ids: string[], includeFeedback: boolean) => Promise<unknown>;
  labels: Record<string, string>;
  questionSourceAttached: boolean;
  questionExtractionPaperId: string | null;
  sesskey: string;
}) {
  const data = useSharedEvaluationData();
  return (
    <section className="qev-native-wizard qev-native-one-page">
      <header className="qev-native-header">
        <div>
          <span>{labels.eyebrow}</span>
          <h2>{data.exam?.title ?? labels.loading}</h2>
          <p>{labels.subtitle}</p>
        </div>
        <div className="qev-native-overview" aria-label={labels.steps}>
          <div>
            <strong>{data.questions.length}</strong>
            <span>{labels.questions}</span>
          </div>
          <div>
            <strong>{submissions.total}</strong>
            <span>{labels.students}</span>
          </div>
        </div>
      </header>
      <main className="qev-native-one-page-content">
        <section className="qev-native-one-page-configuration" aria-label={labels.steps}>
          <details className="qev-native-one-page-panel" open={data.questions.length === 0}>
            <summary>
              <span>1</span>
              <div>
                <strong>{labels.questions}</strong>
                <small>
                  {data.questions.length} {labels.questions}
                </small>
              </div>
              <span className="qev-native-panel-toggle">{labels.openPanel}</span>
              <ChevronRight size={17} aria-hidden="true" />
            </summary>
            <div className="qev-native-one-page-panel-body">
              <QuestionsStep
                services={services}
                labels={labels}
                questionSourceAttached={questionSourceAttached}
                questionExtractionPaperId={questionExtractionPaperId}
                sesskey={sesskey}
              />
            </div>
          </details>
          <details className="qev-native-one-page-panel">
            <summary>
              <span>2</span>
              <div>
                <strong>{labels.criteria}</strong>
                <small>{labels.criteriaDescription}</small>
              </div>
              <span className="qev-native-panel-toggle">{labels.openPanel}</span>
              <ChevronRight size={17} aria-hidden="true" />
            </summary>
            <div className="qev-native-one-page-panel-body">
              <CriteriaStep services={services} labels={labels} />
            </div>
          </details>
        </section>
        <section className="qev-native-one-page-evaluation" aria-label={labels.preview}>
          <div className="qev-native-one-page-section-title">
            <span>3</span>
            <div>
              <strong>{labels.preview}</strong>
              <small>{labels.combinedEvaluationDescription}</small>
            </div>
          </div>
          <PreviewStep
            services={services}
            submissions={submissions}
            startEvaluation={startEvaluation}
            refreshGrades={refreshGrades}
            publishGrades={publishGrades}
            labels={labels}
          />
        </section>
      </main>
    </section>
  );
}

type QuestionDraft = {
  name: string;
  text: string;
  order: number;
  point: number;
  type: QuestionType;
  correctAnswer: string;
  criteria: string;
  contextText: string;
};

function mutationErrorMessage(error: unknown, fallback: string): string {
  const errors = (error as { response?: { data?: { error?: { errors?: unknown[] } } } })?.response?.data?.error?.errors;
  if (Array.isArray(errors) && errors.length > 0) return String(errors[0]);
  return error instanceof Error && error.message ? error.message : fallback;
}

function safeNativeLabel(labels: Record<string, string>, key: string, fallback: string): string {
  const value = labels[key];
  return value && !/^\[\[.+\]\]$/.test(value) ? value : fallback;
}

function QuestionsStep({
  services,
  labels,
  questionSourceAttached,
  questionExtractionPaperId,
  sesskey
}: {
  services: EvaluationServices;
  labels: Record<string, string>;
  questionSourceAttached: boolean;
  questionExtractionPaperId: string | null;
  sesskey: string;
}) {
  const wizard = useSharedEvaluationWizard();
  const data = useSharedEvaluationData();
  const client = useQueryClient();
  const [editing, setEditing] = useState<QuestionDto | null>(null);
  const [notice, setNotice] = useState<{ kind: "success" | "error"; text: string } | null>(null);
  const [rubricUploadOpen, setRubricUploadOpen] = useState(false);
  const [openContexts, setOpenContexts] = useState<Set<string>>(new Set());
  const [rubricFile, setRubricFile] = useState<File | null>(null);
  const [rubricPage, setRubricPage] = useState(1);
  const [draft, setDraft] = useState<QuestionDraft>({
    name: "",
    text: "",
    order: 1,
    point: 0,
    type: QuestionType.ESSAY,
    correctAnswer: "",
    criteria: "",
    contextText: ""
  });
  const sharedContextLabel = safeNativeLabel(labels, "sharedContext", "Ortak metin");
  const showSharedContextLabel = safeNativeLabel(labels, "showSharedContext", "Ortak metni göster");
  const hideSharedContextLabel = safeNativeLabel(labels, "hideSharedContext", "Ortak metni gizle");
  const extractionStatusQuery = useQuery({
    queryKey: ["question-extraction-status", wizard.examId, questionExtractionPaperId],
    queryFn: async () => {
      const response = await services.questionService.getExtractionStatus(wizard.examId, questionExtractionPaperId!);
      return response.data;
    },
    enabled: data.questions.length === 0 && Boolean(questionExtractionPaperId),
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === "completed" || status === "failed" ? false : 2000;
    }
  });
  const extractionStatus = extractionStatusQuery.data?.status;
  // A legacy attempt may have persisted its source hash before an ExamPaper
  // could be created. With no paper id there is nothing to poll, so show the
  // actionable no-questions/retry state instead of an endless spinner.
  const extractionFinished =
    !questionExtractionPaperId || extractionStatus === "completed" || extractionStatus === "failed";
  useEffect(() => {
    if (data.questions.length > 0 || extractionFinished) return;
    const timer = window.setInterval(() => {
      void client.invalidateQueries({ queryKey: ["questions", wizard.examId] });
    }, 2000);
    return () => window.clearInterval(timer);
  }, [client, data.questions.length, extractionFinished, wizard.examId]);

  useEffect(() => {
    if (extractionStatus === "completed") {
      void client.invalidateQueries({ queryKey: ["questions", wizard.examId] });
    }
  }, [client, extractionStatus, wizard.examId]);

  const update = useMutation({
    mutationFn: async () => {
      const response = await services.questionService.Update(editing!.id, {
        name: draft.name.trim(),
        text: draft.text.trim(),
        order: Number(draft.order),
        point: Number(draft.point),
        type: draft.type,
        correctAnswer: draft.correctAnswer.trim() || null,
        questionGradingInstructions: draft.criteria.trim() || null
      });
      if (response.error) throw new Error(response.error.errors?.[0] ?? labels.questionSaveFailed);
      if (editing?.context?.id && draft.contextText.trim() !== (editing.context.text ?? "").trim()) {
        const contextResponse = await services.contextService.Update(editing.context.id, {
          text: draft.contextText.trim()
        });
        if (contextResponse.error) throw new Error(contextResponse.error.errors?.[0] ?? labels.questionSaveFailed);
      }
      return response;
    },
    onSuccess: async () => {
      setEditing(null);
      setNotice({ kind: "success", text: labels.questionSaved });
      await client.invalidateQueries({ queryKey: ["questions", wizard.examId] });
    },
    onError: (error) => setNotice({ kind: "error", text: mutationErrorMessage(error, labels.questionSaveFailed) })
  });

  const generateAnswer = useMutation({
    mutationFn: async () => {
      const response = await services.questionService.generateAssessment({
        text: draft.text.trim(),
        type: draft.type,
        point: Number(draft.point),
        contextText: editing?.context?.text ?? null,
        generateCorrectAnswer: true,
        generateRubric: false,
        rubricDetail: "detailed",
        language: parseFeedbackLanguage(wizard.exam?.feedbackLanguage),
        additionalInstructions: null,
        examId: wizard.examId
      });
      if (response.error) throw new Error(response.error.errors?.[0] ?? labels.aiAnswerFailed);
      const answer = response.data?.correctAnswer?.trim();
      if (!answer) throw new Error(labels.aiAnswerFailed);
      return answer;
    },
    onSuccess: (answer) => {
      setDraft((current) => ({ ...current, correctAnswer: answer }));
      setNotice({ kind: "success", text: labels.aiAnswerGenerated });
    },
    onError: (error) => setNotice({ kind: "error", text: mutationErrorMessage(error, labels.aiAnswerFailed) })
  });

  const extractRubric = useMutation({
    mutationFn: async () => {
      if (!rubricFile) throw new Error(labels.rubricFileRequired);
      const supportedTypes = ["application/pdf", "image/jpeg", "image/png", "image/gif", "image/webp"];
      const supportedExtension = /\.(pdf|png|jpe?g|gif|webp)$/i.test(rubricFile.name);
      if ((!supportedTypes.includes(rubricFile.type) && !supportedExtension) || rubricFile.size > 15 * 1024 * 1024) {
        throw new Error(labels.rubricFileInvalid);
      }
      const isPdf = rubricFile.type === "application/pdf" || rubricFile.name.toLocaleLowerCase().endsWith(".pdf");
      const response = await services.questionService.extractSingleQuestion(
        wizard.examId,
        rubricFile,
        isPdf ? Math.max(1, Math.floor(rubricPage)) : undefined
      );
      if (response.error) throw new Error(response.error.errors?.[0] ?? labels.rubricExtractFailed);
      const rubric = response.data?.questionGradingInstructions?.trim();
      if (!rubric) throw new Error(labels.rubricNotFound);
      return rubric;
    },
    onSuccess: (rubric) => {
      setDraft((current) => ({ ...current, criteria: rubric }));
      setRubricUploadOpen(false);
      setRubricFile(null);
      setRubricPage(1);
      setNotice({ kind: "success", text: labels.rubricExtracted });
    },
    onError: (error) => setNotice({ kind: "error", text: mutationErrorMessage(error, labels.rubricExtractFailed) })
  });

  const openEdit = (question: QuestionDto) => {
    setNotice(null);
    setRubricUploadOpen(false);
    setRubricFile(null);
    setRubricPage(1);
    setEditing(question);
    setDraft({
      name: question.name,
      text: question.text,
      order: Number(question.order),
      point: Number(question.point),
      type: question.type,
      correctAnswer: question.correctAnswer ?? "",
      criteria: question.questionGradingInstructions ?? "",
      contextText: question.context?.text ?? ""
    });
  };

  const toggleContext = (contextId: string) => {
    setOpenContexts((current) => {
      const next = new Set(current);
      if (next.has(contextId)) next.delete(contextId);
      else next.add(contextId);
      return next;
    });
  };

  return (
    <div className="qev-native-stack">
      <div className="qev-native-section-heading">
        <div>
          <h3>{labels.questions}</h3>
          <p>{labels.questionsDescription}</p>
        </div>
        <div className="qev-native-heading-actions">
          <span>
            {data.questions.length} {labels.questions}
          </span>
          {questionSourceAttached && (
            <form
              method="post"
              action={`${window.location.pathname}${window.location.search}`}
              className="qev-native-reextract-form"
            >
              <input type="hidden" name="sesskey" value={sesskey} />
              <input type="hidden" name="action" value="retry_question_extraction" />
              <button type="submit" className="qev-button qev-button-secondary">
                {labels.retryQuestionExtraction}
              </button>
            </form>
          )}
        </div>
      </div>
      {notice && (
        <div className={`qev-native-message is-${notice.kind}`} role="status">
          {notice.text}
        </div>
      )}
      {data.questions.length === 0 && !extractionFinished ? (
        <div className="qev-native-question-preparing" role="status">
          <span className="qev-native-spinner" />
          <div>
            <strong>{labels.preparingQuestions}</strong>
            <p>{labels.preparingQuestionsHelp}</p>
          </div>
        </div>
      ) : data.questions.length === 0 ? (
        <div className="qev-native-message is-warning" role="status">
          <strong>{labels.noQuestions}</strong>
          <p>{labels.noQuestionsHelp}</p>
        </div>
      ) : (
        <div className="qev-table-wrap qev-native-question-table">
          <table className="qev-table">
            <thead>
              <tr>
                <th>{labels.order}</th>
                <th>{labels.questionName}</th>
                <th>{labels.questionText}</th>
                <th>{labels.points}</th>
                <th>{labels.questionType}</th>
                <th>{labels.correctAnswer}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.questions.map((question) => (
                <React.Fragment key={question.id}>
                  <tr>
                    <td>{question.order}</td>
                    <td>
                      <strong className={question.parentId ? "is-child" : ""}>
                        {question.name || <Minus size={14} aria-label={labels.noResults} />}
                      </strong>
                    </td>
                    <td>
                      <span className="qev-native-question-text" title={question.text}>
                        {question.text}
                      </span>
                      {question.context?.text && question.context.id && (
                        <button
                          type="button"
                          aria-expanded={openContexts.has(question.context.id)}
                          className="qev-native-context-toggle"
                          onClick={() => toggleContext(question.context!.id!)}
                        >
                          <ChevronRight
                            className={openContexts.has(question.context.id) ? "is-expanded" : ""}
                            size={14}
                            aria-hidden="true"
                          />{" "}
                          {openContexts.has(question.context.id) ? hideSharedContextLabel : showSharedContextLabel}
                        </button>
                      )}
                    </td>
                    <td>{question.point}</td>
                    <td>
                      <span className="qev-native-type-badge">{labels[`type_${question.type}`] ?? question.type}</span>
                    </td>
                    <td>
                      <span
                        className={question.correctAnswer ? "qev-native-answer-ready" : "qev-native-answer-missing"}
                      >
                        {question.correctAnswer ? (
                          <>
                            <Check size={14} aria-hidden="true" /> {labels.ready}
                          </>
                        ) : (
                          <Minus size={14} aria-label={labels.noResults} />
                        )}
                      </span>
                    </td>
                    <td>
                      <button type="button" onClick={() => openEdit(question)}>
                        {labels.edit}
                      </button>
                    </td>
                  </tr>
                  {question.context?.text && question.context.id && openContexts.has(question.context.id) && (
                    <tr className="qev-native-context-row">
                      <td colSpan={7}>
                        <div className="qev-native-context-panel">
                          <span>{sharedContextLabel}</span>
                          <p>{question.context.text}</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {editing && (
        <div
          className="qev-native-modal-backdrop"
          role="presentation"
          onMouseDown={(event) => event.currentTarget === event.target && setEditing(null)}
        >
          <section
            className="qev-native-editor qev-native-question-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="qev-question-editor-title"
          >
            <header>
              <div>
                <span>{labels.edit}</span>
                <h3 id="qev-question-editor-title">{editing.name}</h3>
              </div>
              <button type="button" aria-label={labels.cancel} onClick={() => setEditing(null)}>
                <X size={19} aria-hidden="true" />
              </button>
            </header>
            <label>
              {labels.questionName}
              <input value={draft.name} onChange={(event) => setDraft({ ...draft, name: event.target.value })} />
            </label>
            <label>
              {labels.questionText}
              <textarea value={draft.text} onChange={(event) => setDraft({ ...draft, text: event.target.value })} />
            </label>
            {editing.context?.text && (
              <label className="qev-native-editor-context">
                <span>{sharedContextLabel}</span>
                <textarea
                  value={draft.contextText}
                  onChange={(event) => setDraft({ ...draft, contextText: event.target.value })}
                />
              </label>
            )}
            <div className="qev-native-editor-grid">
              <label>
                {labels.order}
                <input
                  type="number"
                  min="1"
                  step="1"
                  value={draft.order}
                  onChange={(event) => setDraft({ ...draft, order: Number(event.target.value) })}
                />
              </label>
              <label>
                {labels.points}
                <input
                  type="number"
                  min="0"
                  step="0.1"
                  value={draft.point}
                  onChange={(event) => setDraft({ ...draft, point: Number(event.target.value) })}
                />
              </label>
              <label>
                {labels.questionType}
                <select
                  value={draft.type}
                  onChange={(event) => setDraft({ ...draft, type: event.target.value as QuestionType })}
                >
                  {Object.values(QuestionType).map((type) => (
                    <option key={type} value={type}>
                      {labels[`type_${type}`] ?? type}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <label>
              <span className="qev-native-label-actions">
                <span>{labels.correctAnswer}</span>
                <button
                  type="button"
                  className="qev-button qev-button-ai"
                  disabled={generateAnswer.isPending || draft.text.trim().length < 2}
                  onClick={() => generateAnswer.mutate()}
                >
                  {generateAnswer.isPending ? labels.aiAnswerGenerating : labels.aiGenerateAnswer}
                </button>
              </span>
              <textarea
                className="qev-native-question-answer"
                value={draft.correctAnswer}
                onChange={(event) => setDraft({ ...draft, correctAnswer: event.target.value })}
              />
            </label>
            <label>
              <span className="qev-native-label-actions">
                <span>{labels.criteria}</span>
                <button
                  type="button"
                  className="qev-button qev-button-upload"
                  onClick={() => setRubricUploadOpen(true)}
                >
                  {labels.addRubricFile}
                </button>
              </span>
              <textarea
                className="qev-native-question-criteria"
                value={draft.criteria}
                onChange={(event) => setDraft({ ...draft, criteria: event.target.value })}
              />
            </label>
            {notice && (
              <div className={`qev-native-message is-${notice.kind}`} role="status">
                {notice.text}
              </div>
            )}
            <footer>
              <button type="button" className="qev-button" onClick={() => setEditing(null)}>
                {labels.cancel}
              </button>
              <button
                type="button"
                className="qev-button qev-button-primary"
                disabled={
                  !draft.name.trim() || draft.text.trim().length < 2 || update.isPending || generateAnswer.isPending
                }
                onClick={() => update.mutate()}
              >
                {labels.save}
              </button>
            </footer>
          </section>
          {rubricUploadOpen && (
            <div
              className="qev-native-upload-backdrop"
              role="presentation"
              onMouseDown={(event) =>
                event.currentTarget === event.target && !extractRubric.isPending && setRubricUploadOpen(false)
              }
            >
              <section
                className="qev-native-upload-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="qev-rubric-upload-title"
              >
                <header>
                  <div>
                    <span>{labels.criteria}</span>
                    <h3 id="qev-rubric-upload-title">{labels.rubricUploadTitle}</h3>
                  </div>
                  <button
                    type="button"
                    aria-label={labels.cancel}
                    disabled={extractRubric.isPending}
                    onClick={() => setRubricUploadOpen(false)}
                  >
                    <X size={19} aria-hidden="true" />
                  </button>
                </header>
                <p>{labels.rubricUploadDescription}</p>
                <label className="qev-native-file-picker">
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,.pdf"
                    onChange={(event) => setRubricFile(event.target.files?.[0] ?? null)}
                  />
                  <span>PDF / IMG</span>
                  <strong>{rubricFile?.name ?? labels.chooseRubricFile}</strong>
                  <small>{labels.rubricFileHelp}</small>
                </label>
                {rubricFile &&
                  (rubricFile.type === "application/pdf" || rubricFile.name.toLocaleLowerCase().endsWith(".pdf")) && (
                    <label className="qev-native-pdf-page">
                      {labels.pdfPage}
                      <input
                        type="number"
                        min="1"
                        step="1"
                        value={rubricPage}
                        onChange={(event) => setRubricPage(Math.max(1, Number(event.target.value) || 1))}
                      />
                    </label>
                  )}
                {notice?.kind === "error" && (
                  <div className="qev-native-message is-error" role="alert">
                    {notice.text}
                  </div>
                )}
                <footer>
                  <button
                    type="button"
                    className="qev-button"
                    disabled={extractRubric.isPending}
                    onClick={() => setRubricUploadOpen(false)}
                  >
                    {labels.cancel}
                  </button>
                  <button
                    type="button"
                    className="qev-button qev-button-primary"
                    disabled={!rubricFile || extractRubric.isPending}
                    onClick={() => extractRubric.mutate()}
                  >
                    {extractRubric.isPending ? labels.rubricExtracting : labels.extractRubric}
                  </button>
                </footer>
              </section>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function CriteriaStep({ services, labels }: { services: EvaluationServices; labels: Record<string, string> }) {
  const wizard = useSharedEvaluationWizard();
  const client = useQueryClient();
  const [value, setValue] = useState(wizard.exam?.examGradingInstructions ?? "");
  const [feedbackLanguage, setFeedbackLanguage] = useState<FeedbackLanguageCode>(
    parseFeedbackLanguage(wizard.exam?.feedbackLanguage)
  );
  useEffect(() => {
    setValue(wizard.exam?.examGradingInstructions ?? "");
    setFeedbackLanguage(parseFeedbackLanguage(wizard.exam?.feedbackLanguage));
  }, [wizard.exam?.examGradingInstructions, wizard.exam?.feedbackLanguage]);
  const save = useMutation({
    mutationFn: async () => {
      const response = await services.examService.Update(wizard.examId, {
        examGradingInstructions: value || null,
        feedbackLanguage: feedbackLanguage || DEFAULT_FEEDBACK_LANGUAGE
      });
      if (response.error) throw new Error(response.error.errors?.[0] ?? labels.evaluationSettingsSaveFailed);
      return response.data;
    },
    onSuccess: (exam) => {
      if (exam) client.setQueryData(["exam", wizard.examId], exam);
      void client.invalidateQueries({ queryKey: ["exam", wizard.examId] });
    }
  });
  return (
    <div className="qev-native-stack">
      <h3>{labels.criteria}</h3>
      <p>{labels.criteriaDescription}</p>
      <textarea
        className="qev-native-large-textarea"
        value={value}
        onChange={(event) => setValue(event.target.value)}
      />
      <label className="qev-native-setting-field">
        <strong>{labels.feedbackLanguage}</strong>
        <span>{labels.feedbackLanguageHelp}</span>
        <select
          value={feedbackLanguage}
          onChange={(event) => setFeedbackLanguage(event.target.value as FeedbackLanguageCode)}
        >
          <option value="tr">{labels.feedbackLanguageTr}</option>
          <option value="en">{labels.feedbackLanguageEn}</option>
          <option value="bg">{labels.feedbackLanguageBg}</option>
        </select>
      </label>
      {save.isError && (
        <div className="qev-native-message is-error" role="alert">
          {mutationErrorMessage(save.error, labels.evaluationSettingsSaveFailed)}
        </div>
      )}
      <button
        className="qev-button qev-button-primary qev-native-align-end"
        type="button"
        disabled={save.isPending}
        onClick={() => save.mutate()}
      >
        {save.isPending ? labels.saving : labels.save}
      </button>
    </div>
  );
}

type StudentEvaluationRow = MoodleSubmissionSummary["items"][number] & {
  studentId: string | null;
  answers: AnswerDto[];
  assignments: Array<{ examPdfId: string; displayName: string; pages: number[] }>;
  status: string;
  gradedQuestionCount: number;
  totalScore: number;
  maxScore: number;
};

type AnswerDraft = { score: string; feedback: string };
type StudentPageImage = { examPdfId: string; displayName: string; pageNumber: number; imageUrl: string };

function answerHasScore(answer: AnswerDto): boolean {
  return answer.point != null || answer.pointFromAi != null;
}

function displayScore(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

function displayEvaluationTime(value: number): string {
  const timestamp = value < 1_000_000_000_000 ? value * 1000 : value;
  return new Intl.DateTimeFormat(undefined, {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(timestamp));
}

function PreviewStep({
  services,
  submissions,
  startEvaluation,
  refreshGrades,
  publishGrades,
  labels
}: {
  services: EvaluationServices;
  submissions: MoodleSubmissionSummary;
  startEvaluation: () => Promise<{ state: "evaluating"; queuedSubmissionCount: number }>;
  refreshGrades: () => Promise<unknown>;
  publishGrades: (ids: string[], includeFeedback: boolean) => Promise<unknown>;
  labels: Record<string, string>;
}) {
  const wizard = useSharedEvaluationWizard();
  const data = useSharedEvaluationData();
  const client = useQueryClient();
  const [notice, setNotice] = useState<{ kind: "success" | "error"; text: string } | null>(null);
  const [showPublishPreview, setShowPublishPreview] = useState(false);
  const [includeFeedback, setIncludeFeedback] = useState(false);
  const [search, setSearch] = useState("");
  const [selectedSubmissionId, setSelectedSubmissionId] = useState<string | null>(null);
  const [gradingScope, setGradingScope] = useState<"all" | string | null>(null);
  const [drafts, setDrafts] = useState<Record<string, AnswerDraft>>({});
  const [pageZoom, setPageZoom] = useState(1);
  const [isPanningPages, setIsPanningPages] = useState(false);
  const pagePan = useRef<{ pointerId: number; x: number; y: number; scrollLeft: number; scrollTop: number } | null>(
    null
  );
  const lastGradeRefresh = useRef<string | null>(null);
  const progress = useQuery({
    queryKey: ["evaluation-progress", wizard.examId],
    queryFn: async () => (await services.evaluateStudentAnswersService.getProgress(wizard.examId)).data,
    refetchInterval: (query) =>
      query.state.data?.state === "processing" ||
      (query.state.data?.state === "pending" && Boolean(query.state.data?.runId))
        ? 3000
        : false
  });
  const isRunning = progress.data?.state === "processing";
  const papers = useQuery<ExamPdfWithFlowLayer[]>({
    queryKey: ["moodle-evaluation-papers", wizard.examId],
    queryFn: async () => {
      const response = await services.extractStudentPagesService.getExamPdfs(wizard.examId);
      return Array.isArray(response.data) ? response.data : [];
    }
  });
  const gradableQuestions = useMemo(
    () =>
      data.questions
        .filter((question) => !question.isGroup)
        .sort((left, right) => Number(left.order) - Number(right.order)),
    [data.questions]
  );
  const rows = useMemo<StudentEvaluationRow[]>(() => {
    const assignmentsByPrimaryId = new Map<string, StudentEvaluationRow["assignments"]>();
    const studentIdByPrimaryId = new Map<string, string>();
    for (const paper of papers.data ?? []) {
      for (const flow of paper.flow_layer ?? []) {
        const primaryId = String(flow.student_id ?? flow.ocr_student_id ?? "").trim();
        if (!primaryId) continue;
        const assignments = assignmentsByPrimaryId.get(primaryId) ?? [];
        assignments.push({
          examPdfId: paper.id,
          displayName: paper.displayName,
          pages: (flow.pages ?? []).map(Number)
        });
        assignmentsByPrimaryId.set(primaryId, assignments);
        if (flow.student_uuid) studentIdByPrimaryId.set(primaryId, flow.student_uuid);
      }
    }
    return submissions.items.map((submission) => {
      const studentId = studentIdByPrimaryId.get(submission.moodleUserId) ?? null;
      const answers = studentId ? data.answers.filter((answer) => answer.studentId === studentId) : [];
      const gradedQuestionCount = answers.filter(answerHasScore).length;
      const completed = gradableQuestions.length > 0 && gradedQuestionCount >= gradableQuestions.length;
      // Keep the local lock as soon as the job is queued. The progress request
      // can briefly still report the previous state, and that window must not
      // allow a second evaluation job to be submitted.
      const activelyGrading = gradingScope === "all" || gradingScope === submission.id;
      return {
        ...submission,
        studentId,
        answers,
        assignments: assignmentsByPrimaryId.get(submission.moodleUserId) ?? [],
        status: completed ? "completed" : activelyGrading ? "evaluating" : submission.status,
        gradedQuestionCount,
        totalScore: answers.reduce((sum, answer) => sum + Number(answer.point ?? answer.pointFromAi ?? 0), 0),
        maxScore: gradableQuestions.reduce((sum, question) => sum + Number(question.point ?? 0), 0)
      };
    });
  }, [data.answers, gradableQuestions, gradingScope, isRunning, papers.data, submissions.items]);
  const filteredRows = useMemo(() => {
    const normalized = search.trim().toLocaleLowerCase();
    if (!normalized) return rows;
    return rows.filter(
      (row) =>
        row.studentName.toLocaleLowerCase().includes(normalized) ||
        row.moodleUserId.toLocaleLowerCase().includes(normalized)
    );
  }, [rows, search]);
  const selectedRow = rows.find((row) => row.id === selectedSubmissionId) ?? rows[0] ?? null;
  useEffect(() => {
    if (rows.length > 0 && !rows.some((row) => row.id === selectedSubmissionId)) {
      setSelectedSubmissionId(rows[0]!.id);
    }
  }, [rows, selectedSubmissionId]);
  useEffect(() => {
    setPageZoom(1);
    setIsPanningPages(false);
    pagePan.current = null;
  }, [selectedRow?.id]);
  useEffect(() => {
    const next: Record<string, AnswerDraft> = {};
    for (const answer of selectedRow?.answers ?? []) {
      const score = answer.point ?? answer.pointFromAi;
      next[answer.id] = {
        score: score == null ? "" : String(score),
        feedback: answer.feedback ?? answer.feedbackFromAi ?? ""
      };
    }
    setDrafts(next);
  }, [data.answers, selectedRow?.id]);

  const startAll = useMutation({
    mutationFn: startEvaluation,
    onMutate: () => setGradingScope("all"),
    onSuccess: async () => {
      setNotice(null);
      await Promise.all([
        client.invalidateQueries({ queryKey: ["evaluation-progress", wizard.examId] }),
        client.invalidateQueries({ queryKey: ["answers", wizard.examId] })
      ]);
    },
    onError: (error) => {
      setGradingScope(null);
      setNotice({
        kind: "error",
        text: mutationErrorMessage(error, labels.evaluationStartFailed)
      });
    }
  });
  const reevaluate = useMutation({
    mutationFn: async () => {
      if (!selectedRow?.studentId) throw new Error(labels.studentPagesUnavailable);
      const response = await services.evaluateStudentAnswersService.run(wizard.examId, selectedRow.studentId, true);
      if (response.error) throw new Error(response.error.errors?.[0] ?? labels.evaluationStartFailed);
      if (!response.data?.count) throw new Error(labels.studentPagesUnavailable);
      return response.data;
    },
    onMutate: () => selectedRow && setGradingScope(selectedRow.id),
    onSuccess: async () => {
      setNotice(null);
      await client.invalidateQueries({ queryKey: ["evaluation-progress", wizard.examId] });
    },
    onError: (error) => {
      setGradingScope(null);
      setNotice({ kind: "error", text: mutationErrorMessage(error, labels.evaluationStartFailed) });
    }
  });
  const saveAnswers = useMutation({
    mutationFn: async () => {
      if (!selectedRow) return 0;
      let updated = 0;
      for (const answer of selectedRow.answers) {
        const draft = drafts[answer.id];
        if (!draft) continue;
        const question = gradableQuestions.find((item) => item.id === answer.questionId);
        const score = draft.score.trim() === "" ? null : Number(draft.score);
        if (score != null && (!Number.isFinite(score) || score < 0 || (question && score > Number(question.point)))) {
          throw new Error(labels.invalidScore);
        }
        const feedback = draft.feedback.trim() || null;
        const currentScore = answer.point ?? answer.pointFromAi ?? null;
        const currentFeedback = answer.feedback ?? answer.feedbackFromAi ?? null;
        if (score === currentScore && feedback === currentFeedback) continue;
        const response = await services.answerService.Update(answer.id, { point: score, feedback });
        if (response.error) throw new Error(response.error.errors?.[0] ?? labels.answerSaveFailed);
        updated++;
      }
      return updated;
    },
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ["answers", wizard.examId] });
      try {
        await refreshGrades();
      } catch {
        /* Quessor data was saved. */
      }
      setNotice({ kind: "success", text: labels.answersSaved });
    },
    onError: (error) => setNotice({ kind: "error", text: mutationErrorMessage(error, labels.answerSaveFailed) })
  });
  useEffect(() => {
    if (progress.data?.state === "completed" || progress.data?.state === "completed_with_errors") {
      void client.invalidateQueries({ queryKey: ["answers", wizard.examId] });
      setGradingScope(null);
      const refreshKey = `${progress.data.state}:${progress.data.runId ?? "current"}`;
      if (lastGradeRefresh.current !== refreshKey) {
        lastGradeRefresh.current = refreshKey;
        void refreshGrades().catch(() => {
          setNotice({ kind: "error", text: labels.gradeSyncFailed });
        });
      }
    }
  }, [client, labels.gradeSyncFailed, progress.data?.runId, progress.data?.state, refreshGrades, wizard.examId]);

  const pageImages = useQuery<StudentPageImage[]>({
    queryKey: ["moodle-student-page-images", wizard.examId, selectedRow?.id, selectedRow?.assignments],
    queryFn: async () => {
      if (!selectedRow) return [];
      const groups = await Promise.all(
        selectedRow.assignments.map(async (assignment) => {
          if (assignment.pages.length === 0) return [];
          const response = await services.extractStudentPagesService.getExamPdfPageImageUrls(
            wizard.examId,
            assignment.examPdfId,
            assignment.pages
          );
          return (response.data ?? []).map((image) => ({
            ...image,
            examPdfId: assignment.examPdfId,
            displayName: assignment.displayName
          }));
        })
      );
      return groups.flat();
    },
    enabled: Boolean(selectedRow?.assignments.length)
  });

  const hasRun = Boolean(progress.data?.runId) || Number(progress.data?.total ?? 0) > 0;
  const progressState = progress.data?.state;
  const showLiveProgress =
    progressState === "processing" || (progressState === "pending" && Boolean(progress.data?.runId));
  const progressCopy =
    progressState === "processing"
      ? labels.evaluationProgressProcessing
      : progressState === "completed_with_errors"
        ? labels.evaluationProgressCompletedErrors
        : progressState === "completed"
          ? labels.evaluationProgressCompleted
          : labels.evaluationProgressPending;
  const statusLabels: Record<string, string> = {
    ready: labels.statusReady,
    evaluating: labels.statusEvaluating,
    completed: labels.statusCompleted,
    failed: labels.statusFailed
  };
  const completedRows = rows.filter((row) => row.status === "completed");
  const remainingStudentCount = rows.length - completedRows.length;
  const averageScore =
    completedRows.length > 0 ? completedRows.reduce((sum, row) => sum + row.totalScore, 0) / completedRows.length : 0;
  const selectedIndex = selectedRow ? rows.findIndex((row) => row.id === selectedRow.id) : -1;
  const selectedEvaluationTime = Math.max(
    0,
    ...(selectedRow?.answers.map((answer) => Number(answer.evaluatedAt ?? 0)) ?? [])
  );
  const evaluationBusy = startAll.isPending || reevaluate.isPending || isRunning || gradingScope !== null;
  const hasPendingStudents = remainingStudentCount > 0;
  const publishableRows = rows.filter((row) => row.status === "completed" && row.grade !== null);
  const publishGradesMutation = useMutation({
    mutationFn: () =>
      publishGrades(
        publishableRows.map((row) => row.id),
        includeFeedback
      ),
    onSuccess: async (result: any) => {
      setShowPublishPreview(false);
      setNotice({ kind: "success", text: `${labels.gradesPublished} (${result.updated})` });
      await refreshGrades();
    },
    onError: (error) => setNotice({ kind: "error", text: mutationErrorMessage(error, labels.gradePublishFailed) })
  });
  const evaluationButtonLabel =
    completedRows.length > 0
      ? labels.evaluateRemaining.replace("{count}", String(remainingStudentCount))
      : labels.startEvaluation;
  const startPagePan = (event: React.PointerEvent<HTMLDivElement>) => {
    if (pageZoom <= 1 || event.button !== 0) return;
    pagePan.current = {
      pointerId: event.pointerId,
      x: event.clientX,
      y: event.clientY,
      scrollLeft: event.currentTarget.scrollLeft,
      scrollTop: event.currentTarget.scrollTop
    };
    event.currentTarget.setPointerCapture(event.pointerId);
    setIsPanningPages(true);
    event.preventDefault();
  };
  const movePagePan = (event: React.PointerEvent<HTMLDivElement>) => {
    const pan = pagePan.current;
    if (!pan || pan.pointerId !== event.pointerId) return;
    event.currentTarget.scrollLeft = pan.scrollLeft - (event.clientX - pan.x);
    event.currentTarget.scrollTop = pan.scrollTop - (event.clientY - pan.y);
    event.preventDefault();
  };
  const stopPagePan = (event: React.PointerEvent<HTMLDivElement>) => {
    if (pagePan.current?.pointerId !== event.pointerId) return;
    pagePan.current = null;
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }
    setIsPanningPages(false);
  };
  return (
    <div className="qev-native-stack">
      {notice && (
        <div className={`qev-native-message is-${notice.kind}`} role="status">
          {notice.text}
        </div>
      )}
      {hasRun && showLiveProgress && progress.data && (
        <section
          className={`qev-evaluation-progress-card is-${progressState ?? "pending"}`}
          role="status"
          aria-live="polite"
        >
          <header>
            <span className="qev-evaluation-progress-icon">
              {progressState === "processing" ? (
                <LoaderCircle size={17} className="qev-progress-spinner" aria-hidden="true" />
              ) : (
                <span aria-hidden="true">…</span>
              )}
            </span>
            <div>
              <strong>{labels.evaluationProgressTitle}</strong>
              <p>{progressCopy}</p>
            </div>
            <b>{progress.data.percentage}%</b>
          </header>
          <div className="qev-evaluation-progress-track">
            <span style={{ width: `${progress.data.percentage}%` }} />
          </div>
          <div className="qev-evaluation-progress-counts">
            <span>
              <b>{progress.data.completed}</b>
              {labels.evaluationProgressDone}
            </span>
            <span>
              <b>{progress.data.pending}</b>
              {labels.evaluationProgressWaiting}
            </span>
            {progress.data.failed > 0 && (
              <span className="is-failed">
                <b>{progress.data.failed}</b>
                {labels.evaluationProgressFailed}
              </span>
            )}
          </div>
        </section>
      )}
      <section className="qev-evaluation-summary">
        <div>
          <span>{labels.totalStudents}</span>
          <strong>{rows.length}</strong>
        </div>
        <div>
          <span>{labels.evaluatedStudents}</span>
          <strong>{completedRows.length}</strong>
        </div>
        <div>
          <span>{labels.averageScore}</span>
          <strong>{displayScore(averageScore)}</strong>
        </div>
        <div>
          <span>{labels.totalQuestions}</span>
          <strong>{gradableQuestions.length}</strong>
        </div>
      </section>
      <div className="qev-evaluation-toolbar">
        <div>
          <h3>{labels.combinedEvaluationTitle}</h3>
          <p>{labels.combinedEvaluationDescription}</p>
        </div>
      </div>
      {showPublishPreview && (
        <div className="qev-native-modal-backdrop" role="presentation">
          <section
            className="qev-native-question-modal qev-native-publish-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="qev-publish-title"
          >
            <header>
              <div>
                <span>{labels.preview}</span>
                <h3 id="qev-publish-title">{labels.reviewAndPublishGrades}</h3>
              </div>
              <button type="button" aria-label={labels.cancel} onClick={() => setShowPublishPreview(false)}>
                <X size={19} aria-hidden="true" />
              </button>
            </header>
            <p>{labels.publishGradesIntro}</p>
            <label className="qev-native-publish-option">
              <input
                type="checkbox"
                checked={includeFeedback}
                onChange={(event) => setIncludeFeedback(event.target.checked)}
              />
              <span>
                <strong>{labels.includeFeedback}</strong>
                <small>{labels.includeFeedbackHelp}</small>
              </span>
            </label>
            <div className="qev-native-publish-list">
              {publishableRows.map((row) => (
                <div key={row.id} className={`qev-native-publish-row${includeFeedback ? "" : " is-grade-only"}`}>
                  <strong>{row.studentName}</strong>
                  <b>
                    {displayScore(row.totalScore)} / {displayScore(row.maxScore)}
                  </b>
                  {includeFeedback && <span>{row.feedback || labels.noFeedback}</span>}
                </div>
              ))}
            </div>
            <footer>
              <button type="button" className="qev-button" onClick={() => setShowPublishPreview(false)}>
                {labels.cancel}
              </button>
              <button
                type="button"
                className="qev-button qev-button-primary"
                disabled={publishGradesMutation.isPending}
                onClick={() => publishGradesMutation.mutate()}
              >
                {publishGradesMutation.isPending ? labels.publishingGrades : labels.confirmPublishGrades}
              </button>
            </footer>
          </section>
        </div>
      )}
      {rows.length > 0 ? (
        <section className="qev-evaluation-workspace">
          <aside className="qev-evaluation-students">
            <div className="qev-evaluation-students-header">
              <strong>{labels.students}</strong>
              <span>{rows.length}</span>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={labels.searchStudents}
              />
            </div>
            <div className="qev-evaluation-student-list">
              {filteredRows.map((row) => (
                <button
                  type="button"
                  key={row.id}
                  className={row.id === selectedRow?.id ? "is-selected" : ""}
                  onClick={() => setSelectedSubmissionId(row.id)}
                >
                  <span className="qev-evaluation-avatar">
                    {row.studentName.trim().slice(0, 1).toLocaleUpperCase()}
                  </span>
                  <span className="qev-evaluation-student-copy">
                    <strong>{row.studentName}</strong>
                    <small>
                      Moodle #{row.moodleUserId} ·{" "}
                      {row.assignments.reduce((sum, assignment) => sum + assignment.pages.length, 0)} {labels.pages}
                    </small>
                  </span>
                  <span className="qev-evaluation-student-result">
                    <b>
                      {row.status === "completed" ? (
                        `${displayScore(row.totalScore)} / ${displayScore(row.maxScore)}`
                      ) : (
                        <Minus size={14} aria-label={labels.noResults} />
                      )}
                    </b>
                    <small className={`qev-native-status is-${row.status}`}>
                      {statusLabels[row.status] ?? row.status}
                    </small>
                  </span>
                </button>
              ))}
              {filteredRows.length === 0 && <div className="qev-empty">{labels.noSearchResults}</div>}
            </div>
          </aside>
          {selectedRow && (
            <article className="qev-evaluation-detail">
              <header className="qev-evaluation-detail-header">
                <div>
                  <span>{labels.studentEvaluation}</span>
                  <h3>{selectedRow.studentName}</h3>
                  <p>
                    Moodle #{selectedRow.moodleUserId} · {selectedRow.gradedQuestionCount}/{gradableQuestions.length}{" "}
                    {labels.evaluatedQuestions}
                  </p>
                </div>
                <div className="qev-evaluation-detail-actions">
                  <span className="qev-evaluation-student-navigation" aria-label={labels.students}>
                    <button
                      type="button"
                      aria-label={labels.previousStudent}
                      title={labels.previousStudent}
                      disabled={selectedIndex <= 0}
                      onClick={() => setSelectedSubmissionId(rows[selectedIndex - 1]!.id)}
                    >
                      <ChevronRight size={17} className="qev-rotate-180" aria-hidden="true" />
                    </button>
                    <span>
                      {selectedIndex + 1} / {rows.length}
                    </span>
                    <button
                      type="button"
                      aria-label={labels.nextStudent}
                      title={labels.nextStudent}
                      disabled={selectedIndex < 0 || selectedIndex >= rows.length - 1}
                      onClick={() => setSelectedSubmissionId(rows[selectedIndex + 1]!.id)}
                    >
                      <ChevronRight size={17} aria-hidden="true" />
                    </button>
                  </span>
                  <button
                    type="button"
                    className="qev-button qev-button-ai"
                    disabled={evaluationBusy || !selectedRow.studentId || selectedRow.assignments.length === 0}
                    onClick={() => reevaluate.mutate()}
                  >
                    {reevaluate.isPending
                      ? labels.evaluationRunning
                      : selectedRow.answers.length > 0
                        ? labels.reevaluateStudent
                        : labels.evaluateStudent}
                  </button>
                </div>
              </header>
              <div className="qev-evaluation-detail-grid">
                <section className="qev-evaluation-pages">
                  <header>
                    <div>
                      <span className="qev-evaluation-pages-title">
                        <strong>{labels.studentPages}</strong>
                        <small>
                          {pageImages.data?.length ?? 0} {labels.pages}
                          {pageZoom > 1 ? ` · ${labels.dragToPan}` : ""}
                        </small>
                      </span>
                      <span className="qev-page-zoom-controls">
                        <button
                          type="button"
                          aria-label={labels.zoomOut}
                          title={labels.zoomOut}
                          disabled={pageZoom <= 0.5}
                          onClick={() => setPageZoom((current) => Math.max(0.5, current - 0.25))}
                        >
                          −
                        </button>
                        <output aria-live="polite">{Math.round(pageZoom * 100)}%</output>
                        <button
                          type="button"
                          aria-label={labels.zoomIn}
                          title={labels.zoomIn}
                          disabled={pageZoom >= 2.5}
                          onClick={() => setPageZoom((current) => Math.min(2.5, current + 0.25))}
                        >
                          +
                        </button>
                        <button
                          type="button"
                          className="qev-page-zoom-reset"
                          disabled={pageZoom === 1}
                          onClick={() => setPageZoom(1)}
                        >
                          {labels.resetZoom}
                        </button>
                      </span>
                    </div>
                  </header>
                  <div
                    className={`${pageZoom > 1 ? "is-pannable" : ""} ${isPanningPages ? "is-panning" : ""}`}
                    onPointerDown={startPagePan}
                    onPointerMove={movePagePan}
                    onPointerUp={stopPagePan}
                    onPointerCancel={stopPagePan}
                    onLostPointerCapture={() => {
                      pagePan.current = null;
                      setIsPanningPages(false);
                    }}
                  >
                    {pageImages.isLoading && <NativeState kind="loading" message={labels.loadingStudentPages} />}
                    {!pageImages.isLoading &&
                      (pageImages.data ?? []).map((image, index) => (
                        <figure
                          key={`${image.examPdfId}:${image.pageNumber}`}
                          style={{ width: `${pageZoom * 100}%`, justifySelf: pageZoom < 1 ? "center" : "start" }}
                        >
                          <img
                            src={image.imageUrl}
                            alt={`${selectedRow.studentName} — ${labels.page} ${index + 1}`}
                            loading="lazy"
                            draggable={false}
                          />
                          <figcaption>
                            {image.displayName} · {labels.page} {image.pageNumber}
                          </figcaption>
                        </figure>
                      ))}
                    {!pageImages.isLoading && (pageImages.data?.length ?? 0) === 0 && (
                      <div className="qev-empty">{labels.noStudentPages}</div>
                    )}
                  </div>
                </section>
                <section className="qev-evaluation-answers">
                  <header>
                    <div>
                      <strong>{labels.scoreAndFeedback}</strong>
                      <small>
                        {displayScore(selectedRow.totalScore)} / {displayScore(selectedRow.maxScore)}
                        {selectedEvaluationTime > 0
                          ? ` · ${labels.evaluatedAt.replace("{time}", displayEvaluationTime(selectedEvaluationTime))}`
                          : ""}
                      </small>
                    </div>
                  </header>
                  <div className="qev-evaluation-answer-list">
                    {gradableQuestions.map((question) => {
                      const answer = selectedRow.answers.find((item) => item.questionId === question.id);
                      const draft = answer ? drafts[answer.id] : null;
                      return (
                        <section className="qev-evaluation-answer-card" key={question.id}>
                          <header>
                            <div>
                              <strong>{question.name || `Q${question.order}`}</strong>
                              <p>{question.text}</p>
                            </div>
                            <span>
                              {question.point} {labels.points}
                            </span>
                          </header>
                          {answer && draft ? (
                            <>
                              <label>
                                {labels.grade}
                                <input
                                  type="number"
                                  min="0"
                                  max={Number(question.point)}
                                  step="0.1"
                                  value={draft.score}
                                  onChange={(event) =>
                                    setDrafts((current) => ({
                                      ...current,
                                      [answer.id]: { ...draft, score: event.target.value }
                                    }))
                                  }
                                />
                              </label>
                              <label>
                                {labels.feedback}
                                <textarea
                                  rows={4}
                                  maxLength={2000}
                                  value={draft.feedback}
                                  onChange={(event) =>
                                    setDrafts((current) => ({
                                      ...current,
                                      [answer.id]: { ...draft, feedback: event.target.value }
                                    }))
                                  }
                                />
                              </label>
                            </>
                          ) : (
                            <div className="qev-empty">{labels.notEvaluatedYet}</div>
                          )}
                        </section>
                      );
                    })}
                  </div>
                  <footer>
                    <button
                      type="button"
                      className="qev-button qev-button-primary"
                      disabled={saveAnswers.isPending || selectedRow.answers.length === 0}
                      onClick={() => saveAnswers.mutate()}
                    >
                      {saveAnswers.isPending ? labels.saving : labels.saveScoreFeedback}
                    </button>
                  </footer>
                </section>
              </div>
            </article>
          )}
        </section>
      ) : (
        <div className="qev-empty">{labels.noSubmissions}</div>
      )}
      {(hasPendingStudents || publishableRows.length > 0 || evaluationBusy) && (
        <div className="qev-evaluation-publish-footer">
          {evaluationBusy && (
            <div className="qev-evaluation-lock-notice" role="status" aria-live="polite">
              <LoaderCircle size={16} className="qev-progress-spinner" aria-hidden="true" />
              <span>
                {progressState === "pending"
                  ? labels.evaluationProgressPending
                  : labels.evaluationProgressProcessing}
              </span>
            </div>
          )}
          {hasPendingStudents && (
            <button
              type="button"
              className="qev-button qev-button-primary"
              disabled={evaluationBusy || submissions.ready === 0}
              onClick={() => startAll.mutate()}
            >
              {evaluationBusy && gradingScope === "all" ? labels.evaluationRunning : evaluationButtonLabel}
            </button>
          )}
          {publishableRows.length > 0 && (
            <button
              type="button"
              className="qev-button qev-button-secondary"
              disabled={evaluationBusy || submissions.evaluating > 0 || publishGradesMutation.isPending}
              onClick={() => {
                setIncludeFeedback(false);
                setShowPublishPreview(true);
              }}
            >
              {labels.reviewAndPublishGrades}
            </button>
          )}
        </div>
      )}
    </div>
  );
}

window.QuessorEvaluation = { mount };
document.querySelectorAll<HTMLElement>('[data-qev-mount="1"]').forEach((element) => mount(`#${element.id}`));
