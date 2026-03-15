import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Deferred, Head, Link, useForm, usePage } from '@inertiajs/react';
import { Film, Tv } from 'lucide-react';
import { FormEventHandler } from 'react';

import { ConnectionCard, type ConnectionStatus } from '@/components/connection-card';
import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { PlexConnectionCard } from '@/components/plex-connection-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

interface ProfileProps {
    mustVerifyEmail: boolean;
    status?: string;
    connections: {
        tmdb_has_token: boolean;
        trakt_has_token: boolean;
        plex_account_id: number | null;
    };
    connectionVerification?: {
        tmdb: boolean;
        trakt: boolean;
    };
}

function resolveConnectionStatus(hasToken: boolean, verified?: boolean): ConnectionStatus {
    if (!hasToken) return 'disconnected';
    if (verified === undefined) return 'verifying';
    if (verified) return 'connected';
    return 'error';
}

function ServiceConnectionCards({
    connections,
    connectionVerification,
}: {
    connections: ProfileProps['connections'];
    connectionVerification?: ProfileProps['connectionVerification'];
}) {
    const tmdbStatus = resolveConnectionStatus(connections.tmdb_has_token, connectionVerification?.tmdb);
    const traktStatus = resolveConnectionStatus(connections.trakt_has_token, connectionVerification?.trakt);

    return (
        <div className="space-y-3">
            <ConnectionCard
                label="TMDB"
                description="Sync movies and TV shows to your TMDB lists"
                icon={Film}
                status={tmdbStatus}
                connectUrl={route('tmdb.redirect')}
                disconnectUrl={route('tmdb.disconnect')}
            />

            <ConnectionCard
                label="Trakt"
                description="Sync your watch history to Trakt"
                icon={Tv}
                status={traktStatus}
                connectUrl={route('trakt.redirect')}
                disconnectUrl={route('trakt.disconnect')}
            />

            <PlexConnectionCard plexAccountId={connections.plex_account_id} />
        </div>
    );
}

export default function Profile({ mustVerifyEmail, status, connections, connectionVerification }: ProfileProps) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Profile information" description="Update your name and email address" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Full name"
                            />

                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>

                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="Email address"
                            />

                            <InputError className="mt-2" message={errors.email} />
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div>
                                <p className="text-muted-foreground -mt-4 text-sm">
                                    Your email address is unverified.{' '}
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    >
                                        Click here to resend the verification email.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to your email address.
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>

                <div className="space-y-6">
                    <HeadingSmall title="Connected services" description="Connect your accounts to enable syncing and tracking" />

                    <Deferred
                        data="connectionVerification"
                        fallback={<ServiceConnectionCards connections={connections} />}
                    >
                        <ServiceConnectionCards connections={connections} connectionVerification={connectionVerification} />
                    </Deferred>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
