import { cn } from '@/lib/utils';
import React from 'react';

type Variant = 'contained' | 'danger';

type Props = React.DetailedHTMLProps<React.ButtonHTMLAttributes<HTMLButtonElement>, HTMLButtonElement> & {
    variant?: Variant;
};

export default function Button({ children, className, variant, ...props }: Props) {
    const isVariant = (v: Variant) => v === (variant ?? 'contained');

    return (
        <button
            {...props}
            className={cn(
                'm-0 rounded-md border px-4 py-1',
                {
                    'border-gray-400 bg-white text-black hover:bg-gray-200 active:bg-gray-300': isVariant('contained'),
                    'bg-red-500 text-white hover:bg-red-600 active:bg-red-700': isVariant('danger'),
                },
                className,
            )}
        >
            {children}
        </button>
    );
}
