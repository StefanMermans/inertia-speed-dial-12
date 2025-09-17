import { cn } from '@/lib/utils';
import { Site as SiteType } from '@/pages/speed-dial';
import { CSSProperties, useMemo } from 'react';

type SiteButtonSite = Partial<
        Pick<
            SiteType,
            | 'background_color'
            | 'no_padding'
            | 'id'
            | 'url'
            | 'name'
            | 'icon_url'
        >
    >;

type SiteEditButtonProps = Readonly<{
    className: string;
    onClick: () => void;
    style: CSSProperties;
    site: SiteButtonSite;
}>;

function SiteEditButton({ className, onClick, style, site }: SiteEditButtonProps) {
    return (
          <button
            type="button"
            onClick={onClick}
            className={className}
            style={style}
        >
            <img src={`${site.icon_url}`} alt={`${site.name} logo`} />
        </button>
    );
};

type SiteLinkProps = Readonly<{
    className: string;
    style: CSSProperties;
    site: SiteButtonSite;
}>;

function SiteLink({className, style, site}: SiteLinkProps) {
    return (
        <a href={site.url} className={className} style={style}>
            <img src={`${site.icon_url}`} alt={`${site.name} logo`} />
        </a>
    );
}

type SiteProps = Readonly<{
    editable: boolean;
    onClick?: () => void;
    site: SiteButtonSite;
}>;

export function Site({ site, editable, onClick }: SiteProps) {
    const style = useMemo(
        (): CSSProperties => ({
            backgroundColor: site.background_color || 'white',
        }),
        [site.background_color],
    );
    const className = cn(
                'block h-24 w-24 transform cursor-pointer overflow-hidden rounded-2xl shadow-md transition-transform hover:scale-110 hover:shadow-xl',
                { 'p-2': !site.no_padding },
                {
                    'bg-slate-700 hover:outline-4 hover:outline-offset-4 hover:outline-white hover:outline-dashed':
                        editable,
                },
            );

    if (editable) {
        return <SiteEditButton className={className} onClick={onClick!} style={style} site={site} />;
    }

    return <SiteLink className={className} style={style} site={site} />;
};
