import type { Response } from '@playwright/test';

export function isLivewireUpdate(response: Response): boolean {
    return response.request().method() === 'POST'
        && isLivewireUpdateUrl(new URL(response.url()));
}

export function isLivewireUpdateUrl(url: URL): boolean {
    return /\/livewire-[^/]+\/update$/.test(url.pathname);
}
