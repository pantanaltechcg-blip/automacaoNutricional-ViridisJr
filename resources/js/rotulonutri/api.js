import { normalizeApiError } from './errors.js';

const DEFAULT_TIMEOUT_MS = 15000;

/**
 * Executa requisição JSON contra os endpoints /api existentes.
 * @param {string} url
 * @param {{ method?: string, body?: unknown, signal?: AbortSignal, timeoutMs?: number }} [options]
 * @returns {Promise<{ ok: true, status: number, data: any } | { ok: false, status: number, error: ReturnType<typeof normalizeApiError> }>}
 */
export async function requestJson(url, { method = 'GET', body = undefined, signal = undefined, timeoutMs = DEFAULT_TIMEOUT_MS } = {}) {
    const controller = new AbortController();
    let timedOut = false;

    const onExternalAbort = () => controller.abort();
    if (signal) {
        if (signal.aborted) {
            return { ok: false, status: 0, error: normalizeApiError({ aborted: true }) };
        }
        signal.addEventListener('abort', onExternalAbort, { once: true });
    }

    const timer = setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, timeoutMs);

    try {
        const headers = { Accept: 'application/json' };
        const init = { method, headers, signal: controller.signal };

        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }

        const res = await fetch(url, init);
        let parsed = null;
        try {
            parsed = await res.json();
        } catch {
            parsed = null;
        }

        if (!res.ok) {
            return {
                ok: false,
                status: res.status,
                error: normalizeApiError({ status: res.status, body: parsed }),
            };
        }

        return { ok: true, status: res.status, data: parsed };
    } catch (err) {
        if (err && err.name === 'AbortError' && !timedOut) {
            return { ok: false, status: 0, error: normalizeApiError({ aborted: true }) };
        }
        return {
            ok: false,
            status: 0,
            error: normalizeApiError({ status: 0, networkError: true }),
        };
    } finally {
        clearTimeout(timer);
        if (signal) {
            signal.removeEventListener('abort', onExternalAbort);
        }
    }
}
