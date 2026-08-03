import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { type SeriesProgress } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { TmdbSeriesSearch, type TmdbSearchResult } from './tmdb-series-search';

interface FixMatchDialogProps {
    series: SeriesProgress | null;
    onOpenChange: (open: boolean) => void;
}

export function FixMatchDialog({ series, onOpenChange }: FixMatchDialogProps) {
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function selectMatch(result: TmdbSearchResult) {
        if (!series) {
            return;
        }

        setSubmitting(true);
        setError(null);

        router.post(
            route('watches.fix-match', { series: series.id }),
            { tmdb_id: result.id },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errors) => setError(errors.tmdb_id ?? 'That show could not be linked. Try again.'),
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function handleOpenChange(open: boolean) {
        if (!open) {
            setError(null);
        }

        onOpenChange(open);
    }

    return (
        <Dialog open={series !== null} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Link “{series?.title}” to TMDB</DialogTitle>
                    <DialogDescription>
                        Pick the correct show. Episode totals and artwork will refresh in the background once it is linked.
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <p role="alert" className="text-destructive text-sm">
                        {error}
                    </p>
                )}

                {series && <TmdbSeriesSearch key={series.id} initialQuery={series.title} onSelect={selectMatch} disabled={submitting} />}
            </DialogContent>
        </Dialog>
    );
}
