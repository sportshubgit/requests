import Button from '@app/components/Common/Button';
import LoadingSpinner from '@app/components/Common/LoadingSpinner';
import SensitiveInput from '@app/components/Common/SensitiveInput';
import NotificationTypeSelector from '@app/components/NotificationTypeSelector';
import globalMessages from '@app/i18n/globalMessages';
import defineMessages from '@app/utils/defineMessages';
import { ArrowDownOnSquareIcon, BeakerIcon } from '@heroicons/react/24/outline';
import axios from 'axios';
import { Field, Form, Formik } from 'formik';
import { useState } from 'react';
import { useIntl } from 'react-intl';
import { useToasts } from 'react-toast-notifications';
import useSWR from 'swr';
import * as Yup from 'yup';

const messages = defineMessages(
  'components.Settings.Notifications.NotificationsRocketChat',
  {
    agentenabled: 'Enable Agent',
    embedPoster: 'Embed Poster',
    serverUrl: 'Server URL',
    serverUrlTip: 'Base URL for your Rocket.Chat instance',
    userId: 'Bot User ID',
    userIdTip: 'Rocket.Chat user id for the bot account',
    authToken: 'Bot Auth Token',
    authTokenTip: 'Personal access token for the bot account',
    systemChannel: 'System Channel (optional)',
    systemChannelTip: 'Use #channel for room posts or @username for direct tests',
    rocketchatSaved: 'Rocket.Chat notification settings saved successfully!',
    rocketchatFailed: 'Rocket.Chat notification settings failed to save.',
    toastTestSending: 'Sending Rocket.Chat test notification…',
    toastTestSuccess: 'Rocket.Chat test notification sent!',
    toastTestFailed: 'Rocket.Chat test notification failed to send.',
    validationServerUrl: 'You must provide a valid URL',
    validationUserId: 'You must provide a bot user ID',
    validationAuthToken: 'You must provide a bot auth token',
    validationTypes: 'You must select at least one notification type',
  }
);

