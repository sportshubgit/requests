import Button from '@app/components/Common/Button';
import LabeledCheckbox from '@app/components/Common/LabeledCheckbox';
import LoadingSpinner from '@app/components/Common/LoadingSpinner';
import PageTitle from '@app/components/Common/PageTitle';
import SensitiveInput from '@app/components/Common/SensitiveInput';
import PermissionEdit from '@app/components/PermissionEdit';
import QuotaSelector from '@app/components/QuotaSelector';
import useSettings from '@app/hooks/useSettings';
import globalMessages from '@app/i18n/globalMessages';
import defineMessages from '@app/utils/defineMessages';
import {
  ArrowDownOnSquareIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@heroicons/react/24/outline';
import { MediaServerType } from '@server/constants/server';
import type { MainSettings } from '@server/lib/settings';
import axios from 'axios';
import { Field, Form, Formik } from 'formik';
import { useState } from 'react';
import { useIntl } from 'react-intl';
import { useToasts } from 'react-toast-notifications';
import useSWR, { mutate } from 'swr';
import * as yup from 'yup';

const messages = defineMessages('components.Settings.SettingsUsers', {
  users: 'Users',
  userSettings: 'User Settings',
  userSettingsDescription: 'Configure global and default user settings.',
  toastSettingsSuccess: 'User settings saved successfully!',
  toastSettingsFailure: 'Something went wrong while saving settings.',
  loginMethods: 'Login Methods',
  loginMethodsTip: 'Configure login methods for users.',
  localLogin: 'Enable Local Sign-In',
  localLoginTip:
    'Allow users to sign in using their email address and password',
  xuiLogin: 'Enable XUI Sign-In',
  xuiLoginTip:
    'Allow users to sign in using Xtream/XUI player_api username and password',
  xuiPlayerApiUrl: 'XUI player_api URL',
  xuiPlayerApiUrlTip:
    'Your custom endpoint, e.g. https://your-domain/player_api.php',
  xuiTimeoutSeconds: 'XUI Timeout (seconds)',
  xuiTimeoutSecondsTip: 'Request timeout for XUI sign-in checks',
  mediaServerLogin: 'Enable {mediaServerName} Sign-In',
  mediaServerLoginTip:
    'Allow users to sign in using their {mediaServerName} account',
  atLeastOneAuth: 'At least one authentication method must be selected.',
  newPlexLogin: 'Enable New {mediaServerName} Sign-In',
  newPlexLoginTip:
    'Allow {mediaServerName} users to sign in without first being imported',
  movieRequestLimitLabel: 'Global Movie Request Limit',
  tvRequestLimitLabel: 'Global Series Request Limit',
  defaultPermissions: 'Default Permissions',
  defaultPermissionsTip: 'Initial permissions assigned to new users',
  xuiTestSection: 'XUI Test Login',
  xuiTestDescription:
    'Validate endpoint, credentials, and account eligibility (active + not expired).',
  xuiTestUsername: 'Test Username',
  xuiTestPassword: 'Test Password',
  xuiTestButton: 'Test XUI Login',
  xuiTestOk: 'XUI login valid and allowed',
  xuiTestFailed: 'XUI login failed',
  xuiTestError: 'XUI test request failed',
  trackedSyncSettings: 'Tracked Sync',
  trackedSyncSettingsDescription:
    'Push watchlist/request events to your custom API endpoint with a category.',
  trackedSyncEnabled: 'Enable tracked sync',
  trackedSyncEnabledTip: 'Send watchlist tick-off and request events to your API endpoint',
  trackedSyncUrl: 'Tracked sync API URL',
  trackedSyncApiKey: 'Tracked sync API key (optional)',
  trackedSyncCategory: 'Tracked category',
  trackedSyncPayloadTip:
    'Payload includes event, requested user, TMDB media type, external IDs (IMDB/TVDB when available), and status metadata for your ID-mapping service.',
});

type XuiTestResponse = {
  ok: boolean;
  reason: string;
  parsed: {
    username: string;
    status: string;
    isAuthenticated: boolean;
    isExpired: boolean;
    expDateEpoch: number | null;
  };
};

const SettingsUsers = () => {
  const { addToast } = useToasts();
  const intl = useIntl();
  const [testUsername, setTestUsername] = useState('');
  const [testPassword, setTestPassword] = useState('');
  const [isTestingXui, setIsTestingXui] = useState(false);
  const [xuiTestResult, setXuiTestResult] = useState<XuiTestResponse | null>(
    null
  );
  const [xuiTestError, setXuiTestError] = useState<string | null>(null);
  const {
    data,
    error,
    mutate: revalidate,
  } = useSWR<MainSettings>('/api/v1/settings/main');
  const settings = useSettings();

  const schema = yup
    .object()
    .shape({
      localLogin: yup.boolean(),
      mediaServerLogin: yup.boolean(),
    })
    .test({
      name: 'atLeastOneAuth',
      test: function (values) {
        const isValid = ['localLogin', 'mediaServerLogin', 'xuiLogin'].some(
          (field) => !!values[field]
        );

        if (isValid) return true;
        return this.createError({
          path: 'authMethods',
          message: intl.formatMessage(messages.atLeastOneAuth),
        });
      },
    });

  if (!data && !error) {
    return <LoadingSpinner />;
  }

  const testXui = async () => {
    setIsTestingXui(true);
    setXuiTestError(null);
    setXuiTestResult(null);

    try {
      const response = await axios.post<XuiTestResponse>(
        '/api/v1/settings/xui/test',
        {
          username: testUsername,
          password: testPassword,
        }
      );
      setXuiTestResult(response.data);
    } catch (e: unknown) {
      const axiosError = e as {
        response?: { data?: { message?: string } };
      };
      setXuiTestError(
        axiosError.response?.data?.message ||
          intl.formatMessage(messages.xuiTestError)
      );
    } finally {
      setIsTestingXui(false);
    }
  };

  const mediaServerFormatValues = {
    mediaServerName:
      settings.currentSettings.mediaServerType === MediaServerType.JELLYFIN
        ? 'Jellyfin'
        : settings.currentSettings.mediaServerType === MediaServerType.EMBY
          ? 'Emby'
          : settings.currentSettings.mediaServerType === MediaServerType.PLEX
            ? 'Plex'
            : undefined,
  };

  return (
    <>
      <PageTitle
        title={[
          intl.formatMessage(messages.users),
          intl.formatMessage(globalMessages.settings),
        ]}
      />
      <div className="mb-6">
        <h3 className="heading">{intl.formatMessage(messages.userSettings)}</h3>
        <p className="description">
          {intl.formatMessage(messages.userSettingsDescription)}
        </p>
      </div>
      <div className="section">
        <Formik
          initialValues={{
            localLogin: data?.localLogin,
            xuiLogin: data?.xuiLogin ?? false,
            xuiPlayerApiUrl: data?.xuiPlayerApiUrl ?? '',
            xuiTimeoutSeconds: data?.xuiTimeoutSeconds ?? 8,
            trackedSyncEnabled: data?.trackedSyncEnabled ?? false,
            trackedSyncUrl: data?.trackedSyncUrl ?? '',
            trackedSyncApiKey: data?.trackedSyncApiKey ?? '',
            trackedSyncCategory: data?.trackedSyncCategory ?? 'tracked',
            mediaServerLogin: data?.mediaServerLogin,
            newPlexLogin: data?.newPlexLogin,
            movieQuotaLimit: data?.defaultQuotas.movie.quotaLimit ?? 0,
            movieQuotaDays: data?.defaultQuotas.movie.quotaDays ?? 7,
            tvQuotaLimit: data?.defaultQuotas.tv.quotaLimit ?? 0,
            tvQuotaDays: data?.defaultQuotas.tv.quotaDays ?? 7,
            defaultPermissions: data?.defaultPermissions ?? 0,
          }}
          validationSchema={schema}
          enableReinitialize
          onSubmit={async (values) => {
            try {
              await axios.post('/api/v1/settings/main', {
                localLogin: values.localLogin,
                xuiLogin: values.xuiLogin,
                xuiPlayerApiUrl: values.xuiPlayerApiUrl,
                xuiTimeoutSeconds: values.xuiTimeoutSeconds,
                trackedSyncEnabled: values.trackedSyncEnabled,
                trackedSyncUrl: values.trackedSyncUrl,
                trackedSyncApiKey: values.trackedSyncApiKey,
                trackedSyncCategory: values.trackedSyncCategory,
                mediaServerLogin: values.mediaServerLogin,
                newPlexLogin: values.newPlexLogin,
                defaultQuotas: {
                  movie: {
                    quotaLimit: values.movieQuotaLimit,
                    quotaDays: values.movieQuotaDays,
                  },
                  tv: {
                    quotaLimit: values.tvQuotaLimit,
                    quotaDays: values.tvQuotaDays,
                  },
                },
                defaultPermissions: values.defaultPermissions,
              });
              mutate('/api/v1/settings/public');

              addToast(intl.formatMessage(messages.toastSettingsSuccess), {
                autoDismiss: true,
                appearance: 'success',
              });
            } catch (e) {
              addToast(intl.formatMessage(messages.toastSettingsFailure), {
                autoDismiss: true,
                appearance: 'error',
              });
            } finally {
              revalidate();
            }
          }}
        >
          {({ isSubmitting, isValid, values, errors, setFieldValue }) => {
            return (
              <Form className="section">
                <div
                  role="group"
                  aria-labelledby="group-label"
                  className="form-group"
                >
                  <div className="form-row">
                    <span id="group-label" className="group-label">
                      {intl.formatMessage(messages.loginMethods)}
                      <span className="label-tip">
                        {intl.formatMessage(messages.loginMethodsTip)}
                      </span>
                      {'authMethods' in errors && (
                        <span className="error">
                          {errors.authMethods as string}
                        </span>
                      )}
                    </span>

                    <div className="form-input-area max-w-lg">
                      <LabeledCheckbox
                        id="localLogin"
                        label={intl.formatMessage(messages.localLogin)}
                        description={intl.formatMessage(
                          messages.localLoginTip,
                          mediaServerFormatValues
                        )}
                        onChange={() =>
                          setFieldValue('localLogin', !values.localLogin)
                        }
                      />
                      <LabeledCheckbox
                        id="mediaServerLogin"
                        className="mt-4"
                        label={intl.formatMessage(
                          messages.mediaServerLogin,
                          mediaServerFormatValues
                        )}
                        description={intl.formatMessage(
                          messages.mediaServerLoginTip,
                          mediaServerFormatValues
                        )}
                        onChange={() =>
                          setFieldValue(
                            'mediaServerLogin',
                            !values.mediaServerLogin
                          )
                        }
                      />
                      <LabeledCheckbox
                        id="xuiLogin"
                        className="mt-4"
                        label={intl.formatMessage(messages.xuiLogin)}
                        description={intl.formatMessage(messages.xuiLoginTip)}
                        onChange={() =>
                          setFieldValue('xuiLogin', !values.xuiLogin)
                        }
                      />
                    </div>
                  </div>
                </div>
                <div className="form-row">
                  <label htmlFor="xuiPlayerApiUrl" className="text-label">
                    {intl.formatMessage(messages.xuiPlayerApiUrl)}
                    <span className="label-tip">
                      {intl.formatMessage(messages.xuiPlayerApiUrlTip)}
                    </span>
                  </label>
                  <div className="form-input-area">
                    <div className="form-input-field">
                      <Field
                        id="xuiPlayerApiUrl"
                        name="xuiPlayerApiUrl"
                        type="text"
                      />
                    </div>
                  </div>
                </div>
                <div className="form-row">
                  <label htmlFor="xuiTimeoutSeconds" className="text-label">
                    {intl.formatMessage(messages.xuiTimeoutSeconds)}
                    <span className="label-tip">
                      {intl.formatMessage(messages.xuiTimeoutSecondsTip)}
                    </span>
                  </label>
                  <div className="form-input-area">
                    <div className="form-input-field">
                      <Field
                        id="xuiTimeoutSeconds"
                        name="xuiTimeoutSeconds"
                        type="number"
                        min="1"
                        max="60"
                      />
                    </div>
                  </div>
                </div>

                <div className="form-row">
                  <label htmlFor="newPlexLogin" className="checkbox-label">
                    {intl.formatMessage(
                      messages.newPlexLogin,
                      mediaServerFormatValues
                    )}
                    <span className="label-tip">
                      {intl.formatMessage(
                        messages.newPlexLoginTip,
                        mediaServerFormatValues
                      )}
                    </span>
                  </label>
                  <div className="form-input-area">
                    <Field
                      type="checkbox"
                      id="newPlexLogin"
                      name="newPlexLogin"
                      onChange={() => {
                        setFieldValue('newPlexLogin', !values.newPlexLogin);
                      }}
                    />
                  </div>
                </div>
                <div className="form-group">
                  <div className="form-row">
                    <span className="group-label">
                      {intl.formatMessage(messages.trackedSyncSettings)}
                      <span className="label-tip">
                        {intl.formatMessage(messages.trackedSyncSettingsDescription)}
                      </span>
                    </span>
                    <div className="form-input-area max-w-lg">
                      <LabeledCheckbox
                        id="trackedSyncEnabled"
                        label={intl.formatMessage(messages.trackedSyncEnabled)}
                        description={intl.formatMessage(messages.trackedSyncEnabledTip)}
                        onChange={() =>
                          setFieldValue(
                            'trackedSyncEnabled',
                            !values.trackedSyncEnabled
                          )
                        }
                      />
                    </div>
                  </div>
                  <div className="form-row">
                    <label htmlFor="trackedSyncUrl" className="text-label">
                      {intl.formatMessage(messages.trackedSyncUrl)}
                    </label>
                    <div className="form-input-area">
                      <div className="form-input-field">
                        <Field id="trackedSyncUrl" name="trackedSyncUrl" type="text" />
                      </div>
                    </div>
                  </div>
                  <div className="form-row">
                    <label htmlFor="trackedSyncApiKey" className="text-label">
                      {intl.formatMessage(messages.trackedSyncApiKey)}
                    </label>
                    <div className="form-input-area">
                      <div className="form-input-field">
                        <SensitiveInput
                          as="field"
                          id="trackedSyncApiKey"
                          name="trackedSyncApiKey"
                          type="password"
                        />
                      </div>
                    </div>
                  </div>
                  <div className="form-row">
                    <label htmlFor="trackedSyncCategory" className="text-label">
                      {intl.formatMessage(messages.trackedSyncCategory)}
                    </label>
                    <div className="form-input-area">
                      <div className="form-input-field">
                        <Field
                          id="trackedSyncCategory"
                          name="trackedSyncCategory"
                          type="text"
                        />
                      </div>
                    </div>
                  </div>
                  <div className="form-row">
                    <span className="text-label">Payload format</span>
                    <div className="form-input-area">
                      <p className="mb-2 text-sm text-gray-300">
                        {intl.formatMessage(messages.trackedSyncPayloadTip)}
                      </p>
                      <pre className="overflow-x-auto rounded-md bg-gray-900 p-3 text-xs text-gray-200">
{`{
  "event": "watchlist_added | watched_toggled | watchlist_removed | request_created",
  "category": "tracked",
  "tmdbId": 12345,
  "mediaType": "movie | tv",
  "title": "Title",
  "watched": false,
  "requestedBy": {
    "id": 12,
    "username": "user",
    "email": "user@email.com"
  },
  "externalIds": {
    "tmdbId": 12345,
    "imdbId": "tt1234567",
    "tvdbId": 987654
  },
  "metadata": {
    "mediaStatus": 2,
    "mediaStatus4k": 1,
    "requestId": 999,
    "requestStatus": 2,
    "is4k": false
  }
}`}
                      </pre>
                    </div>
                  </div>
                </div>
                <div className="form-row">
                  <label htmlFor="applicationTitle" className="text-label">
                    {intl.formatMessage(messages.movieRequestLimitLabel)}
                  </label>
                  <div className="form-input-area">
                    <QuotaSelector
                      onChange={setFieldValue}
                      dayFieldName="movieQuotaDays"
                      limitFieldName="movieQuotaLimit"
                      mediaType="movie"
                      defaultDays={values.movieQuotaDays}
                      defaultLimit={values.movieQuotaLimit}
                    />
                  </div>
                </div>
                <div className="form-row">
                  <label htmlFor="applicationTitle" className="text-label">
                    {intl.formatMessage(messages.tvRequestLimitLabel)}
                  </label>
                  <div className="form-input-area">
                    <QuotaSelector
                      onChange={setFieldValue}
                      dayFieldName="tvQuotaDays"
                      limitFieldName="tvQuotaLimit"
                      mediaType="tv"
                      defaultDays={values.tvQuotaDays}
                      defaultLimit={values.tvQuotaLimit}
                    />
                  </div>
                </div>
                <div
                  role="group"
                  aria-labelledby="group-label"
                  className="form-group"
                >
                  <div className="form-row">
                    <span id="group-label" className="group-label">
                      {intl.formatMessage(messages.defaultPermissions)}
                      <span className="label-tip">
                        {intl.formatMessage(messages.defaultPermissionsTip)}
                      </span>
                    </span>
                    <div className="form-input-area">
                      <div className="max-w-lg">
                        <PermissionEdit
                          currentPermission={values.defaultPermissions}
                          onUpdate={(newPermissions) =>
                            setFieldValue('defaultPermissions', newPermissions)
                          }
                        />
                      </div>
                    </div>
                  </div>
                </div>
                <div className="actions">
                  <div className="flex justify-end">
                    <span className="ml-3 inline-flex rounded-md shadow-sm">
                      <Button
                        buttonType="primary"
                        type="submit"
                        disabled={isSubmitting || !isValid}
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
      </div>
      <div className="section mt-6">
        <div className="mb-6">
          <h3 className="heading">{intl.formatMessage(messages.xuiTestSection)}</h3>
          <p className="description">
            {intl.formatMessage(messages.xuiTestDescription)}
          </p>
        </div>
        <div className="form-row">
          <label htmlFor="xuiTestUsername" className="text-label">
            {intl.formatMessage(messages.xuiTestUsername)}
          </label>
          <div className="form-input-area">
            <div className="form-input-field">
              <input
                id="xuiTestUsername"
                type="text"
                value={testUsername}
                onChange={(e) => setTestUsername(e.target.value)}
              />
            </div>
          </div>
        </div>
        <div className="form-row">
          <label htmlFor="xuiTestPassword" className="text-label">
            {intl.formatMessage(messages.xuiTestPassword)}
          </label>
          <div className="form-input-area">
            <div className="form-input-field">
              <SensitiveInput
                id="xuiTestPassword"
                type="password"
                value={testPassword}
                onChange={(e) => setTestPassword(e.target.value)}
              />
            </div>
          </div>
        </div>
        <div className="actions">
          <div className="flex justify-end">
            <span className="ml-3 inline-flex rounded-md shadow-sm">
              <Button
                buttonType="primary"
                type="button"
                disabled={isTestingXui || !testUsername || !testPassword}
                onClick={testXui}
              >
                <ArrowDownOnSquareIcon />
                <span>{intl.formatMessage(messages.xuiTestButton)}</span>
              </Button>
            </span>
          </div>
        </div>
        {xuiTestResult && (
          <div className="mt-4">
            <div className={xuiTestResult.ok ? 'text-green-400' : 'text-red-400'}>
              <div className="flex items-center gap-2">
                {xuiTestResult.ok ? (
                  <CheckCircleIcon className="h-5 w-5" />
                ) : (
                  <XCircleIcon className="h-5 w-5" />
                )}
                <span>
                  {xuiTestResult.ok
                    ? intl.formatMessage(messages.xuiTestOk)
                    : intl.formatMessage(messages.xuiTestFailed)}
                </span>
              </div>
              <div className="mt-2 text-sm text-gray-300">
                reason: {xuiTestResult.reason} | user:{' '}
                {xuiTestResult.parsed.username || '(none)'} | status:{' '}
                {xuiTestResult.parsed.status || '(none)'} | expired:{' '}
                {xuiTestResult.parsed.isExpired ? 'yes' : 'no'}
              </div>
            </div>
          </div>
        )}
        {xuiTestError && <div className="mt-4 error">{xuiTestError}</div>}
      </div>
    </>
  );
};

export default SettingsUsers;
