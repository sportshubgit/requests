import UserSettings from '@app/components/UserProfile/UserSettings';
import UserNotificationSettings from '@app/components/UserProfile/UserSettings/UserNotificationSettings';
import UserNotificationsRocketChat from '@app/components/UserProfile/UserSettings/UserNotificationSettings/UserNotificationsRocketChat';
import type { NextPage } from 'next';

const NotificationsPage: NextPage = () => {
  return (
    <UserSettings>
      <UserNotificationSettings>
        <UserNotificationsRocketChat />
      </UserNotificationSettings>
    </UserSettings>
  );
};

export default NotificationsPage;
