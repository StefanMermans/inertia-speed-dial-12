import { type SeriesProgress } from '@/types';
import { SeriesProgressCard } from './series-progress-card';

interface WatchSectionProps {
    title: string;
    description?: string;
    series: SeriesProgress[];
    onFixMatch: (series: SeriesProgress) => void;
}

export function WatchSection({ title, description, series, onFixMatch }: WatchSectionProps) {
    if (series.length === 0) {
        return null;
    }

    return (
        <section className="mt-8 first:mt-0">
            <div className="mb-3 flex items-baseline gap-2">
                <h2 className="text-lg font-semibold">{title}</h2>
                <span className="text-muted-foreground text-sm tabular-nums">{series.length}</span>
            </div>

            {description && <p className="text-muted-foreground -mt-2 mb-3 text-sm">{description}</p>}

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {series.map((item) => (
                    <SeriesProgressCard key={item.id} series={item} onFixMatch={onFixMatch} />
                ))}
            </div>
        </section>
    );
}
