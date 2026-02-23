import {
  DuplicateWatchlistRequestError,
  NotFoundError,
  Watchlist,
} from '@server/entity/Watchlist';
import { getRepository } from '@server/datasource';
import logger from '@server/logger';
import { Router } from 'express';
import { QueryFailedError } from 'typeorm';

import { watchlistCreate, watchlistUpdate } from '@server/interfaces/api/watchlistCreate';
import {
  buildTrackedExternalIds,
  buildTrackedSyncUser,
  pushTrackedSyncEvent,
} from '@server/lib/trackedSync';
import { getSettings } from '@server/lib/settings';

const watchlistRoutes = Router();

watchlistRoutes.get('/', async (req, res, next) => {
  if (!req.user) {
    return next({
      status: 401,
      message: 'You must be logged in to view watchlist data.',
    });
  }

  try {
    const watchlist = await getRepository(Watchlist).find({
      where: { requestedBy: { id: req.user.id } },
      order: { updatedAt: 'DESC' },
    });

    return res.status(200).json(watchlist);
  } catch (e) {
    return next({ status: 500, message: e instanceof Error ? e.message : 'Unknown error' });
  }
});

watchlistRoutes.post<never, Watchlist, Watchlist>(
  '/',
  async (req, res, next) => {
    try {
      if (!req.user) {
        return next({
          status: 401,
          message: 'You must be logged in to add watchlist.',
        });
      }
      const values = watchlistCreate.parse(req.body);

      const request = await Watchlist.createWatchlist({
        watchlistRequest: values,
        user: req.user,
      });

      const trackedSettings = getSettings().main;
      await pushTrackedSyncEvent({
        event: 'watchlist_added',
        category: trackedSettings.trackedSyncCategory || 'tracked',
        tmdbId: request.tmdbId,
        mediaType: request.mediaType,
        title: request.title,
        watched: request.watched,
        requestedBy: buildTrackedSyncUser(req.user),
        externalIds: buildTrackedExternalIds({
          tmdbId: request.tmdbId,
          imdbId: request.media?.imdbId,
          tvdbId: request.media?.tvdbId,
        }),
        metadata: {
          watchedAt: request.watchedAt ?? null,
          mediaStatus: request.media?.status ?? null,
          mediaStatus4k: request.media?.status4k ?? null,
        },
      });

      return res.status(201).json(request);
    } catch (error) {
      if (!(error instanceof Error)) {
        return;
      }
      switch (error.constructor) {
        case QueryFailedError:
          logger.warn('Something wrong with data watchlist', {
            tmdbId: req.body.tmdbId,
            mediaType: req.body.mediaType,
            label: 'Watchlist',
          });
          return next({ status: 409, message: 'Something wrong' });
        case DuplicateWatchlistRequestError:
          return next({ status: 409, message: error.message });
        default:
          return next({ status: 500, message: error.message });
      }
    }
  }
);

watchlistRoutes.patch('/:tmdbId', async (req, res, next) => {
  if (!req.user) {
    return next({
      status: 401,
      message: 'You must be logged in to update watchlist data.',
    });
  }

  try {
    const values = watchlistUpdate.parse(req.body);
    const trackedSettings = getSettings().main;
    const watchlist = await Watchlist.updateWatched(
      Number(req.params.tmdbId),
      req.user,
      values.watched,
      values.customCategory ?? trackedSettings.trackedSyncCategory
    );

    await pushTrackedSyncEvent({
      event: 'watched_toggled',
      category:
        values.customCategory ??
        watchlist.customCategory ??
        trackedSettings.trackedSyncCategory ??
        'tracked',
      tmdbId: watchlist.tmdbId,
      mediaType: watchlist.mediaType,
      title: watchlist.title,
      watched: watchlist.watched,
      requestedBy: buildTrackedSyncUser(req.user),
      externalIds: buildTrackedExternalIds({
        tmdbId: watchlist.tmdbId,
        imdbId: watchlist.media?.imdbId,
        tvdbId: watchlist.media?.tvdbId,
      }),
      metadata: {
        watchedAt: watchlist.watchedAt ?? null,
        mediaStatus: watchlist.media?.status ?? null,
        mediaStatus4k: watchlist.media?.status4k ?? null,
      },
    });

    return res.status(200).json(watchlist);
  } catch (e) {
    if (e instanceof NotFoundError) {
      return next({ status: 404, message: e.message });
    }
    return next({ status: 500, message: e instanceof Error ? e.message : 'Unknown error' });
  }
});

watchlistRoutes.delete('/:tmdbId', async (req, res, next) => {
  if (!req.user) {
    return next({
      status: 401,
      message: 'You must be logged in to delete watchlist data.',
    });
  }
  try {
    const removed = await Watchlist.deleteWatchlist(Number(req.params.tmdbId), req.user);

    if (removed) {
      const trackedSettings = getSettings().main;
      await pushTrackedSyncEvent({
        event: 'watchlist_removed',
        category:
          removed.customCategory ??
          trackedSettings.trackedSyncCategory ??
          'tracked',
        tmdbId: removed.tmdbId,
        mediaType: removed.mediaType,
        title: removed.title,
        watched: removed.watched,
        requestedBy: buildTrackedSyncUser(req.user),
        externalIds: buildTrackedExternalIds({
          tmdbId: removed.tmdbId,
          imdbId: removed.media?.imdbId,
          tvdbId: removed.media?.tvdbId,
        }),
        metadata: {
          watchedAt: removed.watchedAt ?? null,
          mediaStatus: removed.media?.status ?? null,
          mediaStatus4k: removed.media?.status4k ?? null,
        },
      });
    }

    return res.status(204).send();
  } catch (e) {
    if (e instanceof NotFoundError) {
      return next({
        status: 401,
        message: e.message,
      });
    }
    return next({ status: 500, message: e.message });
  }
});

export default watchlistRoutes;
