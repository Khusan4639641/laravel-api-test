import { API_BASE_URL, endpoints } from './endpoints';
import { getCurrentLanguage } from './language';
import {
  mlmStatusLabel,
  orderStatusLabel,
  packageLabel,
  productStatusLabel,
} from './systemLabels';

export { API_BASE_URL, endpoints };

export const TOKEN_STORAGE_KEY = 'safi_token';

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
export type ApiQueryParams = Record<string, string | number | boolean | null | undefined>;
export type JsonBody = object | unknown[] | string | number | boolean | null;
export type ApiBody = JsonBody | FormData | null | undefined;

export interface ApiRequestOptions {
  method?: ApiMethod;
  body?: ApiBody;
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
  phone: string;
  password: string;
  password_confirmation: string;
  sponsor_id?: string;
  referral_code?: string;
  ref?: string;
  sponsor_code?: string;
  branch?: string;
}

export interface AdminPartnerPayload {
  name: string;
  login: string;
  email: string;
  phone: string;
  password: string;
  password_confirmation: string;
  sponsor_id?: string;
  branch?: string;
  package_id?: string;
  pay_referral_bonus?: boolean;
  role?: string;
}

export interface AdminPartnerBulkPayload {
  partners: AdminPartnerPayload[];
}

export interface AdminPartnerBalancePayload {
  mode: 'set' | 'adjust';
  amount: number;
  comment?: string;
}

export interface AdminPartnerDeletePreview {
  user?: {
    id?: string | number;
    name?: string;
    email?: string;
  };
  can_delete_leaf?: boolean;
  has_children?: boolean;
  descendants_count?: number;
  affected_uplines_count?: number;
  transactions_count?: number;
  orders_count?: number;
  withdrawals_count?: number;
  wallet_balance?: string | number;
  pv_to_recalculate?: string | number;
  warning?: string;
}

export interface OrderPayload {
  product_id?: string | number;
  quantity?: number;
  items?: Array<{ product_id: string | number; quantity: number }>;
  payment_strategy?: 'card_100' | 'card_50_deposit_50' | 'deposit_100';
  recipient_name?: string;
  phone?: string;
  city?: string;
  delivery_address?: string;
  comment?: string;
  [key: string]: unknown;
}

export interface WithdrawalPayload {
  amount: number;
  method?: string;
  [key: string]: unknown;
}

export interface TransferPartner {
  id: number;
  name: string;
  login?: string;
  email?: string;
  phone?: string;
  package?: string;
  status?: string;
}

export interface PartnerTransferPayload {
  recipient_user_id: number;
  amount: number;
  comment?: string;
  idempotency_key?: string;
}

export interface InternalWalletTransferPayload {
  from: 'main';
  to: 'deposit';
  amount: number;
  comment?: string;
}

export interface PartnerTransfer {
  id: string;
  uuid?: string;
  senderId: number;
  recipientId: number;
  amount: number;
  currency: string;
  status: string;
  comment?: string;
  sender?: TransferPartner | null;
  recipient?: TransferPartner | null;
  createdAt?: string;
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
  imageUrl?: string;
  stock?: number;
  stockQuantity?: number;
  reservedQuantity?: number;
  inStock?: boolean;
  isDepositProduct?: boolean;
  isDepositOnly?: boolean;
  status?: string;
  statusLabel?: string;
  createdAt?: string;
}

export interface OrderItem {
  id: string;
  productId?: string;
  productName: string;
  quantity: number;
  unitPrice: number;
  unitPv: number;
  totalPrice: number;
  totalPv: number;
  image?: string;
  category?: string;
}

export interface OrderUser {
  id: string;
  name: string;
  login?: string;
  email?: string;
  phone?: string;
}

export interface Order {
  id: string;
  orderNumber?: string;
  userId?: string;
  user?: OrderUser | null;
  status: string;
  statusLabel?: string;
  paymentStatus?: string;
  paymentStatusLabel?: string;
  paymentProvider?: string;
  paymentExternalId?: string;
  paymentTransactionId?: string;
  paymentStrategy?: string;
  paymentStrategyLabel?: string;
  cardAmount?: number;
  depositAmount?: number;
  paidAt?: string;
  recipientName?: string;
  phone?: string;
  city?: string;
  deliveryAddress?: string;
  comment?: string;
  totalAmount: number;
  totalPv: number;
  itemsCount: number;
  items: OrderItem[];
  createdAt: string;
  updatedAt?: string;
}

export interface TipTopPayPaymentIntent {
  publicTerminalId: string;
  description: string;
  paymentSchema: 'Single' | 'Dual' | string;
  currency: 'KZT' | string;
  amount: number;
  externalId: string;
  accountId?: string;
  receiptEmail?: string;
  emailBehavior?: string;
  language?: string;
  successRedirectUrl: string;
  failRedirectUrl: string;
  userInfo: Record<string, string | number | undefined>;
  items: Array<{
    id: string;
    name: string;
    count: number;
    price: number;
  }>;
  metadata: {
    order_id?: string | number;
    order_number?: string;
    user_id?: string | number;
    [key: string]: unknown;
  };
}

export interface TipTopPayStatus {
  enabled: boolean;
  testMode: boolean;
  currency: string;
  publicTerminalIdSet: boolean;
}

export interface Package {
  id: string;
  code?: string;
  name: string;
  label?: string;
  price: number;
  pv: number;
  activityPv: number;
  turnoverPv: number;
  pvAmount?: number;
  pvMoneyRate?: number;
  volumeAmount?: number;
  referralBonus: number;
  binaryBonus: number | null;
  features: string[];
  isPopular?: boolean;
  sortOrder?: number;
  status?: string;
  statusLabel?: string;
  codeLabel?: string;
  isActive?: boolean;
  isUpgradeable?: boolean;
  current?: boolean;
  available?: boolean;
  action?: string;
  buttonLabel?: string;
  disabledReason?: string;
  paymentAmount?: number;
  upgradeFrom?: string;
}

