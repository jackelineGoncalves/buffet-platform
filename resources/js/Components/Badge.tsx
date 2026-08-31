import React from 'react';

type Variant = 'default' | 'danger' | 'warning' | 'info' | 'success';

interface BadgeProps {
    variant?: Variant;
    children: React.ReactNode
}

const variantClasses: Record<Variant, string> = {
    primary:   'bg-danger text-primary-foreground hover:bg-primary/90',

};

export function Badge ({
    variant = 'default', 
    children
}: BadgeProps) {

    <div className={['px-2 py-1 ',  variantClasses[variant],]}>

    </div>
}

