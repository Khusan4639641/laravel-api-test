import { cn } from '../../lib/utils';
import { ReactNode } from 'react';

export function StatCard({ 
  title, 
  value, 
  icon, 
  trend,
  className,
  variant = 'default'
}: { 
  title: string; 
  value: ReactNode; 
  icon?: ReactNode; 
  trend?: { value: string, isPositive: boolean };
  className?: string;
  variant?: 'default' | 'primary' | 'dark' | 'gold';
}) {
  const bgColors = {
    default: 'border-safi-border/80 bg-white',
    primary: 'border-safi-green bg-safi-green text-white',
    dark: 'border-safi-black bg-safi-black text-white',
    gold: 'border-safi-gold bg-safi-gold text-safi-black'
  };

  const titleColors = {
    default: 'text-safi-muted',
    primary: 'text-white/70',
    dark: 'text-white/70',
    gold: 'text-safi-black/65'
  };

  const valueColors = {
    default: 'text-safi-green',
    primary: 'text-white',
    dark: 'text-white',
    gold: 'text-safi-black'
  };

  return (
    <div className={cn("group relative overflow-hidden rounded-3xl border p-6 shadow-[0_18px_48px_rgba(11,23,18,0.06)] transition-transform duration-200 hover:-translate-y-0.5", bgColors[variant], className)}>
      <div className="flex justify-between items-start mb-4 relative z-10">
        <div className={cn("text-[10px] uppercase font-extrabold tracking-[0.16em]", titleColors[variant])}>{title}</div>
        {icon && <div className={variant === 'default' ? "text-safi-green/35 transition-colors group-hover:text-safi-gold" : "text-current/40"}>{icon}</div>}
      </div>
      <div className={cn("relative z-10 font-serif text-2xl font-semibold md:text-3xl", valueColors[variant])}>{value}</div>
      {trend && (
        <div className={cn("relative z-10 mt-2 text-xs font-bold", trend.isPositive ? (variant === 'default' ? "text-green-600" : "text-green-300") : (variant === 'default' ? "text-red-600" : "text-red-300"))}>
          {trend.isPositive ? '+' : ''}{trend.value}
        </div>
      )}
    </div>
  );
}

export function ProgressBar({ label, current, total, percentageOverride }: { label: string; current: number; total: number; percentageOverride?: number }) {
  const percentage = percentageOverride !== undefined ? percentageOverride : Math.min(100, Math.max(0, (current / total) * 100));
  
  return (
    <div className="w-full">
      <div className="mb-2 flex items-end justify-between text-sm font-bold">
        <span className="text-safi-green">{label}</span>
        <span className="text-xs text-safi-gold">{percentage.toFixed(0)}%</span>
      </div>
      <div className="h-2 w-full overflow-hidden rounded-full bg-safi-cream">
        <div 
          className="h-full rounded-full bg-safi-gold transition-all duration-1000 ease-out"
          style={{ width: `${percentage}%` }}
        />
      </div>
      <div className="mt-2 flex items-center justify-between text-[10px] font-extrabold uppercase tracking-[0.16em] text-safi-muted">
        <span>{current.toLocaleString('ru-RU')}</span>
        <span>{total.toLocaleString('ru-RU')}</span>
      </div>
    </div>
  );
}

export function Badge({ children, variant = 'default', className }: { children: ReactNode; variant?: 'default' | 'success' | 'warning' | 'danger' | 'gold', className?: string }) {
  const variants = {
    default: "border-safi-border bg-safi-cream text-safi-green",
    success: "border-green-200 bg-green-50 text-green-700",
    warning: "border-amber-200 bg-amber-50 text-amber-700",
    danger: "border-red-200 bg-red-50 text-red-700",
    gold: "border-safi-gold/30 bg-safi-gold/10 text-safi-gold"
  };

  return (
    <span className={cn("inline-flex items-center justify-center rounded-full border px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-[0.16em]", variants[variant], className)}>
      {children}
    </span>
  );
}
