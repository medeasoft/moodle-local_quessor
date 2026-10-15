import { UserDto } from "../account";
import { CourseDto } from "../course/course.model";

export interface InstituteDto {
  id: string;
  name: string;
  credit: number;
  hasActiveSubscription?: boolean;
  subscriptionPlanIdForParam?: string | null;
  subscriptionIsYearlyForParam?: boolean | null;
  subscriptionNextBillingAtForParam?: number | null;
  subscriptionLastPaymentAtForParam?: number | null;
  subscriptionAutoRenew?: boolean;
  paytrCardLast4?: string | null;
  contactEmail?: string | null;

  users?: UserDto[];
  courses?: CourseDto[];

  deletionTime?: number | null;
  deleterUserId?: string | null;
  creationTime?: number | null;
  creatorUserId?: string | null;
  lastModificationTime?: number | null;
  lastModifierUserId?: string | null;
}
