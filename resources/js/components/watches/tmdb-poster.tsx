import { posterUrl, type PosterSize } from '@/lib/tmdb';
import { cn } from '@/lib/utils';
import { Tv } from 'lucide-react';

interface TmdbPosterProps {
    path: string | null;
    alt: string;
    size?: PosterSize;
    className?: string;
}

export function TmdbPoster({ path, alt, size = 'w185', className }: TmdbPosterProps) {
    const url = posterUrl(path, size);

    if (!url) {
        return (
            <div className={cn('flex items-center justify-center rounded bg-muted', className)}>
                <Tv className="size-6 text-muted-foreground" />
            </div>
        );
    }

    return <img src={url} alt={alt} loading="lazy" className={cn('rounded object-cover', className)} />;
}
