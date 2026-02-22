import Button from '@app/components/Common/Button';
import LoadingSpinner from '@app/components/Common/LoadingSpinner';
import NotificationTypeSelector from '@app/components/NotificationTypeSelector';
import { useUser } from '@app/hooks/useUser';
import globalMessages from '@app/i18n/globalMessages';
import defineMessages from '@app/utils/defineMessages';
import { ArrowDownOnSquareIcon } from '@heroicons/react/24/outline';
import type { UserSettingsNotificationsResponse } from '@server/interfaces/api/userSettingsInterfaces';
import axios from 'axios';
import { Form, Formik } from 'formik';
import { useRouter } from 'next/router';
import { useIntl } from 'react-intl';
import { useToasts } from 'react-toast-notifications';
import useSWR from 'swr';
import * as Yup from 'yup';

const messages = defineMessages(
  'components.UserProfile.UserSettings.UserNotificationSettings.UserNotificationsRocketChat',
  {
    settingsSaved: 'Rocket.Chat notification settings saved successfully!',
    settingsFailed: 'Rocket.Chat notification settings failed to save.',
    linkedAccount: 'Linked Rocket.Chat account',
    linkedAccountDescription:
      'Direct messages are sent to your SportsHub username on Rocket.Chat.',
    validationTypes: 'You must select at least one notification type',
  }
);

const UserNotificationsRocketChat = () => {
  const intl = useIntl();
  const { addToast } = useToasts();
  const router = useRouter();
  const { user } = useUser({ id: Number(router.query.userId) });
  const {
    data,
    error,
    mutate: revalidate,
  } = useSWR<UserSettingsNotificationsResponse>(
    user ? `/api/v1/user/${user?.id}/settings/notifications` : null
  );

  const Schema = Yup.object().shape({
    types: Yup.number().min(1, intl.formatMessage(messages.validationTypes)),
  });

  if (!data && !error) {
    return <LoadingSpinner />;
  }

  return (
    <Formik
      initialValues={{
        types: data?.notificationTypes.rocketchat ?? 0,
      }}
      validationSchema={Schema}
      enableReinitialize
      onSubmit={async (values) => {
        try {
          await axios.post(`/api/v1/user/${user?.id}/settings/notifications`, {
            pgpKey: data?.pgpKey,
            discordId: data?.discordId,
            pushbulletAccessToken: data?.pushbulletAccessToken,
            pushoverApplicationToken: data?.pushoverApplicationToken,
            pushoverUserKey: data?.pushoverUserKey,
            pushoverSound: data?.pushoverSound,
            telegramChatId: data?.telegramChatId,
            telegramMessageThreadId: data?.telegramMessageThreadId,
            telegramSendSilently: data?.telegramSendSilently,
            notificationTypes: {
              rocketchat: values.types,
            },
          });
          addToast(intl.formatMessage(messages.settingsSaved), {
            appearance: 'success',
            autoDismiss: true,
          });
        } catch (e) {
          addToast(intl.formatMessage(messages.settingsFailed), {
            appearance: 'error',
            autoDismiss: true,
          });
        } finally {
          revalidate();
        }
      }}
    >
      {({
        errors,
        touched,
        isSubmitting,
        isValid,
        values,
        setFieldValue,
        setFieldTouched,
      }) => {
        return (
          <Form className="section">
            <div className="form-row">
              <label className="text-label">
                {intl.formatMessage(messages.linkedAccount)}
                <span className="label-tip">
                  {intl.formatMessage(messages.linkedAccountDescription)}
                </span>
              </label>
              <div className="form-input-area">
                <div className="rounded-md border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200">
                  @{data?.rocketChatUsername}
                </div>
              </div>
            </div>
            <NotificationTypeSelector
              user={user}
              currentTypes={values.types}
              onUpdate={(newTypes) => {
                setFieldValue('types', newTypes);
                setFieldTouched('types');
              }}
              error={
                errors.types && touched.types
                  ? (errors.types as string)
                  : undefined
              }
            />
            <div className="actions">
              <div className="flex justify-end">
                <span className="ml-3 inline-flex rounded-md shadow-sm">
                  <Button
                    buttonType="primary"
                    type="submit"
                    disabled={isSubmitting || !isValid || !values.types}
                  >
                    <ArrowDownOnSquareIcon />
                    <span>
                      {isSubmitting
                        ? intl.formatMessage(globalMessages.saving)
                        : intl.formatMessage(globalMessages.save)}
                    </span>
                  </Button>
                </span>
              </div>
            </div>
          </Form>
        );
      }}
    </Formik>
  );
};

export default UserNotificationsRocketChat;
