import { AuthResultCard } from '@/components/auth-result-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'TMDB', href: '/tmdb/auth' },
];

interface AuthResultProps {
    success: boolean;
    message: string;
}

export default function AuthResult({ success, message }: AuthResultProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={success ? 'TMDB Connected' : 'TMDB Connection Failed'} />
            <AuthResultCard
                success={success}
                message={message}
                serviceName="TMDB"
                successDescription="You can now add movies and TV shows to your TMDB lists."
                failureDescription="Something went wrong while connecting your TMDB account. You can try again below."
                retryUrl="/tmdb/auth"
            />
        </AppLayout>
    );
}
