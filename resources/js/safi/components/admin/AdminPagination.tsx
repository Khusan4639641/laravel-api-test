import type { ReactNode } from 'react';
import type { PaginationMeta } from '../../lib/pagination';

type PaginationItem = number | 'ellipsis';

export interface AdminPaginationProps {
  meta: PaginationMeta;
  perPageOptions?: number[];
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
  totalSuffix?: ReactNode;
}

const defaultPerPageOptions = [20, 50, 100];

export function AdminPagination({
  meta,
  perPageOptions = defaultPerPageOptions,
  onPageChange,
  onPerPageChange,
  totalSuffix,
}: AdminPaginationProps) {
  const total = Math.max(Number(meta.total) || 0, 0);
  const perPage = Math.max(Number(meta.per_page) || perPageOptions[0] || 20, 1);
  const lastPage = Math.max(Number(meta.last_page) || Math.ceil(total / perPage) || 1, 1);
  const currentPage = Math.min(Math.max(Number(meta.current_page) || 1, 1), lastPage);
  const from = total === 0 ? 0 : Number(meta.from) || ((currentPage - 1) * perPage) + 1;
  const to = total === 0 ? 0 : Number(meta.to) || Math.min(currentPage * perPage, total);
  const paginationItems = getPaginationItems(currentPage, lastPage);

  return (
    <div className="flex flex-col gap-4 rounded-[24px] border border-safi-border bg-white px-5 py-4 shadow-[0_14px_36px_rgba(11,23,18,0.05)] xl:flex-row xl:items-center xl:justify-between">
      <div className="text-sm font-bold text-safi-muted">
        {total === 0 ? (
          <>Показано: <span className="text-safi-green">0</span> из <span className="text-safi-green">0</span></>
        ) : (
          <>Показано: <span className="text-safi-green">{from}-{to}</span> из <span className="text-safi-green">{total.toLocaleString('ru-RU')}</span></>
        )}
        {totalSuffix}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <select
          value={perPage}
          onChange={(event) => onPerPageChange(Number(event.target.value))}
          className="cursor-pointer rounded-full border border-safi-border bg-safi-cream px-4 py-2 text-xs font-extrabold text-safi-green outline-none focus:border-safi-green"
          aria-label="Количество записей на странице"
        >
          {perPageOptions.map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </select>
        <button
          type="button"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
          className="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-base font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
          aria-label="Предыдущая страница"
        >
          ‹
        </button>
        <div className="flex flex-wrap items-center gap-1">
          {paginationItems.map((item, index) => item === 'ellipsis' ? (
            <span
              key={`ellipsis-${index}`}
              className="flex h-9 min-w-9 items-center justify-center px-2 text-sm font-extrabold text-safi-muted"
              aria-hidden="true"
            >
              …
            </span>
          ) : (
            <button
              key={item}
              type="button"
              onClick={() => onPageChange(item)}
              disabled={item === currentPage}
              aria-current={item === currentPage ? 'page' : undefined}
              className={[
                'flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-full border px-3 text-xs font-extrabold transition-colors disabled:cursor-default',
                item === currentPage
                  ? 'border-safi-green bg-safi-green text-white shadow-[0_8px_22px_rgba(29,78,54,0.18)]'
                  : 'border-safi-border bg-safi-cream text-safi-green hover:border-safi-green hover:bg-white disabled:opacity-60',
              ].join(' ')}
            >
              {item}
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= lastPage}
          className="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-full border border-safi-border bg-safi-cream px-3 text-base font-extrabold text-safi-green transition-colors hover:border-safi-green hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
          aria-label="Следующая страница"
        >
          ›
        </button>
      </div>
    </div>
  );
}

function getPaginationItems(currentPage: number, totalPages: number): PaginationItem[] {
  const safeTotalPages = Math.max(1, Math.floor(totalPages));
  const safeCurrentPage = Math.min(Math.max(Math.floor(currentPage), 1), safeTotalPages);

  if (safeTotalPages <= 9) {
    return pageRange(1, safeTotalPages);
  }

  if (safeCurrentPage <= 5) {
    return [...pageRange(1, 8), 'ellipsis', safeTotalPages - 1, safeTotalPages];
  }

  if (safeCurrentPage >= safeTotalPages - 4) {
    return [1, 2, 'ellipsis', ...pageRange(safeTotalPages - 7, safeTotalPages)];
  }

  return [
    1,
    2,
    'ellipsis',
    ...pageRange(safeCurrentPage - 2, safeCurrentPage + 2),
    'ellipsis',
    safeTotalPages - 1,
    safeTotalPages,
  ];
}

function pageRange(start: number, end: number): number[] {
  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}
