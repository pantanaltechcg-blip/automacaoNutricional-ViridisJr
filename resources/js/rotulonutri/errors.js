/**
 * Normaliza respostas de erro da API (422, 5xx, rede/timeout) para a UI.
 * @param {{ status?: number, body?: any, networkError?: boolean, aborted?: boolean }} response
 * @returns {{ kind: 'validation'|'server'|'network'|'client'|'aborted', message: string, retryable: boolean, fields: Array<{ field: string, message: string }> }}
 */
export function normalizeApiError({ status = 0, body = null, networkError = false, aborted = false } = {}) {
    if (aborted) {
        return { kind: 'aborted', message: '', retryable: false, fields: [] };
    }

    if (networkError || status === 0) {
        return {
            kind: 'network',
            message: 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
            retryable: true,
            fields: [],
        };
    }

    if (status === 422 && body && typeof body.errors === 'object' && body.errors !== null) {
        const fields = [];
        for (const [field, messages] of Object.entries(body.errors)) {
            if (Array.isArray(messages) && messages.length > 0) {
                fields.push({ field, message: String(messages[0]) });
            } else if (typeof messages === 'string') {
                fields.push({ field, message: messages });
            }
        }
        return {
            kind: 'validation',
            message: typeof body.message === 'string' && body.message !== ''
                ? body.message
                : 'Verifique os campos destacados e tente novamente.',
            retryable: false,
            fields,
        };
    }

    if (status >= 500) {
        return {
            kind: 'server',
            message: 'Ocorreu um erro interno no servidor. Você pode tentar novamente.',
            retryable: true,
            fields: [],
        };
    }

    const fallbackMsg = body && typeof body.message === 'string' && body.message !== ''
        ? body.message
        : `Falha na requisição (código ${status}).`;

    return {
        kind: 'client',
        message: fallbackMsg,
        retryable: false,
        fields: [],
    };
}
