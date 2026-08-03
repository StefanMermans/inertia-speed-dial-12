export type PosterSize = 'w92' | 'w185' | 'w342';

/**
 * TMDB serves images from a separate CDN host to the API, and only ever returns the
 * path portion, so the host and size have to be prefixed client side.
 */
export function posterUrl(path: string | null | undefined, size: PosterSize = 'w185'): string | null {
    return path ? `https://image.tmdb.org/t/p/${size}${path}` : null;
}
