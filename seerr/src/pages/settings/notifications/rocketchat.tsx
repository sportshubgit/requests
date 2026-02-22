import NotificationsRocketChat from '@app/components/Settings/Notifications/NotificationsRocketChat';
import SettingsLayout from '@app/components/Settings/SettingsLayout';
import SettingsNotifications from '@app/components/Settings/SettingsNotifications';
import useRouteGuard from '@app/hooks/useRouteGuard';
import { Permission } from '@app/hooks/useUser';
import type { NextPage } from 'next';

const NotificationsPage: NextPage = () => {
  useRouteGuard(Permission.ADMIN);

  return (
    <SettingsLayout>
      <SettingsNotifications>
        <NotificationsRocketChat />
      </SettingsNotifications>
    </SettingsLayout>
  );
};

export default NotificationsPage;
