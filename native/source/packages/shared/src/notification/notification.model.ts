export enum NotificationChannel {
  IN_APP = "IN_APP",
  PUSH = "PUSH",
  EMAIL = "EMAIL",
  SMS = "SMS"
}

export interface NotificationDto {
  id: string;
  title: string;
  content: string;
  channels: NotificationChannel[];
  isForAllUser: boolean;
  userIds?: string[];
  createdAt: number;
}

export interface UserNotificationDto {
  id: string;
  userId: string;
  notificationId: string;
  notification: NotificationDto;
  isRead: boolean;
  readAt: number | null;
  createdAt: number;
}
