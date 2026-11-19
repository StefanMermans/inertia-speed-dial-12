import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { type SeriesProgress } from '@/types';
import { Link2, TriangleExclamationPoint } from 'lucide-react';
import { TmdbPoster } from './tmdb-poster';

interface SeriesProgressCardProps {
    series: SeriesProgress;
    onFixMatch: (series: SeriesProgress) => void;
}

function episodeLabel(series: SeriesProgress): string | null {
    if (series.last_season_number === null || series.last_episode_number === null) {
        return null;
    }

    const season = String(series.last_season_number).padStart(2, '0');
    const episode = String(series.last_episode_number).padStart(2, '0');

    return `S${season}E${episode}`;
}

function relativeDate(iso: string): string {
    const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);

    if (days <= 0) {
        return 'today';
    }

    if (days === 1) {
        return 'yesterday';
    }

    if (days < 30) {
        return `${days} days ago`;
    }

    const months = Math.floor(days / 30);

    if (months < 12) {
        return `${months} ${months === 1 ? 'month' : 'months'} ago`;
    }

    const years = Math.floor(months / 12);

    return `${years} ${years === 1 ? 'year' : 'years'} ago`;
}

export function SeriesProgressCard({ series, onFixMatch }: SeriesProgressCardProps) {
    const percent = series.progress === null ? null : Math.round(series.progress * 100);
    const marker = episodeLabel(series);
    const isCaughtUp = series.status === 'completed' && series.tmdb_status === 'Returning Series';

    return (
        <div className="bg-card flex gap-4 rounded-xl border p-4">
            <TmdbPoster path={series.poster_path} alt={series.title} size="w185" className="h-32 w-22 shrink-0" />

            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="truncate font-medium" title={series.title}>
                        {series.title}
                    </h3>
                    {series.year && <span className="text-muted-foreground shrink-0 text-sm">{series.year}</span>}
                </div>

                <p className="text-muted-foreground mt-0.5 text-sm">
                    {marker && <span className="text-foreground font-medium">{marker}</span>}
                    {marker && ' · '}
                    {relativeDate(series.last_watched_at)}
                </p>

                <div className="mt-auto pt-3">
                    {percent === null ? (
                        <Button variant="outline" size="sm" onClick={() => onFixMatch(series)}>
                            <Link2 className="mr-1.5 size-3.5" />
                            Link to TMDB
                        </Button>
                    ) : (
                        <>
                            <Progress value={percent} aria-label={`${percent}% of ${series.title} watched`} />
                            <div className="mt-2 flex items-center justify-between gap-2">
                                <span className="text-muted-foreground text-sm">
                                    {series.watched_episodes} / {series.total_episodes} episodes
                                </span>
                                <span className="text-muted-foreground text-sm font-medium tabular-nums">
                                    {isCaughtUp ? 'Caught up' : `${percent}%`}
                                </span>
                            </div>
                        </>
                    )}

                    {series.needs_review && (
                        <button
                            type="button"
                            onClick={() => onFixMatch(series)}
                            className="mt-2 inline-flex cursor-pointer"
                            aria-label={`Check the TMDB match for ${series.title}`}
                        >
                            <Badge variant="outline" className="gap-1 font-normal">
                                <TriangleExclamationPoint className="size-3" />
                                Check match
                            </Badge>
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
