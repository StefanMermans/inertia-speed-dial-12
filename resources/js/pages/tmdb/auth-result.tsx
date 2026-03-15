import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle, XCircle } from 'lucide-react';

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
            <div className="flex flex-1 items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="items-center text-center">
                        {success ? (
                            <CheckCircle className="size-12 text-green-500" />
                        ) : (
                            <XCircle className="size-12 text-destructive" />
                        )}
                        <CardTitle className="text-xl">
                            {success ? 'TMDB Connected' : 'Connection Failed'}
                        </CardTitle>
                        <CardDescription>{message}</CardDescription>
                    </CardHeader>
                    <CardContent className="text-center">
                        {success ? (
                            <p className="text-muted-foreground text-sm">
                                You can now add movies and TV shows to your TMDB lists.
                            </p>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                Something went wrong while connecting your TMDB account. You can try again below.
                            </p>
                        )}
                    </CardContent>
                    <CardFooter className="justify-center gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/dashboard">Dashboard</Link>
                        </Button>
                        {!success && (
                            <Button asChild>
                                <Link href="/tmdb/auth">Try Again</Link>
                            </Button>
                        )}
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}
