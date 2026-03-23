import { AuthResultCard } from '@/components/auth-result-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'AniList', href: route('anilist.redirect') },
];

interface AuthResultProps {
    success: boolean;
    message: string;
}

export default function AuthResult({ success, message }: AuthResultProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={success ? 'AniList Connected' : 'AniList Connection Failed'} />
            <AuthResultCard
                success={success}
                message={message}
                serviceName="AniList"
                successDescription="Your anime and manga lists will now be synced to your AniList account."
                failureDescription="Something went wrong while connecting your AniList account. You can try again below."
                retryUrl={route('anilist.redirect')}
            />
        </AppLayout>
    );
}
