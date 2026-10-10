/**
 * Tiny client-side cache for heavy GET endpoints.
 *  - Successful results are reused for `ttlMs`, so page switches don't refetch them.
 *  - Concurrent callers share one in-flight request instead of firing duplicates.
 *  - Failures are never cached; the next caller retries.
 *  - Entries are keyed per login token, and any write request clears everything
 *    (see the axios interceptor), so users never see stale or someone else's data.
 */
type Entry = { value: unknown; expires: number };

const store = new Map<string, Entry>();
const inFlight = new Map<string, Promise<unknown>>();

const scoped = (key: string) => `${localStorage.getItem('userToken') ?? 'anon'}::${key}`;

export async function cached<T>(
    key: string,
    ttlMs: number,
    fetcher: () => Promise<T>,
    // Not typed as T, so T is inferred from the fetcher alone.
    isSuccess: (value: unknown) => boolean = () => true,
): Promise<T> {
    const fullKey = scoped(key);
    const hit = store.get(fullKey);
    if (hit && hit.expires > Date.now()) return hit.value as T;

    const pending = inFlight.get(fullKey);
    if (pending) return pending as Promise<T>;

    const request = fetcher()
        .then(value => {
            if (isSuccess(value)) store.set(fullKey, { value, expires: Date.now() + ttlMs });
            return value;
        })
        .finally(() => inFlight.delete(fullKey));

    inFlight.set(fullKey, request);
    return request;
}

/** Drop one cached endpoint (all users), e.g. before a forced refresh. */
export function invalidate(key: string): void {
    for (const fullKey of store.keys()) {
        if (fullKey.endsWith(`::${key}`)) store.delete(fullKey);
    }
}

export function clearRequestCache(): void {
    store.clear();
}
