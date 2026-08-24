import React from 'react';
import { cn } from '../../lib/utils';

interface SectionTitleProps {
  title: string;
  subtitle?: string;
  align?: 'left' | 'center';
  className?: string;
}

export function SectionTitle({ title, subtitle, align = 'center', className }: SectionTitleProps) {
  return (
    <div className={cn('mb-12', align === 'center' ? 'text-center' : 'text-left', className)}>
      <h2 className="text-3xl md:text-4xl font-bold tracking-tight text-gray-900 mb-4">{title}</h2>
      {subtitle && <p className="text-lg text-gray-600 max-w-3xl mx-auto">{subtitle}</p>}
    </div>
  );
}
