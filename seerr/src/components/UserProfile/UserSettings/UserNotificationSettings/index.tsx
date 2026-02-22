import LoadingSpinner from '@app/components/Common/LoadingSpinner';
import PageTitle from '@app/components/Common/PageTitle';
import type { SettingsRoute } from '@app/components/Common/SettingsTabs';
import SettingsTabs from '@app/components/Common/SettingsTabs';
import { useUser } from '@app/hooks/useUser';
import globalMessages from '@app/i18n/globalMessages';
import Error from '@app/pages/_error';
import defineMessages from '@app/utils/defineMessages';
import { ChatBubbleOvalLeftEllipsisIcon } from '@heroicons/react/24/solid';
import type { UserSettingsNotificationsResponse } from '@server/interfaces/api/userSettingsInterfaces';
import { useRouter } from 'next/router';
import { useIntl } from 'react-intl';
import useSWR from 'swr';

const messages = defineMessages(
  'components.UserProfile.UserSettings.UserNotificationSettings',
  {
    notifications: 'Notifications',
    notificationsettings: 'Notification Settings',
  }
);

type UserNotificationSettingsProps = {
  children: React.ReactNode;
};

const UserNotificationSettings = ({
  children,
}: UserNotificationSettingsProps) => {
  const intl = useIntl();
  const router = useRouter();
  const { user } = useUser({ id: Number(router.query.userId) });
  const { data, error } = useSWR<UserSettingsNotificationsResponse>(
    user ? `/api/v1/user/${user?.id}/settings/notifications` : null
  );

  const settingsRoutes: SettingsRoute[] = [
    {
      text: 'Rocket.Chat',
      content: (
        <span className="flex items-center">
          <ChatBubbleOvalLeftEllipsisIcon className="mr-2 h-4" />
          Rocket.Chat
        </span>
      ),
      route: '/settings/notifications/rocketchat',
      regex: /\/settings\/notifications\/rocketchat/,
      hidden: !data?.rocketChatUsername,
    },
  ];

  settingsRoutes.forEach((settingsRoute) => {
    settingsRoute.route = router.asPath.includes('/profile')
      ? `/profile${settingsRoute.route}`
      : `/users/${user?.id}${settingsRoute.route}`;
  });

  if (!data && !error) {
    return <LoadingSpinner />;
  }

  if (!data) {
    return <Error statusCode={500} />;
  }

  return (
    <>
      <PageTitle
        title={[
          intl.formatMessage(messages.notifications),
          intl.formatMessage(globalMessages.usersettings),
          user?.displayName,
        ]}
      />
      <div className="mb-6">
        <h3 className="heading">
          {intl.formatMessage(messages.notificationsettings)}
        </h3>
      </div>
      <SettingsTabs tabType="button" settingsRoutes={settingsRoutes} />
      <div className="section">{children}</div>
    </>
  );
};

export default UserNotificationSettings;