const NotificationsRocketChat = () => {
  const intl = useIntl();
  const { addToast, removeToast } = useToasts();
  const [isTesting, setIsTesting] = useState(false);
  const {
    data,
    error,
    mutate: revalidate,
  } = useSWR('/api/v1/settings/notifications/rocketchat');

  const NotificationsRocketChatSchema = Yup.object().shape({
    serverUrl: Yup.string()
      .when('enabled', {
        is: true,
        then: Yup.string()
          .nullable()
          .required(intl.formatMessage(messages.validationServerUrl)),
        otherwise: Yup.string().nullable(),
      })
      .url(intl.formatMessage(messages.validationServerUrl)),
    userId: Yup.string().when('enabled', {
      is: true,
      then: Yup.string()
        .nullable()
        .required(intl.formatMessage(messages.validationUserId)),
      otherwise: Yup.string().nullable(),
    }),
    authToken: Yup.string().when('enabled', {
      is: true,
      then: Yup.string()
        .nullable()
        .required(intl.formatMessage(messages.validationAuthToken)),
      otherwise: Yup.string().nullable(),
    }),
  });

  if (!data && !error) {
    return <LoadingSpinner />;
  }

  return (
    <Formik
      initialValues={{
        enabled: data?.enabled,
        embedPoster: data?.embedPoster,
        types: data?.types,
        serverUrl: data?.options.serverUrl,
        userId: data?.options.userId,
        authToken: data?.options.authToken,
        systemChannel: data?.options.systemChannel,
      }}
      validationSchema={NotificationsRocketChatSchema}
      onSubmit={async (values) => {
        try {
          await axios.post('/api/v1/settings/notifications/rocketchat', {
            enabled: values.enabled,
            embedPoster: values.embedPoster,
            types: values.types,
            options: {
              serverUrl: values.serverUrl,
              userId: values.userId,
              authToken: values.authToken,
              systemChannel: values.systemChannel,
            },
          });
          addToast(intl.formatMessage(messages.rocketchatSaved), {
            appearance: 'success',
            autoDismiss: true,
          });
        } catch (e) {
          addToast(intl.formatMessage(messages.rocketchatFailed), {
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
        values,
        isValid,
        setFieldValue,
        setFieldTouched,
      }) => {
        const testSettings = async () => {
          setIsTesting(true);
          let toastId: string | undefined;

          try {
            addToast(
              intl.formatMessage(messages.toastTestSending),
              {
                autoDismiss: false,
                appearance: 'info',
              },
              (id) => {
                toastId = id;
              }
            );

            await axios.post('/api/v1/settings/notifications/rocketchat/test', {
              enabled: true,
              embedPoster: values.embedPoster,
              types: values.types,
              options: {
                serverUrl: values.serverUrl,
                userId: values.userId,
                authToken: values.authToken,
                systemChannel: values.systemChannel,
              },
            });

            if (toastId) {
              removeToast(toastId);
            }
            addToast(intl.formatMessage(messages.toastTestSuccess), {
              autoDismiss: true,
              appearance: 'success',
            });
          } catch (e) {
            if (toastId) {
              removeToast(toastId);
            }
            addToast(intl.formatMessage(messages.toastTestFailed), {
              autoDismiss: true,
              appearance: 'error',
            });
          } finally {
            setIsTesting(false);
          }
        };

        return (
          <Form className="section">
            <div className="form-row">
              <label htmlFor="enabled" className="checkbox-label">
                {intl.formatMessage(messages.agentenabled)}
                <span className="label-required">*</span>
              </label>
              <div className="form-input-area">
                <Field type="checkbox" id="enabled" name="enabled" />
              </div>
            </div>
            <div className="form-row">
              <label htmlFor="embedPoster" className="checkbox-label">
                {intl.formatMessage(messages.embedPoster)}
              </label>
              <div className="form-input-area">
                <Field type="checkbox" id="embedPoster" name="embedPoster" />
              </div>
            </div>
            <div className="form-row">
              <label htmlFor="serverUrl" className="text-label">
                {intl.formatMessage(messages.serverUrl)}
                <span className="label-required">*</span>
                <span className="label-tip">
                  {intl.formatMessage(messages.serverUrlTip)}
                </span>
              </label>
              <div className="form-input-area">
                <div className="form-input-field">
                  <Field id="serverUrl" name="serverUrl" type="text" />
                </div>
                {errors.serverUrl &&
                  touched.serverUrl &&
                  typeof errors.serverUrl === 'string' && (
                    <div className="error">{errors.serverUrl}</div>
                  )}
              </div>
            </div>
            <div className="form-row">
              <label htmlFor="userId" className="text-label">
                {intl.formatMessage(messages.userId)}
                <span className="label-required">*</span>
                <span className="label-tip">
                  {intl.formatMessage(messages.userIdTip)}
                </span>
              </label>
              <div className="form-input-area">
                <div className="form-input-field">
                  <Field id="userId" name="userId" type="text" />
                </div>
                {errors.userId &&
                  touched.userId &&
                  typeof errors.userId === 'string' && (
                    <div className="error">{errors.userId}</div>
                  )}
              </div>
            </div>
            <div className="form-row">
              <label htmlFor="authToken" className="text-label">
                {intl.formatMessage(messages.authToken)}
                <span className="label-required">*</span>
                <span className="label-tip">
                  {intl.formatMessage(messages.authTokenTip)}
                </span>
              </label>
              <div className="form-input-area">
                <div className="form-input-field">
                  <SensitiveInput as="field" id="authToken" name="authToken" />
                </div>
                {errors.authToken &&
                  touched.authToken &&
                  typeof errors.authToken === 'string' && (
                    <div className="error">{errors.authToken}</div>
                  )}
              </div>
            </div>
            <div className="form-row">
              <label htmlFor="systemChannel" className="text-label">
                {intl.formatMessage(messages.systemChannel)}
                <span className="label-tip">
                  {intl.formatMessage(messages.systemChannelTip)}
                </span>
              </label>
              <div className="form-input-area">
                <div className="form-input-field">
                  <Field id="systemChannel" name="systemChannel" type="text" />
                </div>
              </div>
            </div>
            <NotificationTypeSelector
              currentTypes={values.enabled ? values.types : 0}
              onUpdate={(newTypes) => {
                setFieldValue('types', newTypes);
                setFieldTouched('types');

                if (newTypes) {
                  setFieldValue('enabled', true);
                }
              }}
              error={
                values.enabled && !values.types && touched.types
                  ? intl.formatMessage(messages.validationTypes)
                  : undefined
              }
            />
            <div className="actions">
              <div className="flex justify-end">
                <span className="ml-3 inline-flex rounded-md shadow-sm">
                  <Button
                    buttonType="warning"
                    disabled={isSubmitting || !isValid || isTesting}
                    onClick={(e) => {
                      e.preventDefault();
                      testSettings();
                    }}
                  >
                    <BeakerIcon />
                    <span>
                      {isTesting
                        ? intl.formatMessage(globalMessages.testing)
                        : intl.formatMessage(globalMessages.test)}
                    </span>
                  </Button>
                </span>
                <span className="ml-3 inline-flex rounded-md shadow-sm">
                  <Button
                    buttonType="primary"
                    type="submit"
                    disabled={
                      isSubmitting ||
                      !isValid ||
                      isTesting ||
                      (values.enabled && !values.types)
                    }
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

export default NotificationsRocketChat;
