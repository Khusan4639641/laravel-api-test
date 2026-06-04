export function formatPv(value: unknown) {
  const numericValue = typeof value === 'number'
    ? value
    : typeof value === 'string'
      ? Number(value.replace(/[^\d.-]/g, ''))
      : 0;

  const safeValue = Number.isFinite(numericValue) ? numericValue : 0;
  const displayValue = Number.isInteger(safeValue) ? safeValue : Number(safeValue.toFixed(2));

  return `${displayValue.toLocaleString('ru-RU')} PV`;
}
