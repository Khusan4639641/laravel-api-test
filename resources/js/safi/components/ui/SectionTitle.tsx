import React from 'react';
import { cn } from '../../lib/utils';

interface SectionTitleProps {
  title: string;
  subtitle?: string;
  align?: 'left' | 'center';
  className?: string;
}

export function SectionTitle({ title, subtitle, align = 'center', className }: SectionTitleProps) {
  const isCenter = align === 'center';

  return (
    <div className={cn('mb-12 md:mb-16', isCenter ? 'text-center' : 'text-left', className)}>
      <div className={cn('mb-4 h-px w-12 bg-safi-gold', isCenter && 'mx-auto')} />
      <h2 className="font-serif text-4xl font-semibold leading-tight text-safi-green md:text-5xl lg:text-6xl">
        {title}
      </h2>
      {subtitle && (
        <p className={cn('mt-5 text-base leading-7 text-safi-muted md:text-lg', isCenter ? 'mx-auto max-w-3xl' : 'max-w-2xl')}>
          {subtitle}
        </p>
      )}
    </div>
  );
}
