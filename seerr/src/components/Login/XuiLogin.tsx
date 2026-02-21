import Button from '@app/components/Common/Button';
import SensitiveInput from '@app/components/Common/SensitiveInput';
import defineMessages from '@app/utils/defineMessages';
import { ArrowLeftOnRectangleIcon } from '@heroicons/react/24/outline';
import axios from 'axios';
import { Field, Form, Formik } from 'formik';
import { useState } from 'react';
import { useIntl } from 'react-intl';
import * as Yup from 'yup';

const messages = defineMessages('components.Login', {
  sportshubusername: 'SportsHub Username',
  sportshubpassword: 'SportsHub Password',
  validationUserRequired: 'You must provide a username',
  validationPasswordRequired: 'You must provide a password',
  loginerror: 'Something went wrong while trying to sign in.',
  signingin: 'Signing In…',
  signin: 'Sign In',
});

interface XuiLoginProps {
  revalidate: () => void;
}

const XuiLogin = ({ revalidate }: XuiLoginProps) => {
  const intl = useIntl();
  const [loginError, setLoginError] = useState<string | null>(null);

  const LoginSchema = Yup.object().shape({
    username: Yup.string().required(
      intl.formatMessage(messages.validationUserRequired)
    ),
    password: Yup.string().required(
      intl.formatMessage(messages.validationPasswordRequired)
    ),
  });

  return (
    <Formik
      initialValues={{
        username: '',
        password: '',
      }}
      validationSchema={LoginSchema}
      validateOnBlur={false}
      onSubmit={async (values) => {
        setLoginError(null);

        try {
          await axios.post('/api/v1/auth/xui', {
            username: values.username,
            password: values.password,
          });
        } catch (e: unknown) {
          const axiosError = e as {
            response?: { data?: { message?: string; error?: string } };
          };
          const apiMessage =
            axiosError.response?.data?.message ??
            axiosError.response?.data?.error;
          const sanitizedMessage = apiMessage
            ? apiMessage
                .replace(/xui/gi, 'SportsHub')
                .replace(/xtream/gi, 'SportsHub')
            : null;
          setLoginError(
            sanitizedMessage || intl.formatMessage(messages.loginerror)
          );
        } finally {
          revalidate();
        }
      }}
    >
      {({ errors, touched, isSubmitting, isValid }) => {
        return (
          <Form data-form-type="login">
            <div>
              <div className="mb-4 mt-1">
                <div className="form-input-field">
                  <Field
                    id="username"
                    name="username"
                    placeholder={intl.formatMessage(messages.sportshubusername)}
                    type="text"
                    data-form-type="username"
                    className="!bg-gray-700/80 placeholder:text-gray-400"
                  />
                </div>
                {errors.username &&
                  touched.username &&
                  typeof errors.username === 'string' && (
                    <div className="error">{errors.username}</div>
                  )}
              </div>

              <div className="mb-2 mt-1">
                <div className="form-input-field">
                  <SensitiveInput
                    as="field"
                    id="password"
                    name="password"
                    type="password"
                    placeholder={intl.formatMessage(messages.sportshubpassword)}
                    autoComplete="current-password"
                    className="!bg-gray-700/80 placeholder:text-gray-400"
                    data-form-type="password"
                    data-1pignore="false"
                    data-lpignore="false"
                  />
                </div>
                {errors.password &&
                  touched.password &&
                  typeof errors.password === 'string' && (
                    <div className="error">{errors.password}</div>
                  )}
              </div>

              {loginError && (
                <div className="mb-2 mt-1 sm:col-span-2 sm:mt-0">
                  <div className="error">{loginError}</div>
                </div>
              )}
            </div>

            <Button
              buttonType="primary"
              type="submit"
              disabled={isSubmitting || !isValid}
              className="mt-2 w-full shadow-sm"
            >
              <ArrowLeftOnRectangleIcon />
              <span>
                {isSubmitting
                  ? intl.formatMessage(messages.signingin)
                  : intl.formatMessage(messages.signin)}
              </span>
            </Button>
          </Form>
        );
      }}
    </Formik>
  );
};

export default XuiLogin;
