import React from 'react';
import { cn } from '@/lib/utils';

type Props = Exclude<
    React.DetailedHTMLProps<
        React.InputHTMLAttributes<HTMLInputElement>,
        HTMLInputElement
    >,
    'type'
>;

export default function ColorPicker({ className, ...props }: Props) {
    return (
        <input
            {...props}
            className={cn('border border-black bg-white', className)}
            type="color"
        />
    );
}
