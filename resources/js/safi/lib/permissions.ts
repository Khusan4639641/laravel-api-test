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

  return {
    role: getString(record, ['role']) || fallbackPermissions.role,
    label: getString(record, ['label']) || fallbackPermissions.label,
    redirect_after_login: getString(record, ['redirect_after_login', 'redirectAfterLogin']) || fallbackPermissions.redirect_after_login,
    allowed_routes: getStringArray(record.allowed_routes) || getStringArray(record.allowedRoutes) || fallbackPermissions.allowed_routes,
    menu: normalizeMenu(record.menu),
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

export function menuLabel(item: PermissionMenuItem) {
  return item.label || item.name || item.path;
}

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
