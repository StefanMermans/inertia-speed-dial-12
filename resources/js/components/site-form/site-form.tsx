import Button from '@/components/controls/button';
import ColorPicker from '@/components/controls/colorPicker';
import Input from '@/components/controls/input';
import { Site as SiteComponent } from '@/components/speed-dial/site';
import { cn } from '@/lib/utils';
import { type Site } from '@/pages/speed-dial';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ComponentProps, FormEvent, FormEventHandler, useMemo } from 'react';

type Props = {
    site?: Site;
    creating: boolean;
};

type SiteFormData = Pick<Site, 'name' | 'url' | 'background_color'> & {
    icon?: File;
};

export default function SiteForm({ site, creating }: Props) {
    const { data, setData, errors, clearErrors, post } = useForm<SiteFormData>('SiteForm/create', {
        name: site?.name ?? '',
        url: site?.url ?? '',
        background_color: site?.background_color ?? '',
    });

    const create = () => {
        post(route('sites.store'), {
            forceFormData: true,
        });
    };

    const update = () => {
        if (!site) {
            return;
        }

        post(
            route('sites.update', {
                site: site.id,
            }),
            {
                method: 'put',
                forceFormData: true,
            },
        );
    };

    const save = () => {
        clearErrors();

        if (creating || !site) {
            create();
        } else {
            update();
        }
    };

    const destroy = async () => {
        if (!site) {
            return;
        }

        if (confirm('Are you sure you want to delete this site?')) {
            await axios.delete(route('sites.destroy', { site: site.id }));
            router.visit(route('speed-dial'));
        }
    };

    const submit: FormEventHandler = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        save();
    };

    const sitePreview: ComponentProps<typeof SiteComponent>['site'] = useMemo(() => {
        const icon_path = data.icon && URL.createObjectURL(data.icon);

        return {
            ...data,
            icon_url: icon_path ?? site?.icon_url,
        };
    }, [data, site?.icon_url]);

    return (
        <form onSubmit={submit} id='site-form' className="flex flex-col space-y-4 p-4">
            <div className={cn('flex w-full justify-center rounded-md p-4', 'bg-[url(/background-compressed.jpg)]')}>
                <SiteComponent editable={false} site={sitePreview} />
            </div>
            <label htmlFor="name">Name</label>
            <Input name="name" className="text-black" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
            {errors.name && <div className="text-red-500">{errors.name}</div>}
            <label htmlFor="url">Url</label>
            <Input name="url" className="text-black" type="text" value={data.url} onChange={(e) => setData('url', e.target.value)} />
            {errors.url && <div className="text-red-500">{errors.url}</div>}
            <label htmlFor="color" className="flex gap-2">
                <ColorPicker
                    name="color"
                    className="text-black"
                    value={data.background_color}
                    onChange={(e) => setData('background_color', e.target.value)}
                />
                <span>Background Color</span>
            </label>
            {errors.background_color && <div className="text-red-500">{errors.background_color}</div>}
            <label htmlFor="icon">Icon</label>
            <Input name="icon" className="mt-4 text-black" type="file" onChange={(e) => setData('icon', e.target.files?.item(0) ?? undefined)} />
            {errors.icon && <div className="text-red-500">{errors.icon}</div>}
            <div className="mt-4 flex gap-4">
                {site && (
                    <Button type="button" variant="danger" onClick={destroy}>
                        Delete
                    </Button>
                )}
                <Button className="self-end" type="submit">
                    Save
                </Button>
            </div>
        </form>
    );
}
