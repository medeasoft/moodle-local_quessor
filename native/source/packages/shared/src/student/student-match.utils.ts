import type { StudentDto } from "./student.model";

/**
 * Normalizes text for robust comparison:
 * - Trims whitespace
 * - Converts to lowercase using Turkish locale
 * - Removes zero-width characters
 */
export function normalizeStudentText(text?: string | null): string {
  if (!text) return "";
  return String(text)
    .trim()
    .toLocaleLowerCase("tr-TR")
    .replace(/[\u200B-\u200D\uFEFF]/g, "");
}

/**
 * Strips file extension from filename.
 */
export function getBaseFileName(fileName: string): string {
  return fileName.replace(/\.[^/.]+$/, "").trim();
}

/**
 * Finds the best matching student from the available students list given a PDF filename.
 * Priority order:
 * 1. Exact match between filename and student primaryId
 * 2. Token match where one token in filename equals student primaryId
 * 3. Exact digit sequence match (if primaryId is numeric and >= 3 digits)
 * 4. Substring match for primaryId (if length >= 3, matching longest primaryId first)
 * 5. Full name exact match (e.g., "Ahmet Yılmaz" or "Yılmaz Ahmet")
 * 6. Full name token match (both first and last name tokens present in filename)
 */
export function findMatchingStudent(
  fileName: string,
  students: StudentDto[],
  usedStudentIds: Set<string> = new Set()
): StudentDto | undefined {
  const availableStudents = students.filter((s) => !usedStudentIds.has(s.id));
  if (availableStudents.length === 0) return undefined;

  const baseName = getBaseFileName(fileName);
  if (!baseName) return undefined;

  const baseNorm = normalizeStudentText(baseName);
  // Split by any non-alphanumeric character (supports Turkish characters)
  const tokens: string[] = baseNorm.split(/[^a-z0-9çğıöşü]+/i).filter(Boolean);
  const numbers: string[] = baseNorm.match(/\d+/g) ?? [];

  // 1. Exact primaryId match
  for (const student of availableStudents) {
    const pid = normalizeStudentText(student.primaryId);
    if (pid && pid === baseNorm) {
      return student;
    }
  }

  // 2. Token match on primaryId
  for (const student of availableStudents) {
    const pid = normalizeStudentText(student.primaryId);
    if (pid && tokens.includes(pid)) {
      return student;
    }
  }

  // 3. Numeric sequence match (for numeric primaryIds >= 3 digits)
  for (const student of availableStudents) {
    const pid = normalizeStudentText(student.primaryId);
    if (pid && /^\d{3,}$/.test(pid) && numbers.includes(pid)) {
      return student;
    }
  }

  // 4. Substring match on primaryId with length >= 3 (longest primaryId first to prevent prefix clashes)
  const sortedByPidLen = [...availableStudents]
    .filter((s) => normalizeStudentText(s.primaryId).length >= 3)
    .sort(
      (a, b) =>
        normalizeStudentText(b.primaryId).length -
        normalizeStudentText(a.primaryId).length
    );

  for (const student of sortedByPidLen) {
    const pid = normalizeStudentText(student.primaryId);
    const escapedPid = pid.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const boundedRegex = new RegExp(`(^|[^a-z0-9çğıöşü])${escapedPid}([^a-z0-9çğıöşü]|$)`, "i");
    if (boundedRegex.test(baseNorm)) {
      return student;
    }
  }

  // 5. Full name match (e.g. "Ahmet Yılmaz.pdf" or "Yılmaz Ahmet.pdf")
  const baseNoSpace = baseNorm.replace(/[^a-z0-9çğıöşü]/gi, "");
  for (const student of availableStudents) {
    const first = normalizeStudentText(student.firstName);
    const last = normalizeStudentText(student.lastName);
    if (!first && !last) continue;

    const fullName1 = [first, last].filter(Boolean).join(" ");
    const fullName2 = [last, first].filter(Boolean).join(" ");
    const fullNameNoSpace = (first + last).replace(/[^a-z0-9çğıöşü]/gi, "");

    if (
      (fullName1.length >= 4 && (baseNorm === fullName1 || baseNorm === fullName2)) ||
      (fullNameNoSpace.length >= 4 && baseNoSpace === fullNameNoSpace)
    ) {
      return student;
    }
  }

  // 6. Name tokens match (both first and last name tokens exist in the filename)
  for (const student of availableStudents) {
    const first = normalizeStudentText(student.firstName);
    const last = normalizeStudentText(student.lastName);
    if (first && last && first.length >= 2 && last.length >= 2) {
      if (tokens.includes(first) && tokens.includes(last)) {
        return student;
      }
    }
  }

  return undefined;
}
