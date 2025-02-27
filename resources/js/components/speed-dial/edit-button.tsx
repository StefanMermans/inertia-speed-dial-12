import { cn } from '@/lib/utils';
import { Link, useRemember } from '@inertiajs/react';
import { LogOut } from 'lucide-react';

type Props = {
    onEdit(): void;
};

export default function EditButton({ onEdit }: Props) {
    const [visible, setVisible] = useRemember(false, 'EditButton.visible');

    return (
        <div
            className={cn(
                'absolute -right-4 bottom-32 flex flex-col w-36 cursor-pointer rounded-md border border-gray-700 bg-black/15 backdrop-blur-xl transition-transform',
                {
                    'translate-x-28': !visible,
                    'translate-x-0': visible,
                },
            )}
        >
            <div className="flex">
                <button className="cursor-pointer h-6 py-1 pr-2 pl-1" onClick={() => setVisible((prev) => !prev)}>
                    <div className="h-full w-2 border-x border-x-gray-200/50" />
                </button>
                <button className="cursor-pointer px-2 text-gray-400" onClick={onEdit}>
                    Edit
                </button>
            </div>
            <button className='cursor-pointer'>
                <Link className="block w-full" method="post" href={route('logout')} as="button">
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </button>
        </div>
    );
}
