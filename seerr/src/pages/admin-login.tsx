import Button from '@app/components/Common/Button';
import ImageFader from '@app/components/Common/ImageFader';
import PageTitle from '@app/components/Common/PageTitle';
import LanguagePicker from '@app/components/Layout/LanguagePicker';
import PlexLoginButton from '@app/components/Login/PlexLoginButton';
import { useUser } from '@app/hooks/useUser';
import defineMessages from '@app/utils/defineMessages';
import { Transition } from '@headlessui/react';
import { XCircleIcon } from '@heroicons/react/24/solid';
import axios from 'axios';
import type { NextPage } from 'next';
import Image from 'next/image';
import { useRouter } from 'next/router';
import { useEffect, useState } from 'react';
import { useIntl } from 'react-intl';
import useSWR from 'swr';

const messages = defineMessages('pages.AdminLogin', {
  title: 'Admin Sign In',
  header: 'Sportshub Admin',
  subheader: 'Sign in with Plex admin account',
  backToUserLogin: 'Back to User Login',
});

const AdminLoginPage: NextPage = () => {
  const intl = useIntl();
  const router = useRouter();
  const { user, revalidate } = useUser();
  const [error, setError] = useState('');
  const [isProcessing, setProcessing] = useState(false);
  const [authToken, setAuthToken] = useState<string | undefined>(undefined);

  useEffect(() => {
    const login = async () => {
      setProcessing(true);
      try {
        const response = await axios.post('/api/v1/auth/plex', { authToken });

        if (response.data?.id) {
          revalidate();
        }
      } catch (e) {
        const apiError = e as { response?: { data?: { message?: string } } };
        setError(apiError.response?.data?.message || 'Plex login failed.');
        setAuthToken(undefined);
        setProcessing(false);
      }
    };
    if (authToken) {
      login();
    }
  }, [authToken, revalidate]);

  useEffect(() => {
    if (user) {
      router.push('/');
    }
  }, [router, user]);

  const { data: backdrops } = useSWR<string[]>('/api/v1/backdrops', {
    refreshInterval: 0,
    refreshWhenHidden: false,
    revalidateOnFocus: false,
  });

  return (
    <div className="relative flex min-h-screen flex-col bg-gray-900 py-14">
      <PageTitle title={intl.formatMessage(messages.title)} />
      <ImageFader
        backgroundImages={
          backdrops?.map(
            (backdrop) => `https://image.tmdb.org/t/p/original${backdrop}`
          ) ?? []
        }
      />
      <div className="absolute right-4 top-4 z-50">
        <LanguagePicker />
      </div>
      <div className="relative z-40 mt-10 flex flex-col items-center px-4 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="relative h-48 w-full max-w-full">
          <Image src="/logo_stacked.svg" alt="Logo" fill />
        </div>
      </div>
      <div className="relative z-50 mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div
          className="bg-gray-800 bg-opacity-50 shadow sm:rounded-lg"
          style={{ backdropFilter: 'blur(5px)' }}
        >
          <Transition
            as="div"
            show={!!error}
            enter="transition-opacity duration-300"
            enterFrom="opacity-0"
            enterTo="opacity-100"
            leave="transition-opacity duration-300"
            leaveFrom="opacity-100"
            leaveTo="opacity-0"
          >
            <div className="mb-4 rounded-md bg-red-600 p-4">
              <div className="flex">
                <div className="flex-shrink-0">
                  <XCircleIcon className="h-5 w-5 text-red-300" />
                </div>
                <div className="ml-3">
                  <h3 className="text-sm font-medium text-red-300">{error}</h3>
                </div>
              </div>
            </div>
          </Transition>
          <div className="px-10 py-8">
            <h2 className="mb-2 text-center text-xl font-bold text-neutral-100">
              {intl.formatMessage(messages.header)}
            </h2>
            <p className="mb-6 text-center text-sm text-gray-300">
              {intl.formatMessage(messages.subheader)}
            </p>
            <PlexLoginButton
              isProcessing={isProcessing}
              onAuthToken={(token) => setAuthToken(token)}
              large
            />
            <div className="mt-6 text-center">
              <Button
                buttonType="ghost"
                className="w-full"
                onClick={() => router.push('/login')}
              >
                {intl.formatMessage(messages.backToUserLogin)}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminLoginPage;
