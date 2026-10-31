export type CsrfMode = 'xsrf' | 'both' | 'none';

export interface JsonRequestOptions extends Omit<
    RequestInit,
    'body' | 'headers'
> {
    body?: unknown;
    headers?: Record<string, string>;
    csrf?: CsrfMode;
    requestedWith?: boolean;
}

type JsonFetcher = (
    input: RequestInfo | URL,
    init?: RequestInit,
) => Promise<Pick<Response, 'ok' | 'status' | 'statusText' | 'json'>>;

function getCsrfToken(): string {
    if (typeof document === 'undefined') return '';
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    if (cookie) return decodeURIComponent(cookie.split('=')[1] ?? '');

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export async function requestJson<T = Record<string, unknown>>(
    input: RequestInfo | URL,
    options: JsonRequestOptions = {},
    fetcher: JsonFetcher = fetch,
): Promise<{
    ok: boolean;
    status: number;
    statusText: string;
    data: T;
}> {
    const {
        body,
        csrf = options.method && options.method !== 'GET' ? 'xsrf' : 'none',
        requestedWith = false,
        headers: suppliedHeaders = {},
        ...requestOptions
    } = options;
    const headers: Record<string, string> = {
        Accept: 'application/json',
        ...suppliedHeaders,
    };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (requestedWith) headers['X-Requested-With'] = 'XMLHttpRequest';

    const token = csrf === 'none' ? '' : getCsrfToken();
    if (token) {
        headers['X-XSRF-TOKEN'] = token;
        if (csrf === 'both') headers['X-CSRF-TOKEN'] = token;
    }

    const response = await fetcher(input, {
        ...requestOptions,
        headers,
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    return {
        ok: response.ok,
        status: response.status,
        statusText: response.statusText,
        data: (await response.json()) as T,
    };
}
