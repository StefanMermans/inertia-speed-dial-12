import { cn } from '@/lib/utils';
import React from 'react';

type Props = React.DetailedHTMLProps<
    React.InputHTMLAttributes<HTMLInputElement>,
    HTMLInputElement
>;

export default function Input({ className, ...props }: Props) {
    return (
        <input
            {...props}
            className={cn(
                'm-0 rounded-md border border-gray-400 bg-transparent px-2 py-1 outline-0',
                className,
            )}
        />
    );
}
