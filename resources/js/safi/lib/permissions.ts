import { features, isSupportFrontendPath } from '../config/features';
import i18n from '../i18n';

export interface PermissionMenuItem {
  path: string;
  label?: string;
  name?: string;
  icon?: string;
}

export interface RolePermissions {
  role: string;
  label: string;
  redirect_after_login: string;
  allowed_routes: string[];
  menu: PermissionMenuItem[];
}

export const fallbackPermissions: RolePermissions = {
  role: 'user',
  label: 'Partner',
  redirect_after_login: '/dashboard',
  allowed_routes: ['/dashboard'],
  menu: [{ path: '/dashboard', label: 'Обзор', icon: 'layout-dashboard' }],
};

export function normalizePermissions(response: unknown): RolePermissions {
  const record = isRecord(response) ? response : {};
  const allowedRoutes = getStringArray(record.allowed_routes) || getStringArray(record.allowedRoutes) || fallbackPermissions.allowed_routes;
  const menu = normalizeMenu(record.menu);

  return {
    role: getString(record, ['role']) || fallbackPermissions.role,
    label: getString(record, ['label']) || fallbackPermissions.label,
    redirect_after_login: getString(record, ['redirect_after_login', 'redirectAfterLogin']) || fallbackPermissions.redirect_after_login,
    allowed_routes: filterFeatureRoutes(allowedRoutes),
    menu: filterFeatureMenu(menu),
  };
}

export function canAccessPath(pathname: string, permissions: RolePermissions) {
  const path = normalizePath(pathname);

  return permissions.allowed_routes.some((allowedRoute) => {
    const allowed = normalizePath(allowedRoute);

    if (path === allowed) {
      return true;
    }

    if (allowed === '/admin' || allowed === '/dashboard' || allowed === '/support') {
      return false;
    }

    return routePatternMatches(allowed, path);
  });
}

export function menuLabel(item: PermissionMenuItem, language?: string) {
  const fallback = item.label || item.name || item.path;
  const key = menuTranslationKeys[item.path];

  return key ? i18n.t(key, { lng: language, defaultValue: fallback }) : fallback;
}

const menuTranslationKeys: Record<string, string> = {
  '/dashboard': 'menu.dashboardOverview',
  '/dashboard/structure': 'menu.dashboardStructure',
  '/dashboard/transactions': 'menu.transactions',
  '/dashboard/bonuses': 'menu.bonusesWithdrawals',
  '/dashboard/package': 'menu.packageStatus',
  '/dashboard/products': 'menu.products',
  '/dashboard/orders': 'orders.myOrders',
  '/dashboard/news': 'menu.news',
  '/dashboard/profile': 'menu.profile',
  '/dashboard/support': 'menu.support',
  '/admin': 'menu.adminOverview',
  '/admin/partners': 'menu.partners',
  '/admin/structure': 'menu.structure',
  '/admin/transactions': 'menu.transactions',
  '/admin/withdrawals': 'menu.withdrawals',
  '/admin/orders': 'orders.orders',
  '/admin/bonuses': 'menu.bonuses',
  '/admin/packages': 'menu.packages',
  '/admin/statuses': 'menu.statuses',
  '/admin/products': 'menu.products',
  '/admin/news': 'menu.news',
  '/admin/support': 'menu.support',
  '/admin/reports': 'menu.reports',
  '/admin/settings': 'menu.settings',
  '/support': 'menu.support',
  '/support/profile': 'menu.profile',
};

function normalizeMenu(value: unknown): PermissionMenuItem[] {
  if (!Array.isArray(value)) {
    return fallbackPermissions.menu;
  }

  return value
    .filter(isRecord)
    .map((item) => ({
      path: getString(item, ['path', 'route', 'url']) || '/',
      label: getString(item, ['label', 'name', 'title']),
      name: getString(item, ['name', 'label', 'title']),
      icon: getString(item, ['icon']),
    }))
    .filter((item) => item.path !== '/');
}

function filterFeatureRoutes(routes: string[]) {
  if (features.support) {
    return routes;
  }

  return routes.filter((route) => !isSupportFrontendPath(route));
}

function filterFeatureMenu(menu: PermissionMenuItem[]) {
  if (features.support) {
    return menu;
  }

  return menu.filter((item) => !isSupportFrontendPath(item.path));
}

function normalizePath(value: string) {
  if (value.length > 1 && value.endsWith('/')) {
    return value.slice(0, -1);
  }

  return value || '/';
}

function routePatternMatches(pattern: string, path: string) {
  const patternSegments = pattern.split('/').filter(Boolean);
  const pathSegments = path.split('/').filter(Boolean);

  if (patternSegments.length !== pathSegments.length) {
    return false;
  }

  return patternSegments.every((segment, index) => {
    if (!segment.startsWith(':')) {
      return segment === pathSegments[index];
    }

    return segment.toLowerCase().includes('id') ? /^\d+$/.test(pathSegments[index]) : pathSegments[index] !== '';
  });
}

function getString(record: Record<string, unknown>, keys: string[]) {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === 'string' && value.trim() !== '') {
      return value;
    }
  }

  return undefined;
}

function getStringArray(value: unknown) {
  if (!Array.isArray(value)) {
    return undefined;
  }

  const strings = value.filter((item): item is string => typeof item === 'string' && item.trim() !== '');

  return strings.length > 0 ? strings : undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
