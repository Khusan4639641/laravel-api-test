import { API_BASE_URL, endpoints } from './endpoints';

export { API_BASE_URL, endpoints };

export const TOKEN_STORAGE_KEY = 'safi_token';

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface ApiRequestOptions {
  method?: ApiMethod;
  body?: Record<string, unknown> | FormData | null;
  auth?: boolean;
  headers?: Record<string, string>;
  redirectOnUnauthorized?: boolean;
}

export interface ApiLoadingState<T = unknown> {
  data: T | null;
  isLoading: boolean;
  error: string | null;
  validationErrors?: Record<string, string[]>;
}

export interface LoginPayload {
  login?: string;
  email?: string;
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

export interface Product {
  id: string;
  name: string;
  category: string;
  shortDescription: string;
  description: string;
  benefits: string[];
  composition: string[];
  usage: string;
  price: number;
  pv: number;
  image: string;
  stock?: number;
  status?: string;
  createdAt?: string;
}

export interface Package {
  id: string;
  name: string;
  price: number;
  referralBonus: number;
  binaryBonus: number | null;
  features: string[];
  isPopular?: boolean;
  sortOrder?: number;
}

export interface Status {
  id: string;
  name: string;
  pv: number;
  incomePotential: number;
  reward: string;
  isCashBonus: boolean;
  partnersCount?: number;
}

export interface NewsArticle {
  id: string;
  title: string;
  date: string;
  content: string;
  imageUrl?: string;
  category: string;
}

export interface FaqCategory {
  category: string;
  questions: Array<{ q: string; a: string }>;
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
  validationErrors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.validationErrors = errors;
  }
}

export function getAuthToken() {
  if (typeof window === 'undefined') {
    return null;
  }

  return window.localStorage.getItem(TOKEN_STORAGE_KEY);
}

export function setAuthToken(token: string) {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
}

export function clearAuthToken() {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.removeItem(TOKEN_STORAGE_KEY);
}

export function createApiLoadingState<T>(data: T | null = null): ApiLoadingState<T> {
  return {
    data,
    isLoading: false,
    error: null,
  };
}

export function getApiErrorState(error: unknown): Pick<ApiLoadingState, 'error' | 'validationErrors'> {
  if (error instanceof ApiError) {
    return {
      error: error.message,
      validationErrors: error.validationErrors,
    };
  }

  return {
    error: 'Не удалось выполнить запрос. Попробуйте позже.',
  };
}

export async function withApiLoadingState<T>(
  request: () => Promise<T>,
  setState: (state: ApiLoadingState<T>) => void,
  initialData: T | null = null
) {
  setState({
    data: initialData,
    isLoading: true,
    error: null,
  });

  try {
    const data = await request();

    setState({
      data,
      isLoading: false,
      error: null,
    });

    return data;
  } catch (error) {
    setState({
      data: initialData,
      isLoading: false,
      ...getApiErrorState(error),
    });

    throw error;
  }
}

export async function login(payload: LoginPayload) {
  const response = await apiRequest<AuthResponse>(endpoints.auth.login, {
    method: 'POST',
    body: normalizeLoginPayload(payload),
    auth: false,
  });

  persistTokenFromResponse(response);

  return response;
}

export async function register(payload: RegisterPayload) {
  const response = await apiRequest<AuthResponse>(endpoints.auth.register, {
    method: 'POST',
    body: compactPayload(payload),
    auth: false,
  });

  persistTokenFromResponse(response);

  return response;
}

export async function logout(redirectTo = '/login') {
  try {
    await apiRequest(endpoints.auth.logout, {
      method: 'POST',
      auth: true,
    });
  } finally {
    clearAuthToken();
    redirectToPath(redirectTo);
  }
}

export async function me<T = unknown>() {
  return apiRequest<T>(endpoints.auth.me, {
    method: 'GET',
    auth: true,
  });
}

export async function getPublicProducts() {
  const response = await apiRequest(endpoints.public.products, { method: 'GET', auth: false });
  return normalizeProducts(response);
}

