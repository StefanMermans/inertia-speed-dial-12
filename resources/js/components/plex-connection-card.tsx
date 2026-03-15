import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/react';
import { CheckCircle, MonitorPlay } from 'lucide-react';
import { FormEventHandler } from 'react';

interface PlexConnectionCardProps {
    plexAccountId: number | null;
}

export function PlexConnectionCard({ plexAccountId }: PlexConnectionCardProps) {
    const { data, setData, patch, processing, recentlySuccessful, errors } = useForm({
        plex_account_id: plexAccountId?.toString() ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('connections.plex.update'), { preserveScroll: true });
    };

    const connected = plexAccountId !== null;

    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center gap-3">
                <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg">
                    <MonitorPlay className="text-muted-foreground size-5" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="font-medium">Plex</p>
                    <p className="text-muted-foreground text-sm">
                        {connected ? (
                            <span className="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <CheckCircle className="size-3.5" />
                                Connected
                            </span>
                        ) : (
                            'Link your Plex account to track watches'
                        )}
                    </p>
                </div>
            </div>
            <form onSubmit={submit} className="mt-3 flex flex-col gap-2">
                <div className="flex items-center gap-2">
                    <Input
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        placeholder="Plex Account ID"
                        value={data.plex_account_id}
                        onChange={(e) => setData('plex_account_id', e.target.value)}
                        className="flex-1"
                    />
                    <Button size="sm" disabled={processing}>
                        {recentlySuccessful ? 'Saved' : 'Save'}
                    </Button>
                </div>
                <InputError message={errors.plex_account_id} />
            </form>
        </div>
    );
}
