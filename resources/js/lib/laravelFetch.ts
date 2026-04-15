/**
 * Browser fetch configured for Laravel session + JSON endpoints.
 * Sends cookies, XSRF header, and X-Requested-With like Inertia/axios.
 */
export function getCsrfTokenFromCookie(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function refreshXsrfCookie(): Promise<void> {
    await fetch(window.location.pathname, { method: 'GET', credentials: 'same-origin' });
}

export async function laravelFetch(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
    const headers = new Headers(init.headers ?? undefined);
    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json');
    }
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('X-XSRF-TOKEN', getCsrfTokenFromCookie());

    const doRequest = (): Promise<Response> =>
        fetch(input, {
            ...init,
            credentials: 'same-origin',
            headers,
        });

    let res = await doRequest();
    if (res.status === 419) {
        await refreshXsrfCookie();
        res = await doRequest();
    }

    return res;
}
