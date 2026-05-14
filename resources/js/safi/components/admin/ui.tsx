import React from 'react';
import { cn } from '../../lib/utils';
import { Badge as UserBadge } from '../dashboard/ui';

export function AdminStatCard({ title, value, icon: Icon, trend, className }: { title: string, value: string | number, icon: any, trend?: string, className?: string }) {
  return (
    <div className={cn("group relative overflow-hidden rounded-3xl border border-safi-border/80 bg-white p-6 shadow-[0_18px_48px_rgba(11,23,18,0.06)] transition-transform duration-200 hover:-translate-y-0.5", className)}>
      <div className="relative z-10 flex items-start justify-between gap-4">
        <div>
          <h3 className="mb-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">{title}</h3>
          <div className="font-serif text-2xl font-semibold leading-none text-safi-green">{value}</div>
          {trend && <div className="mt-2 text-[10px] font-extrabold uppercase tracking-[0.16em] text-green-600">{trend}</div>}
        </div>
        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-safi-border bg-safi-cream text-safi-gold">
          <Icon className="w-6 h-6" />
        </div>
      </div>
    </div>
  );
}

export function AdminTable({ headers, children }: { headers: string[], children: React.ReactNode }) {
  return (
    <div className="flex flex-col overflow-hidden rounded-3xl border border-safi-border/80 bg-white shadow-[0_18px_48px_rgba(11,23,18,0.06)]">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead className="bg-safi-cream">
            <tr>
              {headers.map((h, i) => (
                <th key={i} className="text-nowrap px-6 py-4 text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-safi-border/70 text-sm">
            {children}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export function AdminBadge({ children, variant = 'default', className }: { children: React.ReactNode, variant?: 'default' | 'success' | 'warning' | 'danger' | 'gold', className?: string }) {
  return <UserBadge variant={variant} className={className}>{children}</UserBadge>;
}
