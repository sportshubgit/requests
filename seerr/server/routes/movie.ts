import IMDBRadarrProxy from '@server/api/rating/imdbRadarrProxy';
import RottenTomatoes from '@server/api/rating/rottentomatoes';
import { type RatingResponse } from '@server/api/ratings';
import TheMovieDb from '@server/api/themoviedb';
import { MediaStatus, MediaType } from '@server/constants/media';
import { getRepository } from '@server/datasource';
import Media from '@server/entity/Media';
import { getLiveAvailability } from '@server/lib/liveAvailability';
import {
  getTodayDateString,
  getUSMovieReleaseDate,
  isAtLeastOneYearOld,
  isReleasedOnOrBefore,
} from '@server/lib/usReleaseDate';
import { Watchlist } from '@server/entity/Watchlist';
import logger from '@server/logger';
import { mapMovieDetails } from '@server/models/Movie';
import { mapMovieResult } from '@server/models/Search';
import { Router } from 'express';

const movieRoutes = Router();

movieRoutes.get('/:id', async (req, res, next) => {
  const tmdb = new TheMovieDb();

  try {
    const tmdbMovie = await tmdb.getMovie({
      movieId: Number(req.params.id),
      language: (req.query.language as string) ?? req.locale,
    });

    const today = getTodayDateString();
    const usReleaseDate = getUSMovieReleaseDate(tmdbMovie.release_dates);
    const fallbackReleasedDate = isAtLeastOneYearOld(
      tmdbMovie.release_date,
      today
    )
      ? tmdbMovie.release_date
      : undefined;
    const effectiveReleaseDate = usReleaseDate ?? fallbackReleasedDate;

    if (!isReleasedOnOrBefore(effectiveReleaseDate, today)) {
      return next({
        status: 404,
        message: 'Movie not found.',
      });
    }

    if (effectiveReleaseDate) {
      tmdbMovie.release_date = effectiveReleaseDate;
    }

    const media = await Media.getMedia(tmdbMovie.id, MediaType.MOVIE);
    const liveAvailability = await getLiveAvailability({
      mediaType: MediaType.MOVIE,
      tmdbId: tmdbMovie.id,
      username: req.user?.username,
    });

    let mediaForUi = media;
    if (!mediaForUi && (liveAvailability.tracked || liveAvailability.available)) {
      mediaForUi = new Media({
        mediaType: MediaType.MOVIE,
        tmdbId: tmdbMovie.id,
        status: MediaStatus.UNKNOWN,
        status4k: MediaStatus.UNKNOWN,
      });
    }
    if (mediaForUi) {
      if (liveAvailability.available) {
        mediaForUi.status = MediaStatus.AVAILABLE;
      } else if (
        liveAvailability.tracked &&
        mediaForUi.status !== MediaStatus.AVAILABLE
      ) {
        mediaForUi.status = MediaStatus.PROCESSING;
      }

      if (liveAvailability.available4k) {
        mediaForUi.status4k = MediaStatus.AVAILABLE;
      } else if (
        liveAvailability.tracked4k &&
        mediaForUi.status4k !== MediaStatus.AVAILABLE
      ) {
        mediaForUi.status4k = MediaStatus.PROCESSING;
      }
    }

    const onUserWatchlist = await getRepository(Watchlist).exist({
      where: {
        tmdbId: Number(req.params.id),
        requestedBy: {
          id: req.user?.id,
        },
      },
    });

    const data = mapMovieDetails(tmdbMovie, mediaForUi, onUserWatchlist);

    // TMDB issue where it doesnt fallback to English when no overview is available in requested locale.
    if (!data.overview) {
      const tvEnglish = await tmdb.getMovie({ movieId: Number(req.params.id) });
      data.overview = tvEnglish.overview;
    }

    return res.status(200).json(data);
  } catch (e) {
    logger.debug('Something went wrong retrieving movie', {
      label: 'API',
      errorMessage: e.message,
      movieId: req.params.id,
    });
    return next({
      status: 500,
      message: 'Unable to retrieve movie.',
    });
  }
});