export async function getPublicPackages() {
  const response = await apiRequest(endpoints.public.packages, { method: 'GET', auth: false });
  return normalizePackages(response);
}

export async function getPublicNews() {
  const response = await apiRequest(endpoints.public.news, { method: 'GET', auth: false });
  return normalizeNews(response);
}

export async function getPublicFaqs() {
  const response = await apiRequest(endpoints.public.faqs, { method: 'GET', auth: false });
  return normalizeFaqs(response);
}

export async function getPublicStatuses() {
  const response = await apiRequest(endpoints.public.statuses, { method: 'GET', auth: false });
  return normalizeStatuses(response);
}

export async function getProducts<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.productCatalog, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardProfile<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.profile, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardOverview<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.overview, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardStructure<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.structure, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardTransactions<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.transactions, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardBonuses<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.bonuses, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardPackages() {
  const response = await apiRequest(endpoints.dashboard.packages, {
    method: 'GET',
    auth: true,
  });
  return normalizePackages(response);
}

export async function getDashboardProducts() {
  const response = await apiRequest(endpoints.dashboard.products, {
    method: 'GET',
    auth: true,
  });
  return normalizeProducts(response);
}

export async function getDashboardWithdrawals<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.withdrawals, {
    method: 'GET',
    auth: true,
  });
}

export async function createDashboardWithdrawal<T = unknown>(payload: WithdrawalPayload) {
  return apiRequest<T>(endpoints.dashboard.withdrawals, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function getDashboardSupportTickets<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.supportTickets, {
    method: 'GET',
    auth: true,
  });
}

export async function createDashboardSupportTicket<T = unknown>(payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.dashboard.supportTickets, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function getOrders<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.orderCheckout, {
    method: 'GET',
    auth: true,
  });
}

export async function createOrder<T = unknown>(payload: OrderPayload) {
  return apiRequest<T>(endpoints.dashboard.orderCheckout, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function getDashboardOrders<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.orders, {
    method: 'GET',
    auth: true,
  });
}

export async function getWithdrawals<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.withdrawalRequests, {
    method: 'GET',
    auth: true,
  });
}

