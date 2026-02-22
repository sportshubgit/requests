# Carrot API Prod Copy

This folder is a redundant, production-oriented copy.  
Original files in parent folder were not edited.

## Files
- `player_api.php` (patched: virtual user categories)
- `plfunctions.php` (patched: user list SQLite store + resolvers)
- `sportshub_sync.php` (new: receives SportsHub events)
- `sportshub_sync_config.php` (new: token/default category)

## What this does
- Creates two virtual categories per logged-in user:
  - `999999991` = `My List` (movies)
  - `999999992` = `My Series` (series)
- Adds smart watched splits:
  - `999999995` = `My List - Watched` (movies)
  - `999999996` = `My List - Unwatched` (movies)
  - `999999997` = `My Series - Watched` (series)
  - `999999998` = `My Series - Unwatched` (series)
- SportsHub posts events to `sportshub_sync.php`.
- Sync writes **user-scoped** rows to `storage/sportshub_sync.db`.
- `player_api.php` merges those rows at read-time and serves only that user's items.
- Sync lookup order:
  1) external IDs (`imdbId`, `tmdbId`, `tvdbId`)
  2) strict title/year fallback (for sources where IDs differ)

## Production wiring
1. Point `request.brb.ac` (SportsHub/Seerr) tracked sync URL to:
   - `https://carrot.brb.ac/sportshub_sync.php`
2. Set SportsHub tracked sync API key to match:
   - `sportshub_sync_config.php` -> `sync_token`
3. Ensure `carrot.brb.ac` docroot is this folder.
4. Ensure writable directory exists:
   - `storage/`
5. Ensure PHP has `sqlite3` enabled.

## Required local files
This copy expects:
- `config.php`
- `Medoo.php`

Copy those from your existing working API directory into this folder before go-live.

## Quick checks
### Syntax
```bash
php -l player_api.php
php -l plfunctions.php
php -l sportshub_sync.php
```

### Sync endpoint test
```bash
curl -sS -X POST "https://carrot.brb.ac/sportshub_sync.php" \
  -H "Authorization: Bearer CHANGE_ME_TO_A_LONG_RANDOM_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event":"watchlist_added",
    "category":"tracked",
    "mediaType":"movie",
    "title":"Example Movie",
    "requestedBy":{"username":"ryanmortimer"},
    "externalIds":{"imdbId":"tt0111161","tmdbId":278}
  }'
```

### Player API test
```bash
curl -sS "https://carrot.brb.ac/player_api.php?username=YOURUSER&password=YOURPASS&action=get_vod_categories"
curl -sS "https://carrot.brb.ac/player_api.php?username=YOURUSER&password=YOURPASS&action=get_vod_streams&category_id=999999991"
```
