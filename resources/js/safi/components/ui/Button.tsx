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
    const baseStyles = 'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg font-bold uppercase tracking-widest transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-safi-green disabled:pointer-events-none disabled:opacity-50';
    
    const variants = {
      primary: 'bg-safi-green text-white shadow-lg shadow-safi-green/20 hover:bg-safi-green-hover',
      secondary: 'border border-safi-gold bg-safi-gold text-safi-green shadow-lg hover:bg-amber-400',
      outline: 'border border-safi-green bg-transparent text-safi-green hover:bg-safi-green hover:text-white',
      ghost: 'text-safi-green hover:bg-safi-green/5',
    };

    const sizes = {
      sm: 'px-5 py-2.5 text-xs',
      md: 'px-6 py-3 text-xs',
      lg: 'px-8 py-4 text-sm',
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
