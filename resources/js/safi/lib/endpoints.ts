export const API_BASE_URL = '/api';

export type EndpointId = string | number;

export function encodeEndpointId(id: EndpointId) {
  return encodeURIComponent(String(id));
}

export const endpoints = {
  auth: {
    login: '/login',
    register: '/register',
    logout: '/logout',
    me: '/me',
  },
  public: {
    products: '/public/products',
    product: (product: EndpointId) => `/public/products/${encodeEndpointId(product)}`,
    packages: '/public/packages',
    news: '/public/news',
    newsItem: (news: EndpointId) => `/public/news/${encodeEndpointId(news)}`,
    faqs: '/public/faqs',
    statuses: '/public/statuses',
  },
  dashboard: {
    overview: '/dashboard/overview',
    profile: '/dashboard/profile',
    structure: '/dashboard/structure',
    transactions: '/dashboard/transactions',
    bonuses: '/dashboard/bonuses',
    packages: '/dashboard/packages',
    products: '/dashboard/products',
    orders: '/dashboard/orders',
    withdrawals: '/dashboard/withdrawals',
    supportTickets: '/dashboard/support-tickets',
    supportTicket: (ticket: EndpointId) => `/dashboard/support-tickets/${encodeEndpointId(ticket)}`,
    closeSupportTicket: (ticket: EndpointId) => `/dashboard/support-tickets/${encodeEndpointId(ticket)}/close`,
    productCatalog: '/products',
    orderCheckout: '/orders',
    withdrawalRequests: '/withdrawals',
    activatePackage: (pkg: EndpointId) => `/packages/${encodeEndpointId(pkg)}/activate`,
    upgradePackage: (pkg: EndpointId) => `/packages/${encodeEndpointId(pkg)}/upgrade`,
    calculateBinaryBonus: '/bonuses/binary/calculate',
  },
  admin: {
    overview: '/admin/overview',
    structure: '/admin/structure',
    users: '/admin/users',
    user: (user: EndpointId) => `/admin/users/${encodeEndpointId(user)}`,
    products: '/admin/products',
    product: (product: EndpointId) => `/admin/products/${encodeEndpointId(product)}`,
    packages: '/admin/packages',
    package: (pkg: EndpointId) => `/admin/packages/${encodeEndpointId(pkg)}`,
    orders: '/admin/orders',
    transactions: '/admin/transactions',
    bonuses: '/admin/bonuses',
    withdrawals: '/admin/withdrawals',
    approveWithdrawal: (withdrawal: EndpointId) => `/admin/withdrawals/${encodeEndpointId(withdrawal)}/approve`,
    rejectWithdrawal: (withdrawal: EndpointId) => `/admin/withdrawals/${encodeEndpointId(withdrawal)}/reject`,
    news: '/admin/news',
    newsItem: (news: EndpointId) => `/admin/news/${encodeEndpointId(news)}`,
    faqs: '/admin/faqs',
    faq: (faq: EndpointId) => `/admin/faqs/${encodeEndpointId(faq)}`,
    supportTickets: '/support/tickets',
    supportTicket: (ticket: EndpointId) => `/support/tickets/${encodeEndpointId(ticket)}`,
    replySupportTicket: (ticket: EndpointId) => `/support/tickets/${encodeEndpointId(ticket)}/reply`,
    updateSupportTicketStatus: (ticket: EndpointId) => `/support/tickets/${encodeEndpointId(ticket)}/status`,
    assignSupportTicket: (ticket: EndpointId) => `/support/tickets/${encodeEndpointId(ticket)}/assign`,
    closeSupportTicket: (ticket: EndpointId) => `/admin/support-tickets/${encodeEndpointId(ticket)}/close`,
    reportsSummary: '/admin/reports/summary',
    settings: '/admin/settings',
    statuses: '/admin/statuses',
  },
} as const;
