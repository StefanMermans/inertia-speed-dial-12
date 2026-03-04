import { cn } from '@/lib/utils';
import { useRemember } from '@inertiajs/react';

type Props = Readonly<{
    onEdit(): void;
}>;

export default function EditButton({ onEdit }: Props) {
    const [visible, setVisible] = useRemember(false, 'EditButton.visible');

    return (
        <div
            id='speed-dial-edit-button'
            className={cn(
                'absolute -right-4 bottom-32 flex w-36 cursor-pointer flex-col rounded-md border border-gray-700 bg-black/15 backdrop-blur-xl transition-transform',
                {
                    'translate-x-28': !visible,
                    'translate-x-0': visible,
                },
            )}
        >
            <div className="flex">
                <button className="h-6 cursor-pointer py-1 pr-2 pl-1" onClick={() => setVisible((prev) => !prev)}>
                    <div className="h-full w-2 border-x border-x-gray-200/50" />
                </button>
                <button className="cursor-pointer px-2 text-gray-400" onClick={onEdit}>
                    Edit
                </button>
            </div>
        </div>
    );
}