movieRoutes.get('/:id/recommendations', async (req, res, next) => {
  const tmdb = new TheMovieDb();

  try {
    const results = await tmdb.getMovieRecommendations({
      movieId: Number(req.params.id),
      page: Number(req.query.page),
      language: (req.query.language as string) ?? req.locale,
    });

    const releasedResults = results.results.filter((result) =>
      isReleasedOnOrBefore(result.release_date, getTodayDateString())
    );

    const media = await Media.getRelatedMedia(
      req.user,
      releasedResults.map((result) => result.id)
    );

    return res.status(200).json({
      page: results.page,
      totalPages: results.total_pages,
      totalResults: releasedResults.length,
      results: releasedResults.map((result) =>
        mapMovieResult(
          result,
          media.find(
            (req) =>
              req.tmdbId === result.id && req.mediaType === MediaType.MOVIE
          )
        )
      ),
    });
  } catch (e) {
    logger.debug('Something went wrong retrieving movie recommendations', {
      label: 'API',
      errorMessage: e.message,
      movieId: req.params.id,
    });
    return next({
      status: 500,
      message: 'Unable to retrieve movie recommendations.',
    });
  }
});

movieRoutes.get('/:id/similar', async (req, res, next) => {
  const tmdb = new TheMovieDb();

  try {
    const results = await tmdb.getMovieSimilar({
      movieId: Number(req.params.id),
      page: Number(req.query.page),
      language: (req.query.language as string) ?? req.locale,
    });

    const releasedResults = results.results.filter((result) =>
      isReleasedOnOrBefore(result.release_date, getTodayDateString())
    );

    const media = await Media.getRelatedMedia(
      req.user,
      releasedResults.map((result) => result.id)
    );

    return res.status(200).json({
      page: results.page,
      totalPages: results.total_pages,
      totalResults: releasedResults.length,
      results: releasedResults.map((result) =>
        mapMovieResult(
          result,
          media.find(
            (req) =>
              req.tmdbId === result.id && req.mediaType === MediaType.MOVIE
          )
        )
      ),
    });
  } catch (e) {
    logger.debug('Something went wrong retrieving similar movies', {
      label: 'API',
      errorMessage: e.message,
      movieId: req.params.id,
    });
    return next({
      status: 500,
      message: 'Unable to retrieve similar movies.',
    });
  }
});

/**
 * Endpoint backed by RottenTomatoes
 */
movieRoutes.get('/:id/ratings', async (req, res, next) => {
  const tmdb = new TheMovieDb();
  const rtapi = new RottenTomatoes();

  try {
    const movie = await tmdb.getMovie({
      movieId: Number(req.params.id),
    });

    const rtratings = await rtapi.getMovieRatings(
      movie.title,
      Number(movie.release_date.slice(0, 4))
    );

    if (!rtratings) {
      return next({
        status: 404,
        message: 'Rotten Tomatoes ratings not found.',
      });
    }

    return res.status(200).json(rtratings);
  } catch (e) {
    logger.debug('Something went wrong retrieving movie ratings', {
      label: 'API',
      errorMessage: e.message,
      movieId: req.params.id,
    });
    return next({
      status: 500,
      message: 'Unable to retrieve movie ratings.',
    });
  }
});

/**
 * Endpoint combining RottenTomatoes and IMDB
 */
movieRoutes.get('/:id/ratingscombined', async (req, res, next) => {
  const tmdb = new TheMovieDb();
  const rtapi = new RottenTomatoes();
  const imdbApi = new IMDBRadarrProxy();

  try {
    const movie = await tmdb.getMovie({
      movieId: Number(req.params.id),
    });

    const rtratings = await rtapi.getMovieRatings(
      movie.title,
      Number(movie.release_date.slice(0, 4))
    );

    let imdbRatings;
    if (movie.imdb_id) {
      imdbRatings = await imdbApi.getMovieRatings(movie.imdb_id);
    }

    if (!rtratings && !imdbRatings) {
      return next({
        status: 404,
        message: 'No ratings found.',
      });
    }

    const ratings: RatingResponse = {
      ...(rtratings ? { rt: rtratings } : {}),
      ...(imdbRatings ? { imdb: imdbRatings } : {}),
    };

    return res.status(200).json(ratings);
  } catch (e) {
    logger.debug('Something went wrong retrieving movie ratings', {
      label: 'API',
      errorMessage: e.message,
      movieId: req.params.id,
    });
    return next({
      status: 500,
      message: 'Unable to retrieve movie ratings.',
    });
  }
});

export default movieRoutes;