export interface Status {
  id: string;
  code?: string;
  name: string;
  label?: string;
  pv: number;
  incomePotential: number;
  reward: string;
  isCashBonus: boolean;
  rewardType?: string;
  cashAmount?: number;
  compensationAmount?: number;
  compensationAvailable?: boolean;
  partnersCount?: number;
}

export interface LegalSettings {
  company_legal_name: string;
  company_bin: string;
  legal_address: string;
  actual_address: string;
  bank_name: string;
  iban: string;
  bik: string;
  kbe: string;
  support_phone: string;
  support_email: string;
  dispute_email: string;
  website_url: string;
  director_name: string;
  privacy_email: string;
}

export interface PaymentReadinessLegalPage {
  path: string;
  available: boolean;
}

export interface PaymentReadiness {
  appUrl: string;
  httpsEnabled: boolean;
  tiptopEnabled: boolean;
  publicTerminalIdSet: boolean;
  currency: string;
  legalPages: PaymentReadinessLegalPage[];
  requisitesFilled: boolean;
  requisitesMissing: string[];
  productsActiveCount: number;
  productsWithoutImageCount: number;
  productsWithoutStockCount: number;
  ordersPaymentStatusSupport: boolean;
  webhookRoutesWork: boolean;
  webhookRoutes: Record<string, boolean>;
  checkoutRequiresDeliveryFields: boolean;
}

export const fallbackLegalSettings: LegalSettings = {
  company_legal_name: 'ТОО "Safi Life Kazakhstan"',
  company_bin: '000000000000',
  legal_address: 'Республика Казахстан, г. Алматы, адрес компании уточняется в настройках',
  actual_address: 'Республика Казахстан, г. Алматы, офис компании уточняется в настройках',
  bank_name: 'Банк компании',
  iban: 'KZ000000000000000000',
  bik: 'XXXXKZKX',
  kbe: '17',
  support_phone: '+7 (700) 000-00-00',
  support_email: 'support@safilife.kz',
  dispute_email: 'dispute@safilife.kz',
  website_url: 'https://safilife.kz',
  director_name: 'Директор Safi Life',
  privacy_email: 'privacy@safilife.kz',
};

export interface EarningsSummary {
  totalEarned: number;
  availableToWithdraw: number;
  pendingBinary: number;
  referralTotal: number;
  binaryTotal: number;
  statusTotal: number;
  bonusX2Total: number;
  cashbackTotal: number;
  depositBalance: number;
  withdrawnTotal: number;
  pendingWithdrawal: number;
  currency: string;
}

