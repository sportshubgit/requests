import Button from '@app/components/Common/Button';
import Header from '@app/components/Common/Header';
import LoadingSpinner from '@app/components/Common/LoadingSpinner';
import Modal from '@app/components/Common/Modal';
import PageTitle from '@app/components/Common/PageTitle';
import defineMessages from '@app/utils/defineMessages';
import { Transition } from '@headlessui/react';
import { QuestionMarkCircleIcon } from '@heroicons/react/24/outline';
import { MediaStatus, MediaType } from '@server/constants/media';
import axios from 'axios';
import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useIntl } from 'react-intl';
import { useToasts } from 'react-toast-notifications';
import useSWR from 'swr';

type WatchlistRow = {
  id: number;
  tmdbId: number;
  title: string;
  mediaType: MediaType;
  watched: boolean;
  watchedAt?: string | null;
  customCategory?: string | null;
  media?: {
    status?: MediaStatus;
    status4k?: MediaStatus;
  };
};

const messages = defineMessages('components.MyList', {
  pageTitle: 'My List',
  heading: 'My List',
  empty: 'Your list is empty. Add movies or series from any detail page.',
  markWatched: 'Mark watched',
  markUnwatched: 'Mark unwatched',
  remove: 'Remove',
  status: 'Status',
  watched: 'Watched',
  unwatched: 'Unwatched',
  updated: 'List updated.',
  updateFailed: 'Could not update list item.',
  loadFailed: 'Could not load My List. Please refresh or contact support.',
  howItWorks: 'How My List works',
  dismissGuide: 'Dismiss',
  guideBannerTitle: 'Keep track of what you want to watch',
  guideBannerBody:
    'Use My List for favourites and requests. When new content is ready, refresh your playlist in your player app.',
  guideStepOne: '1. Tap the star on a movie or series to add it to My List.',
  guideStepTwo:
    '2. If a title is missing, send a request from the same page.',
  guideStepThree:
    '3. Your requests are also added to My List automatically.',
  guideStepFour:
    '4. When content is available, refresh your playlist/library in your player app.',
  guideStatusLegend: 'Status guide',
  guideStatusRequested: 'Requested: we have your request.',
  guideStatusDownloading: 'Downloading: content is being prepared.',
  guideStatusAvailable: 'Available: ready to play in your app.',
});

const getStatusLabel = (status?: MediaStatus) => {
  switch (status) {
    case MediaStatus.AVAILABLE:
      return 'Available';
    case MediaStatus.PARTIALLY_AVAILABLE:
      return 'Partially Available';
    case MediaStatus.PROCESSING:
      return 'Downloading';
    case MediaStatus.PENDING:
      return 'Requested';
    case MediaStatus.BLOCKLISTED:
      return 'Blocklisted';
    default:
      return 'Unknown';
  }
};

