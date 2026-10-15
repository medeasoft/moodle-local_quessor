/**
 * Ortak metin (passage) – birden fazla sorunun paylaştığı metin.
 */
export interface ContextDto {
  id: string;
  examId: string;
  pageIndex: number;
  pageId?: string | null;
  order: number;
  text: string;
  deletionTime?: number | null;
  deleterUserId?: string | null;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}
