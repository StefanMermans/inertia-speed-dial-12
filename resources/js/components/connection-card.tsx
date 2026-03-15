import { Button } from '@/components/ui/button';
import { Link, router } from '@inertiajs/react';
import { CheckCircle, LucideIcon, Unplug } from 'lucide-react';

interface ConnectionCardProps {
    service: string;
    label: string;
    description: string;
    icon: LucideIcon;
    connected: boolean;
    connectUrl: string;
    disconnectUrl: string;
}

export function ConnectionCard({ service, label, description, icon: Icon, connected, connectUrl, disconnectUrl }: ConnectionCardProps) {
    function handleDisconnect() {
        router.delete(disconnectUrl, { preserveScroll: true });
    }

    return (
        <div className="flex items-center justify-between rounded-lg border p-4">
            <div className="flex items-center gap-3">
                <div className="bg-muted flex size-10 items-center justify-center rounded-lg">
                    <Icon className="text-muted-foreground size-5" />
                </div>
                <div>
                    <p className="font-medium">{label}</p>
                    <p className="text-muted-foreground text-sm">
                        {connected ? (
                            <span className="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <CheckCircle className="size-3.5" />
                                Connected
                            </span>
                        ) : (
                            description
                        )}
                    </p>
                </div>
            </div>
            <div>
                {connected ? (
                    <Button variant="outline" size="sm" onClick={handleDisconnect}>
                        <Unplug className="size-4" />
                        Disconnect
                    </Button>
                ) : (
                    <Button size="sm" asChild>
                        <Link href={connectUrl}>Connect</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
