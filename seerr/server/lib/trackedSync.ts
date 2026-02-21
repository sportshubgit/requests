import type { MediaType } from '@server/constants/media';
import type { User } from '@server/entity/User';
import { getSettings } from '@server/lib/settings';
import logger from '@server/logger';
import axios from 'axios';

type TrackedSyncEvent = 'watchlist_added' | 'watchlist_removed' | 'watched_toggled' | 'request_created';

type TrackedExternalIds = {
  tmdbId: number;
  imdbId?: string;
  tvdbId?: number;
};

type TrackedSyncPayload = {
  event: TrackedSyncEvent;
  category: string;
  tmdbId: number;
  mediaType: MediaType;
  title?: string;
  watched?: boolean;
  requestedBy: {
    id: number;
    username?: string;
    email?: string;
  };
  externalIds?: TrackedExternalIds;
  metadata?: Record<string, unknown>;
};

export const pushTrackedSyncEvent = async (
  payload: TrackedSyncPayload
): Promise<void> => {
  const settings = getSettings();
  const enabled = settings.main.trackedSyncEnabled;
  const url = settings.main.trackedSyncUrl?.trim();

  if (!enabled || !url) {
    return;
  }

  try {
    const apiKey = settings.main.trackedSyncApiKey?.trim();
    await axios.post(url, payload, {
      timeout: 8000,
      headers: apiKey ? { Authorization: `Bearer ${apiKey}` } : undefined,
    });
  } catch (e) {
    logger.error('Failed to push tracked sync event', {
      label: 'Tracked Sync',
      errorMessage: e instanceof Error ? e.message : 'Unknown error',
      payload,
    });
  }
};

export const buildTrackedSyncUser = (user: User): TrackedSyncPayload['requestedBy'] => ({
  id: user.id,
  username: user.username,
  email: user.email,
});

export const buildTrackedExternalIds = (ids: {
  tmdbId: number;
  imdbId?: string | null;
  tvdbId?: number | null;
}): TrackedExternalIds => {
  const externalIds: TrackedExternalIds = {
    tmdbId: ids.tmdbId,
  };

  if (ids.imdbId) {
    externalIds.imdbId = ids.imdbId;
  }

  if (typeof ids.tvdbId === 'number') {
    externalIds.tvdbId = ids.tvdbId;
  }

  return externalIds;
};
