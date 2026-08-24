export function formatAdminDateTime(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  return String(value)
    .replace('T', ' ')
    .replace(/\.\d+Z?$/, '')
    .replace(/Z$/, '')
    .slice(0, 19);
}
