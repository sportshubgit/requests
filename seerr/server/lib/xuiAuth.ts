export type ParsedXuiAuth = {
  isAuthenticated: boolean;
  status: string;
  username: string;
  expDateEpoch: number | null;
  isExpired: boolean;
};

export type XuiAuthVerdict = {
  ok: boolean;
  reason: 'ok' | 'invalid_auth' | 'inactive' | 'expired' | 'invalid_payload';
  parsed: ParsedXuiAuth;
};

const defaultParsed: ParsedXuiAuth = {
  isAuthenticated: false,
  status: '',
  username: '',
  expDateEpoch: null,
  isExpired: false,
};

export const parseXuiAuth = (payload: unknown): ParsedXuiAuth => {
  if (!payload || typeof payload !== 'object') {
    return defaultParsed;
  }

  const data = payload as Record<string, unknown>;
  const userInfo =
    data.user_info && typeof data.user_info === 'object'
      ? (data.user_info as Record<string, unknown>)
      : {};

  const username = String(userInfo.username ?? data.username ?? '').trim();
  const status = String(userInfo.status ?? data.status ?? '').toLowerCase();
  const isAuthenticated = Number(userInfo.auth ?? data.auth ?? 0) === 1;

  const rawExpDate = userInfo.exp_date ?? data.exp_date;
  const expDateNum =
    rawExpDate === undefined || rawExpDate === null || rawExpDate === ''
      ? null
      : Number(rawExpDate);

  const expDateEpoch = Number.isFinite(expDateNum ?? NaN) ? expDateNum : null;

  const isExpired =
    expDateEpoch !== null &&
    expDateEpoch > 0 &&
    expDateEpoch < Math.floor(Date.now() / 1000);

  return {
    isAuthenticated,
    status,
    username,
    expDateEpoch,
    isExpired,
  };
};

export const evaluateXuiAuth = (payload: unknown): XuiAuthVerdict => {
  const parsed = parseXuiAuth(payload);

  if (!payload || typeof payload !== 'object') {
    return { ok: false, reason: 'invalid_payload', parsed };
  }

  if (!parsed.isAuthenticated) {
    return { ok: false, reason: 'invalid_auth', parsed };
  }

  if (parsed.status !== 'active') {
    return { ok: false, reason: 'inactive', parsed };
  }

  if (parsed.isExpired) {
    return { ok: false, reason: 'expired', parsed };
  }

  return { ok: true, reason: 'ok', parsed };
};

export const getXuiAuthContext = (payload: unknown): Record<string, unknown> => {
  if (!payload || typeof payload !== 'object') {
    return { payloadType: typeof payload };
  }

  const data = payload as Record<string, unknown>;
  const userInfo =
    data.user_info && typeof data.user_info === 'object'
      ? (data.user_info as Record<string, unknown>)
      : {};

  return {
    topLevelAuth: data.auth ?? null,
    topLevelStatus: data.status ?? null,
    userInfoAuth: userInfo.auth ?? null,
    userInfoStatus: userInfo.status ?? null,
    userInfoExpDate: userInfo.exp_date ?? null,
    userInfoUsername: userInfo.username ?? null,
  };
};
