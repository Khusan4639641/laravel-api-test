export const TOKEN_STORAGE_KEY = 'safi_token';

type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface LoginPayload {
  login: string;
  password: string;
}

export interface RegisterPayload {
  name: string;
  login: string;
  email: string;
  password: string;
  password_confirmation: string;
  sponsor_id?: string;
  branch?: string;
  package_id?: string;
}

export interface OrderPayload {
  product_id: string | number;
  quantity?: number;
  [key: string]: unknown;
}

export interface WithdrawalPayload {
  amount: number;
  method?: string;
  [key: string]: unknown;
}

export interface BinaryCalculationPayload {
  left_volume?: number;
  right_volume?: number;
  package_id?: string | number;
  [key: string]: unknown;
}

export interface AuthResponse {
  token?: string;
  access_token?: string;
  data?: {
    token?: string;
    access_token?: string;
    [key: string]: unknown;
  };
  user?: unknown;
  [key: string]: unknown;
}

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

export function getAuthToken() {
  return window.localStorage.getItem(TOKEN_STORAGE_KEY);
}

export function setAuthToken(token: string) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
}

export function clearAuthToken() {
  window.localStorage.removeItem(TOKEN_STORAGE_KEY);
}

export async function login(payload: LoginPayload) {
  const response = await apiRequest<AuthResponse>('/api/login', {
    method: 'POST',
    body: payload,
    auth: false,
  });

  persistTokenFromResponse(response);

  return response;
}

export async function register(payload: RegisterPayload) {
  const response = await apiRequest<AuthResponse>('/api/register', {
    method: 'POST',
    body: compactPayload(payload),
    auth: false,
  });

  persistTokenFromResponse(response);

  return response;
}

export async function logout(redirectTo = '/login') {
  try {
    await apiRequest('/api/logout', {
      method: 'POST',
      auth: true,
    });
  } finally {
    clearAuthToken();
    window.location.assign(redirectTo);
  }
}

export async function me<T = unknown>() {
  return apiRequest<T>('/api/me', {
    method: 'GET',
    auth: true,
  });
}

export async function getProducts<T = unknown>() {
  return apiRequest<T>('/api/products', {
    method: 'GET',
    auth: true,
  });
}

export async function getOrders<T = unknown>() {
  return apiRequest<T>('/api/orders', {
    method: 'GET',
    auth: true,
  });
}

export async function createOrder<T = unknown>(payload: OrderPayload) {
  return apiRequest<T>('/api/orders', {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function getWithdrawals<T = unknown>() {
  return apiRequest<T>('/api/withdrawals', {
    method: 'GET',
    auth: true,
  });
}

export async function createWithdrawal<T = unknown>(payload: WithdrawalPayload) {
  return apiRequest<T>('/api/withdrawals', {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function activatePackage<T = unknown>(packageId: string | number) {
  return apiRequest<T>(`/api/packages/${encodeURIComponent(packageId)}/activate`, {
    method: 'POST',
    auth: true,
  });
}

export async function upgradePackage<T = unknown>(packageId: string | number) {
  return apiRequest<T>(`/api/packages/${encodeURIComponent(packageId)}/upgrade`, {
    method: 'POST',
    auth: true,
  });
}

export async function calculateBinaryBonus<T = unknown>(payload: BinaryCalculationPayload) {
  return apiRequest<T>('/api/bonuses/binary/calculate', {
    method: 'POST',
    body: compactPayload(payload),
    auth: true,
  });
}

export async function getAdminUsers<T = unknown>() {
  return apiRequest<T>('/api/admin/users', {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminWithdrawals<T = unknown>() {
  return apiRequest<T>('/api/admin/withdrawals', {
    method: 'GET',
    auth: true,
  });
}

export async function approveAdminWithdrawal<T = unknown>(withdrawalId: string | number) {
  return apiRequest<T>(`/api/admin/withdrawals/${encodeURIComponent(withdrawalId)}/approve`, {
    method: 'PATCH',
    auth: true,
  });
}

export async function rejectAdminWithdrawal<T = unknown>(withdrawalId: string | number) {
  return apiRequest<T>(`/api/admin/withdrawals/${encodeURIComponent(withdrawalId)}/reject`, {
    method: 'PATCH',
    auth: true,
  });
}

async function apiRequest<T = unknown>(
  endpoint: string,
  options: {
    method?: ApiMethod;
    body?: Record<string, unknown>;
    auth?: boolean;
  } = {}
): Promise<T> {
  const token = getAuthToken();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (options.auth !== false && token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(endpoint, {
    method: options.method || 'GET',
    headers,
    credentials: 'same-origin',
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const data = await parseResponse(response);

  if (!response.ok) {
    const errors = getValidationErrors(data);
    throw new ApiError(getErrorMessage(data, response.status), response.status, errors);
  }

  return data as T;
}

async function parseResponse(response: Response) {
  const text = await response.text();

  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return { message: text };
  }
}

function persistTokenFromResponse(response: AuthResponse) {
  const token = extractToken(response);

  if (!token) {
    throw new ApiError('Сервер не вернул token авторизации.', 422);
  }

  setAuthToken(token);
}

function extractToken(response: AuthResponse) {
  return response.token || response.access_token || response.data?.token || response.data?.access_token;
}

function getErrorMessage(data: unknown, status: number) {
  if (isRecord(data)) {
    if (typeof data.message === 'string') {
      return data.message;
    }

    if (typeof data.error === 'string') {
      return data.error;
    }
  }

  if (status === 401) {
    return 'Неверный логин или пароль.';
  }

  if (status === 422) {
    return 'Проверьте данные формы.';
  }

  return 'Не удалось выполнить запрос. Попробуйте позже.';
}

function getValidationErrors(data: unknown) {
  if (isRecord(data) && isRecord(data.errors)) {
    return data.errors as Record<string, string[]>;
  }

  return undefined;
}

function compactPayload<T extends Record<string, unknown>>(payload: T) {
  return Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== '' && value !== undefined && value !== null)
  );
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
