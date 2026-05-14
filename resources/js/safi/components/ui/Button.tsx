import React from 'react';
import { cn } from '../../lib/utils';
import { Link } from 'react-router-dom';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost';
  size?: 'sm' | 'md' | 'lg';
  asChild?: boolean;
  href?: string;
  to?: string;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', asChild: _asChild, href, to, children, ...props }, ref) => {
    const baseStyles = 'inline-flex shrink-0 items-center justify-center gap-2 rounded-full border font-extrabold uppercase tracking-[0.16em] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-safi-gold focus-visible:ring-offset-2 focus-visible:ring-offset-safi-bg disabled:pointer-events-none disabled:opacity-50';
    
    const variants = {
      primary: 'border-safi-green bg-safi-green text-white shadow-[0_18px_38px_rgba(11,23,18,0.16)] hover:-translate-y-0.5 hover:bg-safi-green-hover hover:shadow-[0_22px_44px_rgba(11,23,18,0.22)]',
      secondary: 'border-safi-gold bg-safi-gold text-safi-black shadow-[0_16px_34px_rgba(201,166,70,0.22)] hover:-translate-y-0.5 hover:border-safi-gold-hover hover:bg-safi-gold-hover',
      outline: 'border-safi-border bg-white/70 text-safi-green hover:-translate-y-0.5 hover:border-safi-green hover:bg-safi-green hover:text-white',
      ghost: 'border-transparent bg-transparent text-safi-green hover:bg-safi-cream',
    };

    const sizes = {
      sm: 'min-h-10 px-4 py-2 text-[10px]',
      md: 'min-h-12 px-6 py-3 text-xs',
      lg: 'min-h-14 px-8 py-4 text-sm',
    };

    const classes = cn(baseStyles, variants[variant], sizes[size], className);

    if (to) {
      return (
        <Link to={to} className={classes} onClick={props.onClick as never}>
          {children}
        </Link>
      );
    }

    if (href) {
      return (
        <a href={href} className={classes} onClick={props.onClick as never}>
          {children}
        </a>
      );
    }

    return (
      <button ref={ref} className={classes} {...props}>
        {children}
      </button>
    );
  }
);

Button.displayName = 'Button';
