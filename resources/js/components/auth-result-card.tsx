import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { CheckCircle, XCircle } from 'lucide-react';

interface AuthResultCardProps {
    success: boolean;
    message: string;
    serviceName: string;
    successDescription: string;
    failureDescription: string;
    retryUrl: string;
}

export function AuthResultCard({ success, message, serviceName, successDescription, failureDescription, retryUrl }: AuthResultCardProps) {
    return (
        <div className="flex flex-1 items-center justify-center p-4">
            <Card className="w-full max-w-md">
                <CardHeader className="items-center text-center">
                    {success ? (
                        <CheckCircle className="size-12 text-green-500" />
                    ) : (
                        <XCircle className="size-12 text-destructive" />
                    )}
                    <CardTitle className="text-xl">
                        {success ? `${serviceName} Connected` : 'Connection Failed'}
                    </CardTitle>
                    <CardDescription>{message}</CardDescription>
                </CardHeader>
                <CardContent className="text-center">
                    <p className="text-muted-foreground text-sm">
                        {success ? successDescription : failureDescription}
                    </p>
                </CardContent>
                <CardFooter className="justify-center gap-3">
                    <Button variant="outline" asChild>
                        <Link href="/dashboard">Dashboard</Link>
                    </Button>
                    {!success && (
                        <Button asChild>
                            <Link href={retryUrl}>Try Again</Link>
                        </Button>
                    )}
                </CardFooter>
            </Card>
        </div>
    );
}
