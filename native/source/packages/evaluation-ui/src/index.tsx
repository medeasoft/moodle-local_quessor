import { useState, type ReactNode } from "react";
import { BookOpen, ChevronDown, ChevronUp, Users } from "lucide-react";

export * from "./core";
export * from "./services";
export * from "./wizard";

export interface EvaluationPreviewQuestion {
  id: string;
  name: string;
  text: string;
  points?: number;
  correctAnswer?: string | null;
  gradingInstructions?: string | null;
}

export interface EvaluationPreviewStudent {
  id: string;
  name: string;
  identifier?: string | null;
  pageCount?: number;
}

export interface EvaluationPreviewLabels {
  questionsTitle: (count: number) => string;
  totalPoints: (points: number) => string;
  points: (points: number) => string;
  correctAnswer: string;
  evaluationCriteria: string;
  studentsTitle: (count: number) => string;
  participating: (count: number, total: number) => string;
  pages: (count: number) => string;
  details: string;
}

export function PreviewHeader({ title, description, meta }: { title: string; description: string; meta?: ReactNode }) {
  return <div className="qev-preview-header"><div><h2>{title}</h2><p>{description}</p></div>{meta}</div>;
}

export function QuestionsSummary({ questions, labels, initiallyExpanded = false }: { questions: EvaluationPreviewQuestion[]; labels: EvaluationPreviewLabels; initiallyExpanded?: boolean }) {
  const [expanded, setExpanded] = useState(initiallyExpanded);
  const total = questions.reduce((sum, question) => sum + Number(question.points ?? 0), 0);
  if (!questions.length) return null;
  return <section className="qev-preview-card qev-preview-collapsible"><button className="qev-preview-trigger" type="button" onClick={() => setExpanded(!expanded)} aria-expanded={expanded}><span className="qev-preview-trigger-main"><span className="qev-preview-icon"><BookOpen size={20} /></span><span><strong>{labels.questionsTitle(questions.length)}</strong><small>{labels.totalPoints(total)}</small></span></span><span className="qev-preview-chevron">{expanded ? <ChevronUp size={20} /> : <ChevronDown size={20} />}</span></button>{expanded && <div className="qev-preview-content">{questions.map((question) => <article className="qev-preview-question" key={question.id}><div className="qev-preview-question-title"><strong>{question.name}</strong>{question.points != null && <span>{labels.points(question.points)}</span>}</div><p>{question.text}</p>{question.correctAnswer && <div className="qev-preview-note"><b>{labels.correctAnswer}</b><span>{question.correctAnswer}</span></div>}{question.gradingInstructions && <div className="qev-preview-note qev-preview-note-primary"><b>{labels.evaluationCriteria}</b><span>{question.gradingInstructions}</span></div>}</article>)}</div>}</section>;
}

export function StudentsSummary({ students, labels, onOpenDetail }: { students: EvaluationPreviewStudent[]; labels: EvaluationPreviewLabels; onOpenDetail?: (studentId: string) => void }) {
  const [expanded, setExpanded] = useState(false);
  if (!students.length) return null;
  const participating = students.filter((student) => (student.pageCount ?? 0) > 0).length;
  return <section className="qev-preview-card qev-preview-collapsible"><button className="qev-preview-trigger" type="button" onClick={() => setExpanded(!expanded)} aria-expanded={expanded}><span className="qev-preview-trigger-main"><span className="qev-preview-icon"><Users size={20} /></span><span><strong>{labels.studentsTitle(students.length)}</strong><small>{labels.participating(participating, students.length)}</small></span></span><span className="qev-preview-chevron">{expanded ? <ChevronUp size={20} /> : <ChevronDown size={20} />}</span></button>{expanded && <div className="qev-preview-students">{students.map((student) => <article className={'qev-preview-student' + ((student.pageCount ?? 0) > 0 ? ' is-ready' : '')} key={student.id}><strong>{student.identifier || student.name}</strong><small>{student.identifier ? student.name : ''}</small>{(student.pageCount ?? 0) > 0 && <span>{labels.pages(student.pageCount ?? 0)}</span>}{onOpenDetail && <button type="button" onClick={() => onOpenDetail(student.id)}>{labels.details}</button>}</article>)}</div>}</section>;
}
