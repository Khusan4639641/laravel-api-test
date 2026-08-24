export const features = {
  support: true,
  floatingExternalContacts: false,
} as const;

export function isSupportFrontendPath(path?: string | null) {
  const normalized = normalizePath(path || '');

  return normalized === '/support'
    || normalized.startsWith('/support/')
    || normalized === '/dashboard/support'
    || normalized.startsWith('/dashboard/support/')
    || normalized === '/admin/support'
    || normalized.startsWith('/admin/support/');
}

function normalizePath(path: string) {
  if (!path) {
    return '/';
  }

  const cleanPath = path.split('?')[0].split('#')[0] || '/';

  return cleanPath.length > 1 && cleanPath.endsWith('/') ? cleanPath.slice(0, -1) : cleanPath;
}
