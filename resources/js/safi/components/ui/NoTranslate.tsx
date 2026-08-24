import type { ElementType, HTMLAttributes, ReactNode } from 'react';
import { cn } from '../../lib/utils';

interface NoTranslateProps extends HTMLAttributes<HTMLElement> {
  as?: ElementType;
  children: ReactNode;
}

export function NoTranslate({
  as: Component = 'span',
  className,
  children,
  ...props
}: NoTranslateProps) {
  return (
    <Component
      translate="no"
      className={cn('notranslate', className)}
      {...props}
    >
      {children}
    </Component>
  );
}
