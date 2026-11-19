import { Button } from '@/components/ui/button';
import { Link, router } from '@inertiajs/react';
import { CircleCheckBig, LoaderCircle, LucideIcon, Unplug } from 'lucide-react';

export type ConnectionStatus = 'disconnected' | 'verifying' | 'connected' | 'error';

interface ConnectionCardProps {
    label: string;
    description: string;
    icon: LucideIcon;
    status: ConnectionStatus;
    connectUrl: string;
    disconnectUrl: string;
}

export function ConnectionCard({ label, description, icon: Icon, status, connectUrl, disconnectUrl }: ConnectionCardProps) {
    function handleDisconnect() {
        router.delete(disconnectUrl, { preserveScroll: true });
    }

    return (
        <div className="flex items-center justify-between rounded-lg border p-4">
            <div className="flex items-center gap-3">
                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                    <Icon className="size-5 text-muted-foreground" />
                </div>
                <div>
                    <p className="font-medium">{label}</p>
                    <p className="text-sm text-muted-foreground">
                        {status === 'connected' && (
                            <span className="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <CircleCheckBig className="size-3.5" />
                                Connected
                            </span>
                        )}
                        {status === 'verifying' && (
                            <span className="flex items-center gap-1">
                                <LoaderCircle className="size-3.5 animate-spin" />
                                Verifying connection...
                            </span>
                        )}
                        {status === 'disconnected' && description}
                        {status === 'error' && (
                            <span className="flex flex-col gap-0.5">
                                <span>{description}</span>
                                <span className="text-red-600 dark:text-red-400">Failed to verify connection</span>
                            </span>
                        )}
                    </p>
                </div>
            </div>
            <div>
                {status === 'connected' ? (
                    <Button variant="outline" size="sm" onClick={handleDisconnect}>
                        <Unplug className="size-4" />
                        Disconnect
                    </Button>
                ) : status === 'verifying' ? null : (
                    <Button size="sm" asChild>
                        <Link href={connectUrl}>Connect</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
