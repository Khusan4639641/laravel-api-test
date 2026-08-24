import type { ReactNode } from 'react';
import { cn } from '../../lib/utils';

export function MobileDataList({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn('mobile-data-list md:hidden', className)}>{children}</div>;
}

export function MobileDataCard({ children, className }: { children: ReactNode; className?: string }) {
  return <article className={cn('mobile-data-card', className)}>{children}</article>;
}

export function MobileDataHeader({
  title,
  meta,
  action,
}: {
  title: ReactNode;
  meta?: ReactNode;
  action?: ReactNode;
}) {
  return (
    <div className="mobile-data-card__header">
      <div className="min-w-0">
        <div className="mobile-data-card__title">{title}</div>
        {meta && <div className="mobile-data-card__meta">{meta}</div>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

export function MobileDataRow({ label, children }: { label: ReactNode; children: ReactNode }) {
  return (
    <div className="mobile-data-row">
      <div className="mobile-data-label">{label}</div>
      <div className="mobile-data-value">{children}</div>
    </div>
  );
}

export function MobileCardActions({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn('mobile-card-actions', className)}>{children}</div>;
}
