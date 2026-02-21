<?php

require __DIR__ . '/Medoo.php';
require __DIR__ . '/config.php';
require __DIR__ . '/plfunctions.php';

header('Content-Type: application/json');

function sync_response($status, $payload) {
  http_response_code($status);
  echo json_encode($payload);
  exit();
}

function get_authorization_header_value() {
  if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    return trim($_SERVER['HTTP_AUTHORIZATION']);
  }
  if (isset($_SERVER['Authorization'])) {
    return trim($_SERVER['Authorization']);
  }
  if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
      return trim($headers['Authorization']);
    }
    if (isset($headers['authorization'])) {
      return trim($headers['authorization']);
    }
  }
  return '';
}

function parse_bearer_token($headerValue) {
  if (!$headerValue) {
    return '';
  }
  if (stripos($headerValue, 'Bearer ') !== 0) {
    return '';
  }
  return trim(substr($headerValue, 7));
}

function normalize_media_type($raw) {
  $raw = strtolower(trim((string)$raw));
  if ($raw === 'movie') {
    return 'movie';
  }
  if ($raw === 'tv' || $raw === 'series') {
    return 'series';
  }
  return '';
}

function safe_category($value, $defaultCategory) {
  $value = trim((string)$value);
  if ($value === '') {
    return $defaultCategory;
  }
  if (strlen($value) > 64) {
    $value = substr($value, 0, 64);
  }
  return $value;
}

