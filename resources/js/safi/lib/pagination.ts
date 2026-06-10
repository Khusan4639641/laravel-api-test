export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number;
  to?: number;
}

export const defaultPaginationMeta: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
  from: 0,
  to: 0,
};

export function getPaginatedItems(response: unknown, legacyKey: string): unknown[] {
  if (Array.isArray(response)) {
    return response;
  }

  if (!isRecord(response)) {
    return [];
  }

  const legacy = response[legacyKey];

  if (Array.isArray(response.data)) {
    return response.data;
  }

  if (isRecord(response.data) && Array.isArray(response.data.data)) {
    return response.data.data;
  }

  if (Array.isArray(legacy)) {
    return legacy;
  }

  if (isRecord(legacy) && Array.isArray(legacy.data)) {
    return legacy.data;
  }

  return [];
}

export function normalizePaginationMeta(
  response: unknown,
  legacyKey: string,
  fallbackPage = 1,
  fallbackPerPage = 20,
  rowCount = 0,
): PaginationMeta {
  const record = isRecord(response) ? response : {};
  const nested = isRecord(record[legacyKey]) ? record[legacyKey] as Record<string, unknown> : {};
  const dataObject = isRecord(record.data) && !Array.isArray(record.data) ? record.data : {};
  const meta = isRecord(record.meta)
    ? record.meta
    : isRecord(nested.meta)
      ? nested.meta
      : isRecord(dataObject.meta)
        ? dataObject.meta
        : {};
  const source = Object.keys(meta).length > 0
    ? meta
    : Object.keys(nested).length > 0
      ? nested
      : Object.keys(dataObject).length > 0
        ? dataObject
        : record;
  const currentPage = getNumber(source, ['current_page', 'currentPage']) ?? fallbackPage;
  const perPage = getNumber(source, ['per_page', 'perPage']) ?? fallbackPerPage;
  const total = getNumber(source, ['total']) ?? rowCount;
  const lastPage = getNumber(source, ['last_page', 'lastPage']) ?? Math.max(Math.ceil(total / Math.max(perPage, 1)), 1);
  const from = getNumber(source, ['from']) ?? (total > 0 ? ((currentPage - 1) * perPage) + 1 : 0);
  const to = getNumber(source, ['to']) ?? (total > 0 ? Math.min(currentPage * perPage, total) : 0);

  return {
    current_page: Math.max(currentPage, 1),
    last_page: Math.max(lastPage, 1),
    per_page: Math.max(perPage, 1),
    total: Math.max(total, 0),
    from,
    to,
  };
}

function getNumber(record: Record<string, unknown>, keys: string[]) {
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

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