export interface NewsArticle {
  id: string;
  title: string;
  date: string;
  excerpt?: string;
  content: string;
  imageUrl?: string;
  category: string;
  status?: string;
  isPublished?: boolean;
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
      redirectOnUnauthorized: false,
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

export async function getMyPermissions<T = unknown>() {
  return apiRequest<T>(endpoints.auth.permissions, {
    method: 'GET',
    auth: true,
  });
}

export async function getPublicProducts() {
  const response = await apiRequest(endpoints.public.products, { method: 'GET', auth: false });
  return normalizeProducts(response);
}

export async function getPublicDepositProducts() {
  const response = await apiRequest(endpoints.public.depositProducts, { method: 'GET', auth: false });
  return normalizeProducts(response);
}

export async function getPublicPackages() {
  const response = await apiRequest(endpoints.public.packages, { method: 'GET', auth: false });
  return normalizePackages(response);
}

export async function getRegistrationPackages() {
  const response = await apiRequest(endpoints.public.registrationPackages, { method: 'GET', auth: false });
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

export async function getPublicLegalSettings() {
  const response = await apiRequest(endpoints.public.legalSettings, { method: 'GET', auth: false });
  return normalizeLegalSettings(response);
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

export async function uploadDashboardAvatar<T = unknown>(file: File) {
  const formData = new FormData();
  formData.append('_method', 'PATCH');
  formData.append('avatar', file);

  return apiRequest<T>(endpoints.dashboard.profileAvatar, {
    method: 'POST',
    body: formData,
    auth: true,
  });
}

export async function getDashboardOverview<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.overview, {
    method: 'GET',
    auth: true,
  });
}

export async function getDashboardStructure<T = unknown>(params: ApiQueryParams = {}) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.dashboard.structure, params), {
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

export async function getDashboardEarningsSummary() {
  const response = await apiRequest(endpoints.dashboard.earningsSummary, {
    method: 'GET',
    auth: true,
  });

  return normalizeEarningsSummary(response);
}

export async function getDashboardNotifications<T = unknown>(limit = 10) {
  return apiRequest<T>(`${endpoints.dashboard.notifications}?limit=${encodeURIComponent(String(limit))}`, {
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

export async function getDashboardDepositProducts() {
  const response = await apiRequest(endpoints.dashboard.depositProducts, {
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

export async function searchTransferPartners(q: string, limit = 20) {
  const response = await apiRequest(buildEndpointWithParams(endpoints.dashboard.partnersSearch, { q, limit }), {
    method: 'GET',
    auth: true,
  });

  return normalizeTransferPartners(response);
}

export async function getPartnerTransfers(params: ApiQueryParams = {}) {
  const response = await apiRequest(buildEndpointWithParams(endpoints.dashboard.walletTransfers, params), {
    method: 'GET',
    auth: true,
  });

  return normalizePartnerTransfers(response);
}

export async function createPartnerTransfer<T = unknown>(payload: PartnerTransferPayload) {
  return apiRequest<T>(endpoints.dashboard.walletTransfers, {
    method: 'POST',
    body: compactPayload(payload),
    auth: true,
  });
}

export async function createInternalWalletTransfer<T = unknown>(payload: InternalWalletTransferPayload) {
  return apiRequest<T>(endpoints.dashboard.internalWalletTransfer, {
    method: 'POST',
    body: compactPayload(payload),
    auth: true,
  });
}

export async function getDashboardSupportTickets<T = unknown>() {
  return apiRequest<T>(endpoints.dashboard.supportTickets, {
    method: 'GET',
    auth: true,
  });
}

export async function createDashboardSupportTicket<T = unknown>(payload: Record<string, unknown> | FormData) {
  return apiRequest<T>(endpoints.dashboard.supportTickets, {
    method: 'POST',
    body: isFormData(payload) ? payload : compactPayload(payload),
    auth: true,
  });
}

export async function getDashboardSupportTicket<T = unknown>(ticketId: string | number) {
  return apiRequest<T>(endpoints.dashboard.supportTicket(ticketId), {
    method: 'GET',
    auth: true,
  });
}

export async function updateDashboardSupportTicket<T = unknown>(ticketId: string | number, payload: Record<string, unknown>) {
  return apiRequest<T>(endpoints.dashboard.supportTicket(ticketId), {
    method: 'PUT',
    body: payload,
    auth: true,
  });
}

export async function sendDashboardSupportMessage<T = unknown>(ticketId: string | number, payload: Record<string, unknown> | FormData) {
  return apiRequest<T>(endpoints.dashboard.supportTicketMessages(ticketId), {
    method: 'POST',
    body: isFormData(payload) ? payload : compactPayload(payload),
    auth: true,
  });
}

export async function closeDashboardSupportTicket<T = unknown>(ticketId: string | number) {
  return apiRequest<T>(endpoints.dashboard.closeSupportTicket(ticketId), {
    method: 'POST',
    auth: true,
  });
}

export async function downloadDashboardSupportAttachment(attachmentId: string | number, filename = 'attachment') {
  return downloadAuthenticatedFile(endpoints.dashboard.supportAttachmentDownload(attachmentId), filename);
}

export async function getOrders(params: ApiQueryParams = {}) {
  const response = await apiRequest(buildEndpointWithParams(endpoints.dashboard.orderCheckout, params), {
    method: 'GET',
    auth: true,
  });

  return normalizeOrders(response);
}

export async function createOrder(payload: OrderPayload) {
  const response = await apiRequest(endpoints.dashboard.orderCheckout, {
    method: 'POST',
    body: payload,
    auth: true,
  });

  return normalizeOrder(unwrapRecord(response, ['order']));
}

export async function createTipTopPayPaymentIntent(orderId: string | number) {
  const response = await apiRequest(endpoints.dashboard.orderTipTopPayIntent(orderId), {
    method: 'POST',
    auth: true,
  });

  return normalizeTipTopPayPaymentIntent(response);
}

export async function getTipTopPayStatus() {
  const response = await apiRequest(endpoints.dashboard.tipTopPayStatus, {
    method: 'GET',
    auth: false,
  });

  const record = unwrapRecord(response);

  return {
    enabled: Boolean(record.enabled),
    testMode: Boolean(record.test_mode ?? record.testMode),
    currency: getString(record, ['currency']) || 'KZT',
    publicTerminalIdSet: Boolean(record.public_terminal_id_set ?? record.publicTerminalIdSet),
  } satisfies TipTopPayStatus;
}

export async function getOrder(orderId: string | number) {
  const response = await apiRequest(endpoints.dashboard.order(orderId), {
    method: 'GET',
    auth: true,
  });

  return normalizeOrder(unwrapRecord(response, ['order']));
}

export async function getDashboardOrders(params: ApiQueryParams = {}) {
  const response = await apiRequest(buildEndpointWithParams(endpoints.dashboard.orders, params), {
    method: 'GET',
    auth: true,
  });

  return normalizeOrders(response);
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

export async function createDepositPurchase<T = unknown>(
  payload: number | (OrderPayload & { amount?: number; productId?: string | number }),
) {
  const body = typeof payload === 'number'
    ? { amount: payload }
    : {
        ...payload,
        product_id: payload.product_id ?? payload.productId,
      };

  return apiRequest<T>(endpoints.dashboard.depositPurchase, {
    method: 'POST',
    body: compactPayload(body),
    auth: true,
  });
}

export async function getAdminOverview<T = unknown>() {
  return apiRequest<T>(endpoints.admin.overview, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminStructure<T = unknown>(params: ApiQueryParams = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      query.set(key, String(value));
    }
  });

  return apiRequest<T>(`${endpoints.admin.structure}${query.toString() ? `?${query.toString()}` : ''}`, {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminStructureRootOrphans<T = unknown>(params: ApiQueryParams = {}) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.admin.structureRootOrphans, params), {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminUsers<T = unknown>(params: ApiQueryParams = {}) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.admin.partners, params), {
    method: 'GET',
    auth: true,
  });
}

export async function searchAdminPartners<T = unknown>(q: string, limit = 10) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.admin.partnersSearch, { q, limit }), {
    method: 'GET',
    auth: true,
  });
}

export async function searchAdminSponsors<T = unknown>(
  q = '',
  limit = 30,
  selectedId?: string | number,
) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.admin.sponsorsSearch, {
    q,
    limit,
    selected_id: selectedId,
  }), {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminPartner<T = unknown>(userId: string | number) {
  return apiRequest<T>(endpoints.admin.partner(userId), {
    method: 'GET',
    auth: true,
  });
}

export async function createAdminPartner<T = unknown>(payload: AdminPartnerPayload) {
  return apiRequest<T>(endpoints.admin.partners, {
    method: 'POST',
    body: compactPayload(payload),
    auth: true,
  });
}

export async function bulkCreateAdminPartners<T = unknown>(payload: AdminPartnerBulkPayload) {
  return apiRequest<T>(endpoints.admin.partnersBulkCreate, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function changeAdminPartnerPassword<T = unknown>(userId: string | number, payload: { password: string; password_confirmation: string }) {
  return apiRequest<T>(endpoints.admin.partnerPassword(userId), {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function blockAdminPartner<T = unknown>(userId: string | number) {
  return apiRequest<T>(endpoints.admin.partnerBlock(userId), {
    method: 'PATCH',
    auth: true,
  });
}

export async function unblockAdminPartner<T = unknown>(userId: string | number) {
  return apiRequest<T>(endpoints.admin.partnerUnblock(userId), {
    method: 'PATCH',
    auth: true,
  });
}

export async function changeAdminPartnerPackage<T = unknown>(
  userId: string | number,
  packageId: string | number,
  applyBusinessEffects = true,
) {
  return apiRequest<T>(endpoints.admin.partnerPackage(userId), {
    method: 'PATCH',
    body: { package_id: packageId, apply_business_effects: applyBusinessEffects },
    auth: true,
  });
}

export async function changeAdminPartnerBalance<T = unknown>(
  userId: string | number,
  payload: AdminPartnerBalancePayload,
) {
  return apiRequest<T>(endpoints.admin.partnerBalance(userId), {
    method: 'PATCH',
    body: compactPayload(payload),
    auth: true,
  });
}

export async function changeAdminPartnerStatus<T = unknown>(userId: string | number, status: string, applyBonusEffects = false) {
  return apiRequest<T>(endpoints.admin.partnerStatus(userId), {
    method: 'PATCH',
    body: { status, apply_bonus_effects: applyBonusEffects },
    auth: true,
  });
}

export async function saveAdminPartnerNote<T = unknown>(userId: string | number, adminNote: string) {
  return apiRequest<T>(endpoints.admin.partnerNote(userId), {
    method: 'PATCH',
    body: { admin_note: adminNote },
    auth: true,
  });
}

export async function getAdminPartnerTransactions<T = unknown>(userId: string | number, limit = 10) {
  return apiRequest<T>(`${endpoints.admin.partnerTransactions(userId)}?limit=${encodeURIComponent(String(limit))}`, {
    method: 'GET',
    auth: true,
  });
}

export async function calculateAdminPartnerBinaryBonus<T = unknown>(userId: string | number) {
  return apiRequest<T>(endpoints.admin.partnerBinaryBonusCalculate(userId), {
    method: 'POST',
    auth: true,
  });
}

export async function recalculateAdminPartnerBinaryBonus<T = unknown>(userId: string | number) {
  return apiRequest<T>(endpoints.admin.partnerBinaryBonusRecalculate(userId), {
    method: 'POST',
    auth: true,
  });
}

export async function getAdminPartnerDeletePreview(userId: string | number, deleteSubtree = false) {
  return apiRequest<AdminPartnerDeletePreview>(
    buildEndpointWithParams(endpoints.admin.partnerDeletePreview(userId), { delete_subtree: deleteSubtree ? 1 : 0 }),
    {
      method: 'GET',
      auth: true,
    },
  );
}

export async function deleteAdminPartner<T = unknown>(
  userId: string | number,
  payload: { delete_subtree?: boolean; reason: string },
) {
  return apiRequest<T>(endpoints.admin.partner(userId), {
    method: 'DELETE',
    body: payload,
    auth: true,
  });
}

export async function calculateAdminBinaryBonuses<T = unknown>() {
  return apiRequest<T>(`${endpoints.admin.bonuses}/binary/calculate`, {
    method: 'POST',
    auth: true,
  });
}

export async function recalculateAdminBonuses<T = unknown>(payload: { date_from: string; date_to: string; force?: boolean }) {
  return apiRequest<T>(`${endpoints.admin.bonuses}/binary/recalculate`, {
    method: 'POST',
    body: payload,
    auth: true,
  });
}

export async function updateAdminBonus<T = unknown>(bonusId: string | number, payload: { amount: number | string; reason: string }) {
  return apiRequest<T>(`${endpoints.admin.bonuses}/${encodeEndpointId(bonusId)}`, {
    method: 'PATCH',
    body: payload,
    auth: true,
  });
}

export async function deleteAdminBonus<T = unknown>(bonusId: string | number, payload: { reason: string }) {
  return apiRequest<T>(`${endpoints.admin.bonuses}/${encodeEndpointId(bonusId)}`, {
    method: 'DELETE',
    body: payload,
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

export async function createAdminProduct<T = unknown>(payload: Record<string, unknown> | FormData) {
  return apiRequest<T>(endpoints.admin.products, {
    method: 'POST',
    body: isFormData(payload) ? payload : compactPayload(payload),
    auth: true,
  });
}

export async function updateAdminProduct<T = unknown>(productId: string | number, payload: Record<string, unknown> | FormData) {
  if (isFormData(payload)) {
    if (!payload.has('_method')) {
      payload.append('_method', 'PUT');
    }

    return apiRequest<T>(endpoints.admin.product(productId), {
      method: 'POST',
      body: payload,
      auth: true,
    });
  }

  return apiRequest<T>(endpoints.admin.product(productId), {
    method: 'PUT',
    body: compactPayload(payload),
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

export async function getAdminOrders(params: ApiQueryParams = {}) {
  const response = await apiRequest(buildEndpointWithParams(endpoints.admin.orders, params), {
    method: 'GET',
    auth: true,
  });

  return {
    orders: normalizeOrders(response),
    meta: isRecord(response) ? response.meta : undefined,
  };
}

export async function getAdminPaymentReadiness() {
  const response = await apiRequest(endpoints.admin.paymentReadiness, {
    method: 'GET',
    auth: true,
  });

  return normalizePaymentReadiness(response);
}

export async function getAdminOrder(orderId: string | number) {
  const response = await apiRequest(endpoints.admin.order(orderId), {
    method: 'GET',
    auth: true,
  });

  return normalizeOrder(unwrapRecord(response, ['order']));
}

export async function updateAdminOrderStatus(orderId: string | number, status: string) {
  const response = await apiRequest(endpoints.admin.orderStatus(orderId), {
    method: 'PATCH',
    body: { status },
    auth: true,
  });

  return normalizeOrder(unwrapRecord(response, ['order']));
}

export async function getAdminTransactions<T = unknown>(params: ApiQueryParams = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      query.set(key, String(value));
    }
  });

  return apiRequest<T>(`${endpoints.admin.transactions}${query.toString() ? `?${query.toString()}` : ''}`, {
    method: 'GET',
    auth: true,
  });
}

export async function updateAdminTransactionAmount<T = unknown>(transactionId: string | number, payload: { amount: number | string; reason: string }) {
  return apiRequest<T>(endpoints.admin.transaction(transactionId), {
    method: 'PATCH',
    body: payload,
    auth: true,
  });
}

export async function deleteAdminTransaction<T = unknown>(transactionId: string | number, payload: { reason: string }) {
  return apiRequest<T>(endpoints.admin.transaction(transactionId), {
    method: 'DELETE',
    body: payload,
    auth: true,
  });
}

export async function getAdminBonuses<T = unknown>(params: ApiQueryParams = {}) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.admin.bonuses, params), {
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

export async function createAdminNews<T = unknown>(payload: Record<string, unknown> | FormData) {
  return apiRequest<T>(endpoints.admin.news, {
    method: 'POST',
    body: isFormData(payload) ? payload : compactPayload(payload),
    auth: true,
  });
}

export async function updateAdminNews<T = unknown>(newsId: string | number, payload: Record<string, unknown> | FormData) {
  if (isFormData(payload)) {
    if (!payload.has('_method')) {
      payload.append('_method', 'PUT');
    }

    return apiRequest<T>(endpoints.admin.newsItem(newsId), {
      method: 'POST',
      body: payload,
      auth: true,
    });
  }

  return apiRequest<T>(endpoints.admin.newsItem(newsId), {
    method: 'PUT',
    body: compactPayload(payload),
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

export async function getAdminSupportTickets<T = unknown>(params: ApiQueryParams = {}) {
  return apiRequest<T>(buildEndpointWithParams(endpoints.admin.supportTickets, params), {
    method: 'GET',
    auth: true,
  });
}

export async function getAdminSupportTicket<T = unknown>(ticketId: string | number) {
  return apiRequest<T>(endpoints.admin.supportTicket(ticketId), {
    method: 'GET',
    auth: true,
  });
}

export async function replyAdminSupportTicket<T = unknown>(
  ticketId: string | number,
  replyOrPayload: string | FormData | Record<string, unknown>,
  status?: string,
) {
  const body = typeof replyOrPayload === 'string'
    ? compactPayload({ message: replyOrPayload, status })
    : isFormData(replyOrPayload)
      ? replyOrPayload
      : compactPayload(replyOrPayload);

  return apiRequest<T>(endpoints.admin.replySupportTicket(ticketId), {
    method: 'POST',
    body,
    auth: true,
  });
}

export async function updateAdminSupportTicketStatus<T = unknown>(ticketId: string | number, status: string) {
  return apiRequest<T>(endpoints.admin.updateSupportTicketStatus(ticketId), {
    method: 'PATCH',
    body: { status },
    auth: true,
  });
}

export async function assignAdminSupportTicket<T = unknown>(ticketId: string | number, assignedTo?: string | number | null) {
  return apiRequest<T>(endpoints.admin.assignSupportTicket(ticketId), {
    method: 'PATCH',
    body: compactPayload({ assigned_to: assignedTo }),
    auth: true,
  });
}

export async function closeAdminSupportTicket<T = unknown>(ticketId: string | number) {
  return apiRequest<T>(endpoints.admin.closeSupportTicket(ticketId), {
    method: 'POST',
    auth: true,
  });
}

export async function reopenAdminSupportTicket<T = unknown>(ticketId: string | number) {
  return apiRequest<T>(endpoints.admin.reopenSupportTicket(ticketId), {
    method: 'POST',
    auth: true,
  });
}

export async function downloadAdminSupportAttachment(attachmentId: string | number, filename = 'attachment') {
  return downloadAuthenticatedFile(endpoints.admin.supportAttachmentDownload(attachmentId), filename);
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
    'Accept-Language': getCurrentLanguage(),
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

async function downloadAuthenticatedFile(endpoint: string, fallbackFilename: string) {
  const token = getAuthToken();
  const headers: Record<string, string> = {
    Accept: '*/*',
    'Accept-Language': getCurrentLanguage(),
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(buildApiUrl(endpoint), {
    method: 'GET',
    headers,
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const data = await parseResponse(response);
    throw new ApiError(getErrorMessage(data, response.status), response.status, getValidationErrors(data));
  }

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  const headerFilename = response.headers.get('Content-Disposition')?.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i)?.[1];

  link.href = url;
  link.download = headerFilename ? decodeURIComponent(headerFilename.replace(/"/g, '')) : fallbackFilename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
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

function serializeRequestBody(body: ApiBody) {
  if (body === undefined || body === null) {
    return undefined;
  }

  if (isFormData(body)) {
    return body;
  }

  return JSON.stringify(body);
}

function isFormData(body: ApiBody): body is FormData {
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

function compactPayload<T extends object>(payload: T): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== '' && value !== undefined && value !== null)
  );
}

function buildEndpointWithParams(endpoint: string, params: ApiQueryParams = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      query.set(key, String(value));
    }
  });

  return `${endpoint}${query.toString() ? `?${query.toString()}` : ''}`;
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

export function normalizeTransferPartners(response: unknown): TransferPartner[] {
  return getArray(response, ['partners']).map((item) => {
    const record = isRecord(item) ? item : {};

    return normalizeTransferPartner(record);
  }).filter((partner) => partner.id > 0);
}

export function normalizePartnerTransfers(response: unknown): PartnerTransfer[] {
  return getArray(response, ['transfers']).map((item) => {
    const record = isRecord(item) ? item : {};
    const senderRecord = isRecord(record.sender) ? record.sender : undefined;
    const recipientRecord = isRecord(record.recipient) ? record.recipient : undefined;

    return {
      id: getString(record, ['id']) || getString(record, ['uuid']) || '',
      uuid: getString(record, ['uuid']),
      senderId: getNumber(record, ['sender_id', 'senderId']) ?? 0,
      recipientId: getNumber(record, ['recipient_id', 'recipientId']) ?? 0,
      amount: getNumber(record, ['amount']) ?? 0,
      currency: getString(record, ['currency']) || 'KZT',
      status: getString(record, ['status']) || 'completed',
      comment: getString(record, ['comment']),
      sender: senderRecord ? normalizeTransferPartner(senderRecord) : null,
      recipient: recipientRecord ? normalizeTransferPartner(recipientRecord) : null,
      createdAt: getString(record, ['created_at', 'createdAt']),
    } satisfies PartnerTransfer;
  });
}

function normalizeTransferPartner(record: Record<string, unknown>): TransferPartner {
  return {
    id: getNumber(record, ['id']) ?? 0,
    name: getString(record, ['name']) || 'Партнёр',
    login: getString(record, ['login']),
    email: getString(record, ['email']),
    phone: getString(record, ['phone']),
    package: getString(record, ['package', 'package_label', 'packageLabel']),
    status: getString(record, ['status']),
  };
}

export function normalizeOrders(response: unknown): Order[] {
  return getArray(response, ['orders']).map((item, index) => normalizeOrder(item, index));
}

export function normalizeOrder(item: unknown, index = 0): Order {
  const record = isRecord(item) ? item : {};
  const user = isRecord(record.user) ? record.user : undefined;
  const delivery = isRecord(record.delivery) ? record.delivery : {};
  const shippingAddress = isRecord(record.shipping_address) ? record.shipping_address : isRecord(record.shippingAddress) ? record.shippingAddress : {};
  const items = getArray(record.items).map((orderItem, itemIndex) => normalizeOrderItem(orderItem, itemIndex));
  const status = getString(record, ['status']) || 'pending';

  return {
    id: getString(record, ['id']) || String(index + 1),
    orderNumber: getString(record, ['order_number', 'orderNumber']),
    userId: getString(record, ['user_id', 'userId']),
    user: user ? normalizeOrderUser(user) : null,
    status,
    statusLabel: orderStatusLabel(status, getString(record, ['status_label', 'statusLabel'])),
    paymentStatus: getString(record, ['payment_status', 'paymentStatus']),
    paymentStatusLabel: getString(record, ['payment_status_label', 'paymentStatusLabel']),
    paymentProvider: getString(record, ['payment_provider', 'paymentProvider']),
    paymentExternalId: getString(record, ['payment_external_id', 'paymentExternalId']),
    paymentTransactionId: getString(record, ['payment_transaction_id', 'paymentTransactionId']),
    paymentStrategy: getString(record, ['payment_strategy', 'paymentStrategy']),
    paymentStrategyLabel: getString(record, ['payment_strategy_label', 'paymentStrategyLabel']),
    cardAmount: getNumber(record, ['card_amount', 'cardAmount']),
    depositAmount: getNumber(record, ['deposit_amount', 'depositAmount']),
    paidAt: getString(record, ['paid_at', 'paidAt']),
    recipientName: getString(record, ['recipient_name', 'recipientName'])
      || getString(delivery, ['recipient_name', 'recipientName'])
      || getString(shippingAddress, ['recipient_name', 'recipientName', 'recipient']),
    phone: getString(record, ['phone'])
      || getString(delivery, ['phone'])
      || getString(shippingAddress, ['phone']),
    city: getString(record, ['city'])
      || getString(delivery, ['city'])
      || getString(shippingAddress, ['city']),
    deliveryAddress: getString(record, ['delivery_address', 'deliveryAddress'])
      || getString(delivery, ['delivery_address', 'deliveryAddress'])
      || getString(shippingAddress, ['delivery_address', 'deliveryAddress', 'address']),
    comment: getString(record, ['comment'])
      || getString(delivery, ['comment'])
      || getString(shippingAddress, ['comment']),
    totalAmount: getNumber(record, ['total_amount', 'totalAmount']) ?? 0,
    totalPv: getNumber(record, ['total_pv', 'totalPv']) ?? 0,
    itemsCount: getNumber(record, ['items_count', 'itemsCount']) ?? items.reduce((sum, orderItem) => sum + orderItem.quantity, 0),
    items,
    createdAt: getString(record, ['created_at', 'createdAt']) || '',
    updatedAt: getString(record, ['updated_at', 'updatedAt']),
  };
}

function normalizeOrderItem(item: unknown, index: number): OrderItem {
  const record = isRecord(item) ? item : {};
  const product = isRecord(record.product) ? record.product : undefined;
  const snapshot = isRecord(record.item_snapshot) ? record.item_snapshot : undefined;

  return {
    id: getString(record, ['id']) || String(index + 1),
    productId: getString(record, ['product_id', 'productId']),
    productName: getString(record, ['product_name', 'productName']) || getString(product, ['name', 'title']) || '-',
    quantity: getNumber(record, ['quantity']) ?? 0,
    unitPrice: getNumber(record, ['unit_price', 'unitPrice']) ?? 0,
    unitPv: getNumber(record, ['unit_pv', 'unitPv']) ?? 0,
    totalPrice: getNumber(record, ['total_price', 'totalPrice']) ?? 0,
    totalPv: getNumber(record, ['total_pv', 'totalPv']) ?? 0,
    image: getString(record, ['image_url', 'imageUrl'])
      || getString(snapshot, ['image_url', 'imageUrl', 'image_path', 'imagePath'])
      || getString(product, ['image', 'image_url', 'imageUrl', 'image_path', 'imagePath']),
    category: getString(product, ['category']),
  };
}

function normalizeOrderUser(user: Record<string, unknown>): OrderUser {
  return {
    id: getString(user, ['id']) || '-',
    name: getString(user, ['name', 'full_name', 'fullName']) || '-',
    login: getString(user, ['login', 'username']),
    email: getString(user, ['email']),
    phone: getString(user, ['phone']),
  };
}

function normalizeTipTopPayPaymentIntent(response: unknown): TipTopPayPaymentIntent {
  const intent = unwrapRecord(response, ['intent']);
  const userInfo = isRecord(intent.userInfo) ? intent.userInfo : {};
  const metadata = isRecord(intent.metadata) ? intent.metadata : {};

  return {
    publicTerminalId: getString(intent, ['publicTerminalId', 'public_terminal_id']) || '',
    description: getString(intent, ['description']) || '',
    paymentSchema: getString(intent, ['paymentSchema', 'payment_schema']) || 'Single',
    currency: getString(intent, ['currency']) || 'KZT',
    amount: getNumber(intent, ['amount']) ?? 0,
    externalId: getString(intent, ['externalId', 'external_id']) || '',
    accountId: getString(intent, ['accountId', 'account_id']),
    receiptEmail: getString(intent, ['receiptEmail', 'receipt_email']),
    emailBehavior: getString(intent, ['emailBehavior', 'email_behavior']),
    language: getString(intent, ['language']),
    successRedirectUrl: getString(intent, ['successRedirectUrl', 'success_redirect_url']) || '',
    failRedirectUrl: getString(intent, ['failRedirectUrl', 'fail_redirect_url']) || '',
    userInfo: Object.fromEntries(Object.entries(userInfo).filter(([, value]) => value !== null && value !== undefined)) as TipTopPayPaymentIntent['userInfo'],
    items: getArray(intent.items).map((item, index) => {
      const record = isRecord(item) ? item : {};

      return {
        id: getString(record, ['id']) || String(index + 1),
        name: getString(record, ['name']) || `Safi Life item ${index + 1}`,
        count: getNumber(record, ['count', 'quantity']) ?? 1,
        price: getNumber(record, ['price']) ?? 0,
      };
    }),
    metadata,
  };
}

function normalizePaymentReadiness(response: unknown): PaymentReadiness {
  const record = unwrapRecord(response);
  const webhookRoutes = isRecord(record.webhook_routes) ? record.webhook_routes : {};

  return {
    appUrl: getString(record, ['app_url', 'appUrl']) || '',
    httpsEnabled: Boolean(record.https_enabled ?? record.httpsEnabled),
    tiptopEnabled: Boolean(record.tiptop_enabled ?? record.tiptopEnabled),
    publicTerminalIdSet: Boolean(record.public_terminal_id_set ?? record.publicTerminalIdSet),
    currency: getString(record, ['currency']) || 'KZT',
    legalPages: getArray(record.legal_pages ?? record.legalPages).map((page) => {
      const pageRecord = isRecord(page) ? page : {};

      return {
        path: getString(pageRecord, ['path']) || '-',
        available: Boolean(pageRecord.available),
      };
    }),
    requisitesFilled: Boolean(record.requisites_filled ?? record.requisitesFilled),
    requisitesMissing: getStringArray(record, ['requisites_missing', 'requisitesMissing']) || [],
    productsActiveCount: getNumber(record, ['products_active_count', 'productsActiveCount']) ?? 0,
    productsWithoutImageCount: getNumber(record, ['products_without_image_count', 'productsWithoutImageCount']) ?? 0,
    productsWithoutStockCount: getNumber(record, ['products_without_stock_count', 'productsWithoutStockCount']) ?? 0,
    ordersPaymentStatusSupport: Boolean(record.orders_payment_status_support ?? record.ordersPaymentStatusSupport),
    webhookRoutesWork: Boolean(record.webhook_routes_work ?? record.webhookRoutesWork),
    webhookRoutes: Object.fromEntries(Object.entries(webhookRoutes).map(([key, value]) => [key, Boolean(value)])),
    checkoutRequiresDeliveryFields: Boolean(record.checkout_requires_delivery_fields ?? record.checkoutRequiresDeliveryFields),
  };
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
    const image = getString(record, ['image', 'image_url', 'imageUrl']) || getString(metadata, ['image', 'image_url', 'imageUrl']) || productImagePlaceholder;
    const status = getString(record, ['status']) || 'active';
    const isDepositProduct = Boolean(record.is_deposit_product ?? record.isDepositProduct ?? record.is_deposit_only ?? record.isDepositOnly);

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
      image,
      imageUrl: image,
      stock: getNumber(record, ['stock', 'stock_quantity']) ?? undefined,
      stockQuantity: getNumber(record, ['stock_quantity', 'stock']) ?? undefined,
      reservedQuantity: getNumber(record, ['reserved_quantity']) ?? 0,
      inStock: Boolean(record.in_stock ?? record.is_in_stock ?? ((getNumber(record, ['stock', 'stock_quantity']) ?? 0) > 0 && status !== 'inactive')),
      isDepositProduct,
      isDepositOnly: isDepositProduct,
      status,
      statusLabel: productStatusLabel(status, getString(record, ['status_label', 'statusLabel'])),
      createdAt: getString(record, ['created_at', 'createdAt']),
    };
  });
}

export const productImagePlaceholder = '/images/product-placeholder.svg';

export function normalizePackages(response: unknown): Package[] {
  return getArray(response, ['packages']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const code = getString(record, ['code', 'slug', 'id']) || String(index + 1);
    const name = getString(record, ['name', 'title', 'code']) || code.toUpperCase();
    const label = packageLabel(code, getString(record, ['code_label', 'codeLabel', 'label']) || name);
    const status = getString(record, ['status']);

    return {
      id: getString(record, ['id']) || code.toLowerCase(),
      code,
      codeLabel: label,
      name,
      label,
      price: getNumber(record, ['price']) ?? 0,
      pv: getNumber(record, ['pv']) ?? 0,
      activityPv: getNumber(record, ['activityPv', 'activity_pv', 'pv']) ?? 0,
      turnoverPv: getNumber(record, ['turnoverPv', 'turnover_pv', 'pv']) ?? 0,
      pvAmount: getNumber(record, ['pvAmount', 'pv_amount', 'volumeAmount', 'volume_amount']) ?? 0,
      pvMoneyRate: getNumber(record, ['pvMoneyRate', 'pv_money_rate']) ?? 500,
      volumeAmount: getNumber(record, ['volumeAmount', 'volume_amount']) ?? 0,
      referralBonus: getNumber(record, ['referralBonus', 'referral_percent']) ?? 0,
      binaryBonus: getNumber(record, ['binaryBonus', 'binary_percent']) ?? null,
      features: getStringArray(record, ['features']) || [
        'Доступ ко всем продуктам',
        'Личный кабинет',
        'Реферальная ссылка',
        'Обучающие материалы',
      ],
      isPopular: code.toLowerCase() === 'vip',
      sortOrder: getNumber(record, ['sort_order', 'sortOrder']) ?? index,
      status,
      statusLabel: productStatusLabel(status, getString(record, ['status_label', 'statusLabel'])),
      isActive: Boolean(record.is_active ?? record.isActive ?? true),
      isUpgradeable: Boolean(record.is_upgradeable ?? record.isUpgradeable ?? true),
      current: Boolean(record.current),
      available: Boolean(record.available),
      action: getString(record, ['action']),
      buttonLabel: getString(record, ['button_label', 'buttonLabel']),
      disabledReason: getString(record, ['disabled_reason', 'disabledReason']),
      paymentAmount: getNumber(record, ['paymentAmount', 'payment_amount']),
      upgradeFrom: getString(record, ['upgrade_from', 'upgradeFrom']),
    };
  });
}

export function normalizeNews(response: unknown): NewsArticle[] {
  return getArray(response, ['news']).map((item, index) => {
    const record = isRecord(item) ? item : {};
    const date = getString(record, ['published_at', 'created_at', 'date']) || '';
    const excerpt = getString(record, ['excerpt']);

    return {
      id: getString(record, ['id']) || String(index + 1),
      title: getString(record, ['title']) || `News ${index + 1}`,
      date: date ? new Date(date).toLocaleDateString('ru-RU') : '',
      excerpt,
      content: getString(record, ['content']) || excerpt || '',
      imageUrl: getString(record, ['imageUrl', 'image_url']),
      category: getString(record, ['category']) || 'Новости',
      status: getString(record, ['status']) || (record.is_published === false ? 'draft' : 'published'),
      isPublished: record.is_published !== false,
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
    const code = getString(record, ['code', 'id']) || String(index + 1);

    return {
      id: code,
      code,
      name: mlmStatusLabel(code, getString(record, ['name_label', 'label', 'name', 'title']) || `Status ${index + 1}`),
      label: mlmStatusLabel(code, getString(record, ['label', 'name_label', 'name', 'title']) || `Status ${index + 1}`),
      pv: getNumber(record, ['pv', 'threshold']) ?? 0,
      incomePotential: getNumber(record, ['incomePotential', 'income_potential']) ?? 0,
      reward: getString(record, ['reward']) || '',
      isCashBonus: Boolean(record.isCashBonus ?? record.is_cash_bonus),
      rewardType: getString(record, ['rewardType', 'reward_type']) ?? undefined,
      cashAmount: getNumber(record, ['cashAmount', 'cash_amount']) ?? undefined,
      compensationAmount: getNumber(record, ['compensationAmount', 'compensation_amount']) ?? undefined,
      compensationAvailable: Boolean(record.compensationAvailable ?? record.compensation_available),
      partnersCount: getNumber(record, ['partnersCount', 'partners_count']) ?? undefined,
    };
  });
}

export function normalizeLegalSettings(response: unknown): LegalSettings {
  const values = unwrapRecord(response, ['settings', 'legal_settings', 'legalSettings']);
  const normalized = { ...fallbackLegalSettings };

  (Object.keys(normalized) as Array<keyof LegalSettings>).forEach((key) => {
    const value = getString(values, [key]);

    if (value) {
      normalized[key] = value;
    }
  });

  return normalized;
}

export function normalizeEarningsSummary(response: unknown): EarningsSummary {
  const summary = unwrapRecord(response, ['summary', 'earnings_summary', 'earningsSummary']);
  const byType = isRecord(summary.by_type) ? summary.by_type : isRecord(summary.byType) ? summary.byType : {};

  return {
    totalEarned: getNumber(summary, ['total_earned', 'totalEarned', 'total']) ?? 0,
    availableToWithdraw: getNumber(summary, ['available_to_withdraw', 'availableToWithdraw', 'available']) ?? 0,
    pendingBinary: getNumber(summary, ['pending_binary', 'pendingBinary']) ?? 0,
    referralTotal: getNumber(summary, ['referral_total', 'referralTotal']) ?? getNumber(byType, ['referral']) ?? 0,
    binaryTotal: getNumber(summary, ['binary_total', 'binaryTotal']) ?? getNumber(byType, ['binary']) ?? 0,
    statusTotal: getNumber(summary, ['status_total', 'statusTotal']) ?? getNumber(byType, ['status']) ?? 0,
    bonusX2Total: getNumber(summary, ['bonus_x2_total', 'bonusX2Total']) ?? getNumber(byType, ['bonus_x2', 'x2']) ?? 0,
    cashbackTotal: getNumber(summary, ['cashback_total', 'cashbackTotal']) ?? getNumber(byType, ['cashback']) ?? 0,
    depositBalance: getNumber(summary, ['deposit_balance', 'depositBalance']) ?? 0,
    withdrawnTotal: getNumber(summary, ['withdrawn_total', 'withdrawnTotal', 'withdrawn']) ?? 0,
    pendingWithdrawal: getNumber(summary, ['pending_withdrawal', 'pendingWithdrawal', 'pending_withdrawals']) ?? 0,
    currency: getString(summary, ['currency']) || 'KZT',
  };
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