function sqlite_upsert_row($sqlite, $record) {
  $now = time();
  $stmt = $sqlite->prepare('
    INSERT INTO user_mylist
    (user_id, username, media_type, internal_id, title, category, watched, tmdb_id, imdb_id, tvdb_id, source_event, metadata_json, added_at, updated_at, removed_at)
    VALUES
    (:user_id, :username, :media_type, :internal_id, :title, :category, :watched, :tmdb_id, :imdb_id, :tvdb_id, :source_event, :metadata_json, :added_at, :updated_at, NULL)
    ON CONFLICT(user_id, media_type, internal_id, category)
    DO UPDATE SET
      title = excluded.title,
      watched = excluded.watched,
      tmdb_id = excluded.tmdb_id,
      imdb_id = excluded.imdb_id,
      tvdb_id = excluded.tvdb_id,
      source_event = excluded.source_event,
      metadata_json = excluded.metadata_json,
      updated_at = excluded.updated_at,
      removed_at = NULL
  ');

  if (!$stmt) {
    return false;
  }

  $stmt->bindValue(':user_id', (int)$record['user_id'], SQLITE3_INTEGER);
  $stmt->bindValue(':username', (string)$record['username'], SQLITE3_TEXT);
  $stmt->bindValue(':media_type', (string)$record['media_type'], SQLITE3_TEXT);
  $stmt->bindValue(':internal_id', (int)$record['internal_id'], SQLITE3_INTEGER);
  $stmt->bindValue(':title', (string)$record['title'], SQLITE3_TEXT);
  $stmt->bindValue(':category', (string)$record['category'], SQLITE3_TEXT);
  $stmt->bindValue(':watched', $record['watched'] ? 1 : 0, SQLITE3_INTEGER);
  $stmt->bindValue(':tmdb_id', (string)$record['tmdb_id'], SQLITE3_TEXT);
  $stmt->bindValue(':imdb_id', (string)$record['imdb_id'], SQLITE3_TEXT);
  $stmt->bindValue(':tvdb_id', (string)$record['tvdb_id'], SQLITE3_TEXT);
  $stmt->bindValue(':source_event', (string)$record['source_event'], SQLITE3_TEXT);
  $stmt->bindValue(':metadata_json', (string)$record['metadata_json'], SQLITE3_TEXT);
  $stmt->bindValue(':added_at', $now, SQLITE3_INTEGER);
  $stmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);

  $ok = $stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

function sqlite_soft_remove_row($sqlite, $record) {
  $stmt = $sqlite->prepare('
    UPDATE user_mylist
    SET removed_at = :removed_at, updated_at = :updated_at, source_event = :source_event
    WHERE user_id = :user_id
      AND media_type = :media_type
      AND internal_id = :internal_id
      AND category = :category
      AND removed_at IS NULL
  ');

  if (!$stmt) {
    return false;
  }

  $now = time();
  $stmt->bindValue(':removed_at', $now, SQLITE3_INTEGER);
  $stmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
  $stmt->bindValue(':source_event', (string)$record['source_event'], SQLITE3_TEXT);
  $stmt->bindValue(':user_id', (int)$record['user_id'], SQLITE3_INTEGER);
  $stmt->bindValue(':media_type', (string)$record['media_type'], SQLITE3_TEXT);
  $stmt->bindValue(':internal_id', (int)$record['internal_id'], SQLITE3_INTEGER);
  $stmt->bindValue(':category', (string)$record['category'], SQLITE3_TEXT);

  $ok = $stmt->execute();
  $changes = $sqlite->changes();
  $stmt->close();

  return $ok && $changes >= 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  sync_response(405, array('ok' => false, 'message' => 'Method not allowed'));
}

$config = file_exists(__DIR__ . '/sportshub_sync_config.php')
  ? require __DIR__ . '/sportshub_sync_config.php'
  : array();

$expectedToken = getenv('SPORTSHUB_SYNC_TOKEN');
if (!$expectedToken && isset($config['sync_token'])) {
  $expectedToken = $config['sync_token'];
}

if (!$expectedToken || $expectedToken === 'CHANGE_ME_TO_A_LONG_RANDOM_TOKEN') {
  sync_response(500, array('ok' => false, 'message' => 'Sync token not configured'));
}

$authHeader = get_authorization_header_value();
$providedToken = parse_bearer_token($authHeader);
if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
  sync_response(401, array('ok' => false, 'message' => 'Unauthorized'));
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
  sync_response(400, array('ok' => false, 'message' => 'Invalid JSON payload'));
}

$allowedEvents = array('watchlist_added', 'watchlist_removed', 'watched_toggled', 'request_created');
$event = isset($payload['event']) ? (string)$payload['event'] : '';
if (!in_array($event, $allowedEvents, true)) {
  sync_response(400, array('ok' => false, 'message' => 'Unsupported event'));
}

$requestedBy = isset($payload['requestedBy']) && is_array($payload['requestedBy'])
  ? $payload['requestedBy']
  : array();
$username = isset($requestedBy['username']) ? trim((string)$requestedBy['username']) : '';
if ($username === '') {
  sync_response(400, array('ok' => false, 'message' => 'requestedBy.username is required'));
}

$db = new \Medoo\Medoo(array(
  'database_type' => 'mysql',
  'database_name' => $stalker_db,
  'server' => $stalker_host,
  'username' => $stalker_dbuser,
  'password' => $stalker_dbpass,
  'charset' => 'utf8',
));

$plf = new plfunctions($db);
$userRow = $plf->getUserRecordByLogin($username);
if (!$userRow) {
  sync_response(404, array('ok' => false, 'message' => 'User not found'));
}

if (!$plf->isUserActiveAndNotExpired($userRow)) {
  sync_response(403, array('ok' => false, 'message' => 'User inactive or expired'));
}

$mediaType = normalize_media_type(isset($payload['mediaType']) ? $payload['mediaType'] : '');
if ($mediaType === '') {
  sync_response(400, array('ok' => false, 'message' => 'mediaType must be movie or tv'));
}

$defaultCategory = isset($config['default_category']) ? (string)$config['default_category'] : 'tracked';
$category = safe_category(isset($payload['category']) ? $payload['category'] : '', $defaultCategory);

$externalIds = isset($payload['externalIds']) && is_array($payload['externalIds'])
  ? $payload['externalIds']
  : array();

$tmdbId = '';
if (isset($externalIds['tmdbId'])) {
  $tmdbId = (string)$externalIds['tmdbId'];
} else if (isset($payload['tmdbId'])) {
  $tmdbId = (string)$payload['tmdbId'];
}
$imdbId = isset($externalIds['imdbId']) ? trim((string)$externalIds['imdbId']) : '';
$tvdbId = isset($externalIds['tvdbId']) ? (string)$externalIds['tvdbId'] : '';

$internalId = null;
if ($mediaType === 'movie') {
  $internalId = $plf->resolvePVODMovieIdByExternalIds($imdbId, $tmdbId, $tvdbId);
} else {
  $internalId = $plf->resolvePVODSeriesIdByExternalIds($imdbId, $tmdbId, $tvdbId);
}

if (!$internalId) {
  sync_response(404, array(
    'ok' => false,
    'message' => 'Content not found by external IDs',
    'mediaType' => $mediaType,
    'externalIds' => array(
      'tmdbId' => $tmdbId,
      'imdbId' => $imdbId,
      'tvdbId' => $tvdbId,
    ),
  ));
}

$sqlite = $plf->openSportsHubSyncDb();
if (!$sqlite) {
  sync_response(500, array('ok' => false, 'message' => 'SQLite extension not available'));
}

$plf->ensureSportsHubSyncSchema($sqlite);

$watched = false;
if (isset($payload['watched'])) {
  $watched = (bool)$payload['watched'];
}
if ($event === 'request_created') {
  $watched = false;
}

$record = array(
  'user_id' => (int)$userRow['id'],
  'username' => $username,
  'media_type' => $mediaType,
  'internal_id' => (int)$internalId,
  'title' => isset($payload['title']) ? trim((string)$payload['title']) : '',
  'category' => $category,
  'watched' => $watched,
  'tmdb_id' => $tmdbId,
  'imdb_id' => $imdbId,
  'tvdb_id' => $tvdbId,
  'source_event' => $event,
  'metadata_json' => isset($payload['metadata']) ? json_encode($payload['metadata']) : '{}',
);

if ($event === 'watchlist_removed') {
  $ok = sqlite_soft_remove_row($sqlite, $record);
  $sqlite->close();
  if (!$ok) {
    sync_response(500, array('ok' => false, 'message' => 'Failed to remove row'));
  }
  sync_response(200, array(
    'ok' => true,
    'action' => 'removed',
    'user' => $username,
    'mediaType' => $mediaType,
    'internalId' => (int)$internalId,
    'category' => $category,
  ));
}

$ok = sqlite_upsert_row($sqlite, $record);
$sqlite->close();
if (!$ok) {
  sync_response(500, array('ok' => false, 'message' => 'Failed to upsert row'));
}

sync_response(200, array(
  'ok' => true,
  'action' => 'upserted',
  'event' => $event,
  'user' => $username,
  'mediaType' => $mediaType,
  'internalId' => (int)$internalId,
  'category' => $category,
  'watched' => (bool)$watched,
));

