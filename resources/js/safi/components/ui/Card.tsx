import React from 'react';
import { cn } from '../../lib/utils';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  padding?: 'none' | 'sm' | 'md' | 'lg';
}

export function Card({ children, className, padding = 'md', ...props }: CardProps) {
  const paddings = {
    none: '',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8 md:p-10',
  };

  return (
    <div
      className={cn(
        'overflow-hidden rounded-3xl border border-safi-border/80 bg-safi-card shadow-[0_18px_48px_rgba(11,23,18,0.06)]',
        paddings[padding],
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
}
