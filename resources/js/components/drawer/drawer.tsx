import { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    onClose?: () => void;
};

export default function Drawer({ children, onClose }: Props) {
    function handleBackgroundClick() {
        onClose?.();
    }

    return (
        <div className="absolute flex h-screen w-screen">
            <div
                onClick={handleBackgroundClick}
                className="w-full h-full"
            />
            <div className="h-full w-96 border-l border-gray-500 bg-black/50 backdrop-blur-xl">
                {children}
            </div>
        </div>
    );
}
