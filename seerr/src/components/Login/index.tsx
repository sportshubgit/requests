import ImageFader from '@app/components/Common/ImageFader';
import Modal from '@app/components/Common/Modal';
import { SettingsContext } from '@app/context/SettingsContext';
import PageTitle from '@app/components/Common/PageTitle';
import LanguagePicker from '@app/components/Layout/LanguagePicker';
import XuiLogin from '@app/components/Login/XuiLogin';
import { useUser } from '@app/hooks/useUser';
import defineMessages from '@app/utils/defineMessages';
import { Transition } from '@headlessui/react';
import { useRouter } from 'next/dist/client/router';
import Image from 'next/image';
import { useContext, useEffect, useState } from 'react';
import { useIntl } from 'react-intl';
import useSWR from 'swr';

const messages = defineMessages('components.Login', {
  signin: 'Sign In',
  sportshubsigninheader: 'SportsHub',
  sportshubsigninsubheader: 'Log in with SportsHub credentials.',
  welcomeTitle: 'Welcome to SportsHub',
  welcomeBody:
    'Use SportsHub to request movies and series, and save titles to My List.',
  welcomeStepOne: 'Add titles to My List from any movie or series page.',
  welcomeStepTwo:
    'If a title is missing, request it and SportsHub will process it for you.',
  welcomeStepThree:
    'When new content is ready, refresh your playlist in your player app to see it.',
  welcomeButton: 'Got it',
});

const Login = () => {
  const intl = useIntl();
  const router = useRouter();
  const { user, revalidate } = useUser();
  const { currentSettings } = useContext(SettingsContext);
  const [showWelcome, setShowWelcome] = useState(false);

  // Effect that is triggered whenever `useUser`'s user changes. If we get a new
  // valid user, we redirect the user to the home page as the login was successful.
  useEffect(() => {
    if (user) {
      router.push('/');
    }
  }, [user, router]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    if (!currentSettings.loginGuideEnabled) {
      setShowWelcome(false);
      return;
    }

    const seen = window.localStorage.getItem('sportshub-login-guide-seen');
    if (!currentSettings.loginGuideShowOnce || seen !== 'true') {
      setShowWelcome(true);
    }
  }, [currentSettings.loginGuideEnabled, currentSettings.loginGuideShowOnce]);

  const dismissWelcome = () => {
    setShowWelcome(false);
    if (typeof window !== 'undefined') {
      window.localStorage.setItem('sportshub-login-guide-seen', 'true');
    }
  };

  const { data: backdrops } = useSWR<string[]>('/api/v1/backdrops', {
    refreshInterval: 0,
    refreshWhenHidden: false,
    revalidateOnFocus: false,
  });

  return (
    <div className="relative flex min-h-screen flex-col bg-gray-900 py-14">
      <PageTitle title={intl.formatMessage(messages.signin)} />
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
          <div className="px-10 py-8">
            <h2 className="mb-2 text-center text-xl font-bold text-neutral-100">
              {intl.formatMessage(messages.sportshubsigninheader)}
            </h2>
            <p className="mb-6 text-center text-sm text-gray-300">
              {intl.formatMessage(messages.sportshubsigninsubheader)}
            </p>
            <XuiLogin revalidate={revalidate} />
          </div>
        </div>
      </div>
      <Transition
        appear
        show={showWelcome && currentSettings.loginGuideEnabled}
        enter="transition ease-in-out duration-300 transform opacity-0"
        enterFrom="opacity-0"
        enterTo="opacity-100"
        leave="transition ease-in-out duration-300 transform opacity-100"
        leaveFrom="opacity-100"
        leaveTo="opacity-0"
      >
        <Modal
          title={
            currentSettings.loginGuideTitle ||
            intl.formatMessage(messages.welcomeTitle)
          }
          onOk={dismissWelcome}
          okText={intl.formatMessage(messages.welcomeButton)}
          backgroundClickable={false}
        >
          <p className="text-sm text-gray-200">
            {currentSettings.loginGuideBody ||
              intl.formatMessage(messages.welcomeBody)}
          </p>
          <ul className="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-200">
            <li>
              {currentSettings.loginGuideStepOne ||
                intl.formatMessage(messages.welcomeStepOne)}
            </li>
            <li>
              {currentSettings.loginGuideStepTwo ||
                intl.formatMessage(messages.welcomeStepTwo)}
            </li>
            <li>
              {currentSettings.loginGuideStepThree ||
                intl.formatMessage(messages.welcomeStepThree)}
            </li>
          </ul>
        </Modal>
      </Transition>
    </div>
  );
};

export default Login;
