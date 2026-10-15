import { z } from "zod";
import { NotificationChannel } from "./notification.model";

// Create a Zod enum from NotificationChannel enum
const NotificationChannelEnum = z.nativeEnum(NotificationChannel);

export const createNotificationSchema = z
  .object({
    title: z.string().min(1, "title_required"),
    content: z.string().min(1, "content_required"),
    channels: z.array(NotificationChannelEnum).min(1, "channel_required"),
    isForAllUser: z.boolean(),
    userIds: z.array(z.string().uuid("user_uuid")).optional()
  })
  .refine(
    (data) => {
      if (!data.isForAllUser && (!data.userIds || data.userIds.length === 0)) {
        return false;
      }
      return true;
    },
    {
      message: "user_or_all_required",
      path: ["userIds"]
    }
  );

// Type exports
export type CreateNotificationDto = z.infer<typeof createNotificationSchema>;
