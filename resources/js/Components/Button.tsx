import React from 'react';

type Variant = 'primary' | 'secondary' | 'outline' | 'danger';
type Size = 'sm' | 'md' | 'lg';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
}

const variantClasses: Record<Variant, string> = {
    primary:   'bg-primary text-primary-foreground hover:bg-primary/90',
    secondary: 'bg-surface-interactive text-foreground border border-border hover:border-border-strong',
    outline:   'bg-transparent border border-foreground-subtle text-foreground hover:bg-surface-muted',
    danger:    'bg-danger text-white hover:bg-danger/90',
};

const sizeClasses: Record<Size, string> = {
    sm: 'text-xs px-3 py-1.5',
    md: 'text-sm px-4 py-2',
    lg: 'text-base px-6 py-2.5',
};

export function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    disabled,
    children,
    className = '',
    ...props
}: ButtonProps) {
    return (
        <button
            disabled={disabled || loading}
            className={[
                'inline-flex items-center justify-center gap-2 rounded-full font-medium transition-colors',
                'disabled:opacity-50 disabled:cursor-not-allowed',
                variantClasses[variant],
                sizeClasses[size],
                className,
            ].join(' ')}
            {...props}
        >
            {loading && (
               <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true" >
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"strokeWidth="4" />
                    <circle className="opacity-75" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" strokeLinecap="round" strokeDasharray="20 44" />
                </svg>
            )}

            {children}
            
        </button>
    );
}