const MyList = () => {
  const intl = useIntl();
  const { addToast } = useToasts();
  const { data, error, mutate } = useSWR<WatchlistRow[]>('/api/v1/watchlist');
  const [showGuide, setShowGuide] = useState(false);
  const [showGuideBanner, setShowGuideBanner] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const dismissed = window.localStorage.getItem('sportshub-my-list-guide-dismissed');
    setShowGuideBanner(dismissed !== 'true');
  }, []);

  const dismissGuideBanner = () => {
    setShowGuideBanner(false);
    if (typeof window !== 'undefined') {
      window.localStorage.setItem('sportshub-my-list-guide-dismissed', 'true');
    }
  };

  if (!data && !error) {
    return <LoadingSpinner />;
  }

  if (error) {
    return (
      <>
        <PageTitle title={intl.formatMessage(messages.pageTitle)} />
        <div className="mb-5 mt-1">
          <div className="flex items-center justify-between gap-3">
            <Header>{intl.formatMessage(messages.heading)}</Header>
            <Button buttonType="default" buttonSize="sm" onClick={() => setShowGuide(true)}>
              <QuestionMarkCircleIcon />
              <span>{intl.formatMessage(messages.howItWorks)}</span>
            </Button>
          </div>
        </div>
        <div className="rounded-lg border border-red-700 bg-red-900/20 p-6 text-red-200">
          {intl.formatMessage(messages.loadFailed)}
        </div>
      </>
    );
  }

  const onToggleWatched = async (item: WatchlistRow) => {
    try {
      await axios.patch(`/api/v1/watchlist/${item.tmdbId}`, {
        watched: !item.watched,
      });
      await mutate();
      addToast(intl.formatMessage(messages.updated), {
        appearance: 'success',
        autoDismiss: true,
      });
    } catch {
      addToast(intl.formatMessage(messages.updateFailed), {
        appearance: 'error',
        autoDismiss: true,
      });
    }
  };

  const onRemove = async (item: WatchlistRow) => {
    try {
      await axios.delete(`/api/v1/watchlist/${item.tmdbId}`);
      await mutate();
      addToast(intl.formatMessage(messages.updated), {
        appearance: 'success',
        autoDismiss: true,
      });
    } catch {
      addToast(intl.formatMessage(messages.updateFailed), {
        appearance: 'error',
        autoDismiss: true,
      });
    }
  };

  return (
    <>
      <PageTitle title={intl.formatMessage(messages.pageTitle)} />
      <div className="mb-5 mt-1">
        <div className="flex items-center justify-between gap-3">
          <Header>{intl.formatMessage(messages.heading)}</Header>
          <Button buttonType="default" buttonSize="sm" onClick={() => setShowGuide(true)}>
            <QuestionMarkCircleIcon />
            <span>{intl.formatMessage(messages.howItWorks)}</span>
          </Button>
        </div>
      </div>
      {showGuideBanner && (
        <div className="mb-4 rounded-lg border border-indigo-500/50 bg-indigo-500/10 p-4">
          <p className="text-sm font-semibold text-indigo-100">
            {intl.formatMessage(messages.guideBannerTitle)}
          </p>
          <p className="mt-1 text-sm text-indigo-100/90">
            {intl.formatMessage(messages.guideBannerBody)}
          </p>
          <div className="mt-3">
            <Button buttonType="default" buttonSize="sm" onClick={dismissGuideBanner}>
              {intl.formatMessage(messages.dismissGuide)}
            </Button>
          </div>
        </div>
      )}

      {data && data.length === 0 ? (
        <div className="rounded-lg border border-gray-700 bg-gray-900/50 p-6 text-gray-300">
          {intl.formatMessage(messages.empty)}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
          {(data ?? []).map((item) => {
            const href =
              item.mediaType === MediaType.MOVIE
                ? `/movie/${item.tmdbId}`
                : `/tv/${item.tmdbId}`;
            return (
              <div
                key={`${item.mediaType}-${item.tmdbId}`}
                className="rounded-lg border border-gray-700 bg-gray-900/50 p-4"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <Link href={href} className="text-lg font-semibold hover:underline">
                      {item.title}
                    </Link>
                    <div className="mt-1 text-xs uppercase tracking-wide text-gray-400">
                      {item.mediaType}
                    </div>
                    <div className="mt-2 text-sm text-gray-300">
                      {intl.formatMessage(messages.status)}:{' '}
                      {getStatusLabel(item.media?.status)}
                    </div>
                    <div className="mt-1 text-sm text-gray-300">
                      {item.watched
                        ? intl.formatMessage(messages.watched)
                        : intl.formatMessage(messages.unwatched)}
                    </div>
                  </div>
                  <div className="flex flex-col gap-2">
                    <Button
                      buttonType={item.watched ? 'warning' : 'success'}
                      buttonSize="sm"
                      onClick={() => onToggleWatched(item)}
                    >
                      {item.watched
                        ? intl.formatMessage(messages.markUnwatched)
                        : intl.formatMessage(messages.markWatched)}
                    </Button>
                    <Button
                      buttonType="danger"
                      buttonSize="sm"
                      onClick={() => onRemove(item)}
                    >
                      {intl.formatMessage(messages.remove)}
                    </Button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
      <Transition
        appear
        show={showGuide}
        enter="transition ease-in-out duration-300 transform opacity-0"
        enterFrom="opacity-0"
        enterTo="opacuty-100"
        leave="transition ease-in-out duration-300 transform opacity-100"
        leaveFrom="opacity-100"
        leaveTo="opacity-0"
      >
        <Modal
          title={intl.formatMessage(messages.howItWorks)}
          onCancel={() => setShowGuide(false)}
          cancelText={intl.formatMessage(messages.dismissGuide)}
        >
          <ul className="list-disc space-y-2 pl-5 text-sm text-gray-200">
            <li>{intl.formatMessage(messages.guideStepOne)}</li>
            <li>{intl.formatMessage(messages.guideStepTwo)}</li>
            <li>{intl.formatMessage(messages.guideStepThree)}</li>
            <li>{intl.formatMessage(messages.guideStepFour)}</li>
          </ul>
          <div className="mt-4 rounded-md border border-gray-700 bg-gray-900/70 p-3 text-sm">
            <p className="font-semibold text-gray-200">
              {intl.formatMessage(messages.guideStatusLegend)}
            </p>
            <p className="mt-1 text-gray-300">
              {intl.formatMessage(messages.guideStatusRequested)}
            </p>
            <p className="mt-1 text-gray-300">
              {intl.formatMessage(messages.guideStatusDownloading)}
            </p>
            <p className="mt-1 text-gray-300">
              {intl.formatMessage(messages.guideStatusAvailable)}
            </p>
          </div>
        </Modal>
      </Transition>
    </>
  );
};

export default MyList;