export async function createWithdrawal<T = unknown>(payload: WithdrawalPayload) {
  return apiRequest<T>(endpoints.dashboard.withdrawalRequests, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function activatePackage<T = unknown>(packageId: string | number) {
  return apiRequest<T>(endpoints.dashboard.activatePackage(packageId), {
    method: 'POST',
    auth: true,
  });
}

export async function upgradePackage<T = unknown>(packageId: string | number) {
  return apiRequest<T>(endpoints.dashboard.upgradePackage(packageId), {
    method: 'POST',
    auth: true,
  });
}

export async function calculateBinaryBonus<T = unknown>(payload: BinaryCalculationPayload) {
  return apiRequest<T>(endpoints.dashboard.calculateBinaryBonus, {
    method: 'POST',
    body: compactPayload(payload),
    auth: true,
  });
}

export async function getAdminOverview<T = unknown>() {
  return apiRequest<T>(endpoints.admin.overview, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminStructure<T = unknown>() {
  return apiRequest<T>(endpoints.admin.structure, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminUsers<T = unknown>() {
  return apiRequest<T>(endpoints.admin.users, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminUser<T = unknown>(userId: string | number) {
  return apiRequest<T>(endpoints.admin.user(userId), {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminProducts<T = unknown>() {
  return apiRequest<T>(endpoints.admin.products, {
    method: 'GET',
    auth: true,
  });
}

export async function createAdminProduct<T = unknown>(payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.products, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function updateAdminProduct<T = unknown>(productId: string | number, payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.product(productId), {
    method: 'PUT',
    body: payload,
    auth: true,
  });
}

export async function deleteAdminProduct<T = unknown>(productId: string | number) {
  return apiRequest<T>(endpoints.admin.product(productId), {
    method: 'DELETE',
    auth: true,
  });
}

export async function getAdminPackages() {
  const response = await apiRequest(endpoints.admin.packages, {
    method: 'GET',
    auth: true,
  });
  return normalizePackages(response);
}

export async function createAdminPackage<T = unknown>(payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.packages, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function updateAdminPackage<T = unknown>(packageId: string | number, payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.package(packageId), {
    method: 'PUT',
    body: payload,
    auth: true,
  });
}

export async function getAdminOrders<T = unknown>() {
  return apiRequest<T>(endpoints.admin.orders, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminTransactions<T = unknown>() {
  return apiRequest<T>(endpoints.admin.transactions, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminBonuses<T = unknown>() {
  return apiRequest<T>(endpoints.admin.bonuses, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminWithdrawals<T = unknown>() {
  return apiRequest<T>(endpoints.admin.withdrawals, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminNews() {
  const response = await apiRequest(endpoints.admin.news, {
    method: 'GET',
    auth: true,
  });
  return normalizeNews(response);
}

export async function createAdminNews<T = unknown>(payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.news, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function updateAdminNews<T = unknown>(newsId: string | number, payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.newsItem(newsId), {
    method: 'PUT',
    body: payload,
    auth: true,
  });
}

export async function deleteAdminNews<T = unknown>(newsId: string | number) {
  return apiRequest<T>(endpoints.admin.newsItem(newsId), {
    method: 'DELETE',
    auth: true,
  });
}

export async function getAdminFaqs() {
  const response = await apiRequest(endpoints.admin.faqs, {
    method: 'GET',
    auth: true,
  });
  return normalizeFaqs(response);
}

export async function createAdminFaq<T = unknown>(payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.faqs, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function updateAdminFaq<T = unknown>(faqId: string | number, payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.faq(faqId), {
    method: 'PUT',
    body: payload,
    auth: true,
  });
}

export async function deleteAdminFaq<T = unknown>(faqId: string | number) {
  return apiRequest<T>(endpoints.admin.faq(faqId), {
    method: 'DELETE',
    auth: true,
  });
}

export async function getAdminSupportTickets<T = unknown>() {
  return apiRequest<T>(endpoints.admin.supportTickets, {
    method: 'GET',
    auth: true,
  });
}

export async function replyAdminSupportTicket<T = unknown>(ticketId: string | number, reply: string) {
  return apiRequest<T>(endpoints.admin.replySupportTicket(ticketId), {
    method: 'PATCH',
    body: { reply },
    auth: true,
  });
}

export async function closeAdminSupportTicket<T = unknown>(ticketId: string | number) {
  return apiRequest<T>(endpoints.admin.closeSupportTicket(ticketId), {
    method: 'PATCH',
    auth: true,
  });
}

export async function getAdminReportsSummary<T = unknown>() {
  return apiRequest<T>(endpoints.admin.reportsSummary, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminSettings<T = unknown>() {
  return apiRequest<T>(endpoints.admin.settings, {
    method: 'GET',
    auth: true,
  });
}

export async function updateAdminSettings<T = unknown>(settings: Record<string, unknown>) {
  return apiRequest<T>(endpoints.admin.settings, {
    method: 'PUT',
    body: { settings },
    auth: true,
  });
}

export async function getAdminStatuses() {
  const response = await apiRequest(endpoints.admin.statuses, {
    method: 'GET',
    auth: true,
  });
  return normalizeStatuses(response);
}

export async function approveAdminWithdrawal<T = unknown>(withdrawalId: string | number) {
  return apiRequest<T>(endpoints.admin.approveWithdrawal(withdrawalId), {
    method: 'PATCH',
    auth: true,
  });
}

export async function rejectAdminWithdrawal<T = unknown>(withdrawalId: string | number) {
  return apiRequest<T>(endpoints.admin.rejectWithdrawal(withdrawalId), {
    method: 'PATCH',
    auth: true,
  });
}

export async function apiRequest<T = unknown>(endpoint: string, options: ApiRequestOptions = {}): Promise<T> {
  const token = getAuthToken();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...options.headers,
  };
  const body = serializeRequestBody(options.body);

  if (options.auth !== false && token) {
    headers.Authorization = `Bearer ${token}`;
  }

  if (body !== undefined && !isFormData(options.body)) {
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
  }

  const response = await fetch(buildApiUrl(endpoint), {
    method: options.method || 'GET',
    headers,
    credentials: 'same-origin',
    body,
  });

  const data = await parseResponse(response);

  if (response.status === 401 && options.auth !== false) {
    clearAuthToken();

    if (options.redirectOnUnauthorized !== false) {
      redirectToPath('/login');
    }
  }

  if (!response.ok) {
    const errors = getValidationErrors(data);
    throw new ApiError(getErrorMessage(data, response.status), response.status, errors);
  }

  return data as T;
}

function buildApiUrl(endpoint: string) {
  if (/^https?:\/\//i.test(endpoint)) {
    return endpoint;
  }

  const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

  if (normalizedEndpoint === API_BASE_URL || normalizedEndpoint.startsWith(`${API_BASE_URL}/`)) {
    return normalizedEndpoint;
  }

  return `${API_BASE_URL}${normalizedEndpoint}`;
}

function serializeRequestBody(body: ApiRequestOptions['body']) {
  if (!body) {
    return undefined;
  }

  if (isFormData(body)) {
    return body;
  }

  return JSON.stringify(body);
}

function isFormData(body: ApiRequestOptions['body']): body is FormData {
  return typeof FormData !== 'undefined' && body instanceof FormData;
}

function redirectToPath(path: string) {
  if (typeof window === 'undefined') {
    return;
  }

  if (window.location.pathname === path) {
    return;
  }

  window.location.assign(path);
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

function normalizeLoginPayload(payload: LoginPayload) {
  const identifier = (payload.email || payload.login || '').trim();
  const identifierKey = identifier.includes('@') ? 'email' : 'login';

  return {
    [identifierKey]: identifier,
    password: payload.password,
  };
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

export function getArray(response: unknown, keys: string[] = []) {
  if (Array.isArray(response)) {
    return response;
  }

  if (isRecord(response)) {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (isRecord(response.data) && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    for (const key of keys) {
      if (Array.isArray(response[key])) {
        return response[key] as unknown[];
      }
    }
  }

  return [];
}

export function unwrapRecord(response: unknown, keys: string[] = []) {
  if (!isRecord(response)) {
    return {};
  }

  for (const key of keys) {
    if (isRecord(response[key])) {
      return response[key] as Record<string, unknown>;
    }
  }

  if (isRecord(response.data)) {
    return response.data;
  }

  return response;
}

export function normalizeProducts(response: unknown): Product[] {
  return getArray(response, ['products']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const metadata = isRecord(record.metadata) ? record.metadata : {};

    return {
      id: getString(record, ['id', 'uuid']) || String(index + 1),
      name: getString(record, ['name', 'title']) || `Safi Product ${index + 1}`,
      category: getString(record, ['category']) || getString(metadata, ['category']) || 'Safi Life',
      shortDescription: getString(record, ['shortDescription', 'short_description']) || getString(metadata, ['short_description', 'shortDescription']) || getString(record, ['description']) || '',
      description: getString(record, ['description']) || '',
      benefits: getStringArray(record, ['benefits']) || getStringArray(metadata, ['benefits']) || [],
      composition: getStringArray(record, ['composition']) || getStringArray(metadata, ['composition']) || [],
      usage: getString(record, ['usage']) || getString(metadata, ['usage']) || '',
      price: getNumber(record, ['price', 'amount']) ?? 0,
      pv: getNumber(record, ['pv', 'points']) ?? 0,
      image: getString(record, ['image', 'image_url', 'imageUrl']) || getString(metadata, ['image', 'image_url', 'imageUrl']) || 'https://images.unsplash.com/photo-1584362917165-526a968579e8?auto=format&fit=crop&q=80&w=400&h=400',
      stock: getNumber(record, ['stock', 'stock_quantity']) ?? undefined,
      status: getString(record, ['status']),
      createdAt: getString(record, ['created_at', 'createdAt']),
    };
  });
}

export function normalizePackages(response: unknown): Package[] {
  return getArray(response, ['packages']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const code = getString(record, ['code', 'slug', 'id']) || String(index + 1);
    const name = getString(record, ['name', 'title', 'code']) || code.toUpperCase();

    return {
      id: getString(record, ['id']) || code.toLowerCase(),
      name,
      price: getNumber(record, ['price']) ?? 0,
      referralBonus: getNumber(record, ['referralBonus', 'referral_percent']) ?? 0,
      binaryBonus: getNumber(record, ['binaryBonus', 'binary_percent']) ?? null,
      features: getStringArray(record, ['features']) || [
        'Доступ ко всем продуктам',
        'Личный кабинет',
        'Реферальная ссылка',
        'Обучающие материалы',
      ],
      isPopular: name.toLowerCase() === 'vip',
      sortOrder: getNumber(record, ['sort_order', 'sortOrder']) ?? index,
    };
  });
}

export function normalizeNews(response: unknown): NewsArticle[] {
  return getArray(response, ['news']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const date = getString(record, ['published_at', 'created_at', 'date']) || '';

    return {
      id: getString(record, ['id']) || String(index + 1),
      title: getString(record, ['title']) || `News ${index + 1}`,
      date: date ? new Date(date).toLocaleDateString('ru-RU') : '',
      content: getString(record, ['content', 'excerpt']) || '',
      imageUrl: getString(record, ['imageUrl', 'image_url']),
      category: getString(record, ['category']) || 'Новости',
    };
  });
}

export function normalizeFaqs(response: unknown): FaqCategory[] {
  const grouped = new Map<string, Array<{ q: string; a: string }>>();

  getArray(response, ['faqs']).forEach((item) => {
    const record = isRecord(item) ? item : {};
    const category = getString(record, ['category']) || 'FAQ';
    const question = getString(record, ['question', 'q']) || '';
    const answer = getString(record, ['answer', 'a']) || '';

    if (!grouped.has(category)) {
      grouped.set(category, []);
    }

    grouped.get(category)?.push({ q: question, a: answer });
  });

  return Array.from(grouped.entries()).map(([category, questions]) => ({ category, questions }));
}

export function normalizeStatuses(response: unknown): Status[] {
  return getArray(response, ['statuses']).map((item, index) => {
    const record = isRecord(item) ? item : {};

    return {
      id: getString(record, ['id', 'code']) || String(index + 1),
      name: getString(record, ['name', 'title']) || `Status ${index + 1}`,
      pv: getNumber(record, ['pv', 'threshold']) ?? 0,
      incomePotential: getNumber(record, ['incomePotential', 'income_potential']) ?? 0,
      reward: getString(record, ['reward']) || '',
      isCashBonus: Boolean(record.isCashBonus ?? record.is_cash_bonus),
      partnersCount: getNumber(record, ['partnersCount', 'partners_count']) ?? undefined,
    };
  });
}

export function getString(record: Record<string, unknown> | undefined, keys: string[]) {
  if (!record) {
    return undefined;
  }

  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }

    if (typeof value === 'number') {
      return String(value);
    }
  }

  return undefined;
}

export function getNumber(record: Record<string, unknown> | undefined, keys: string[]) {
  if (!record) {
    return undefined;
  }

  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string') {
      const normalized = Number(value.replace(/[^\d.-]/g, ''));

      if (Number.isFinite(normalized)) {
        return normalized;
      }
    }
  }

  return undefined;
}

export function getStringArray(record: Record<string, unknown> | undefined, keys: string[]) {
  if (!record) {
    return undefined;
  }

  for (const key of keys) {
    const value = record[key];

    if (Array.isArray(value)) {
      return value.map(String);
    }
  }

  return undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
