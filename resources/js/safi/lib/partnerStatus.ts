const activePackageCodes = new Set(['START', 'VIP', 'ELITE']);

export type PartnerPackageStatus = 'active' | 'inactive';

export function getPartnerPackageStatus(record?: Record<string, unknown>, fallbackPackageCode = ''): PartnerPackageStatus {
  const explicitActive = record?.is_partner_active ?? record?.isPartnerActive;

  if (typeof explicitActive === 'boolean') {
    return explicitActive ? 'active' : 'inactive';
  }

  const explicitStatus = stringValue(record?.package_status ?? record?.packageStatus);

  if (explicitStatus === 'active') {
    return 'active';
  }

  if (explicitStatus === 'inactive') {
    return 'inactive';
  }

  return activePackageCodes.has(normalizePackageCode(fallbackPackageCode)) ? 'active' : 'inactive';
}

export function isPartnerPackageActive(record?: Record<string, unknown>, fallbackPackageCode = '') {
  return getPartnerPackageStatus(record, fallbackPackageCode) === 'active';
}

export function partnerPackageStatusLabel(status: PartnerPackageStatus) {
  return status === 'active' ? 'Активен' : 'Неактивен';
}

export function activePackageCode(record?: Record<string, unknown>, fallbackPackageCode = '') {
  return isPartnerPackageActive(record, fallbackPackageCode) ? normalizePackageCode(fallbackPackageCode) : '';
}

function normalizePackageCode(value: string) {
  return String(value || '').trim().toUpperCase();
}

function stringValue(value: unknown) {
  return typeof value === 'string' ? value.trim().toLowerCase() : '';
}
