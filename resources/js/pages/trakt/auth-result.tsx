import { AuthResultCard } from '@/components/auth-result-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trakt', href: '/trakt/auth' },
];

interface AuthResultProps {
    success: boolean;
    message: string;
}

export default function AuthResult({ success, message }: AuthResultProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={success ? 'Trakt Connected' : 'Trakt Connection Failed'} />
            <AuthResultCard
                success={success}
                message={message}
                serviceName="Trakt"
                successDescription="Your watches will now be synced to your Trakt account."
                failureDescription="Something went wrong while connecting your Trakt account. You can try again below."
                retryUrl="/trakt/auth"
            />
        </AppLayout>
    );
}
