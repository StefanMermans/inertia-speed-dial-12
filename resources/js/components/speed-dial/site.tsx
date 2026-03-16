import { cn } from '@/lib/utils';
import { Site as SiteType } from '@/pages/speed-dial';
import { CSSProperties, useMemo } from 'react';

type Props = {
    clickable?: boolean;
    editable: boolean;
    site: Partial<Pick<SiteType, 'background_color' | 'no_padding' | 'id' | 'url' | 'name' | 'icon_url'>>;
};

export const Site = ({ site, editable }: Props) => {
    const style = useMemo(
        (): CSSProperties => ({
            backgroundColor: site.background_color || 'white',
        }),
        [site.background_color],
    );

    const url = editable
        ? route('speed-dial', {
              site: site.id,
          })
        : site.url;

    return (
        <a
            id={`site-${site.id}`}
            href={url}
            className={cn(
                'block h-24 w-24 transform cursor-pointer overflow-hidden rounded-2xl shadow-md transition-transform hover:scale-110 hover:shadow-xl',
                { 'p-2': !site.no_padding },
                {
                    'bg-slate-700 hover:outline-4 hover:outline-offset-4 hover:outline-white hover:outline-dashed': editable,
                },
            )}
            style={style}
        >
            <img src={`${site.icon_url}`} alt={`${site.name} logo`} />
        </a>
    );
};
