const DECIMAL_RE = /^(\d+)(?:\.(\d+))?$/;

/**
 * Normaliza entrada decimal (troca vírgula por ponto, trim).
 * Retorna '' para vazio e null se inválido ou notação científica.
 * @param {string|null|undefined} raw
 * @returns {string|null}
 */
export function normalizeDecimalInput(raw) {
    if (raw === null || raw === undefined) {
        return '';
    }
    const trimmed = String(raw).trim();
    if (trimmed === '') {
        return '';
    }
    const normalized = trimmed.replace(',', '.');
    if (!DECIMAL_RE.test(normalized)) {
        return null;
    }
    return normalized;
}

/**
 * Converte string decimal válida em { int: BigInt, scale: number }.
 * @param {string} normalized
 * @returns {{ int: bigint, scale: number }}
 */
function parseScaled(normalized) {
    const [, whole, frac = ''] = DECIMAL_RE.exec(normalized);
    return {
        int: BigInt(whole + frac),
        scale: frac.length,
    };
}

/**
 * Formata BigInt escalado removendo zeros à direita na parte fracionária.
 * @param {bigint} value
 * @param {number} scale
 * @returns {string}
 */
function formatScaled(value, scale) {
    if (scale === 0) {
        return value.toString();
    }
    const str = value.toString().padStart(scale + 1, '0');
    const splitAt = str.length - scale;
    const whole = str.slice(0, splitAt);
    const frac = str.slice(splitAt).replace(/0+$/, '');
    return frac === '' ? whole : `${whole}.${frac}`;
}

/**
 * @param {string|null|undefined} raw
 * @returns {boolean}
 */
export function isPositiveDecimal(raw) {
    const normalized = normalizeDecimalInput(raw);
    if (!normalized) {
        return false;
    }
    return parseScaled(normalized).int > 0n;
}

/**
 * Soma lista de strings decimais usando BigInt (sem ponto flutuante).
 * Valores vazios ou inválidos são ignorados na soma informativa.
 * @param {Array<string|null|undefined>} values
 * @returns {string}
 */
export function addDecimalStrings(values) {
    const parsed = [];
    let maxScale = 0;

    for (const raw of values) {
        const normalized = normalizeDecimalInput(raw);
        if (!normalized) {
            continue;
        }
        const item = parseScaled(normalized);
        parsed.push(item);
        if (item.scale > maxScale) {
            maxScale = item.scale;
        }
    }

    if (parsed.length === 0) {
        return '0';
    }

    let total = 0n;
    for (const item of parsed) {
        const factor = 10n ** BigInt(maxScale - item.scale);
        total += item.int * factor;
    }

    return formatScaled(total, maxScale);
}

/**
 * Incremento local de 1 g; campo vazio ou inválido parte de 0 -> "1".
 * @param {string|null|undefined} raw
 * @returns {string}
 */
export function incrementByOne(raw) {
    const normalized = normalizeDecimalInput(raw);
    if (!normalized) {
        return '1';
    }
    const { int, scale } = parseScaled(normalized);
    const one = 10n ** BigInt(scale);
    return formatScaled(int + one, scale);
}

/**
 * Decremento local de 1 g; retorna null se o resultado for <= 0.
 * @param {string|null|undefined} raw
 * @returns {string|null}
 */
export function decrementByOnePositive(raw) {
    const normalized = normalizeDecimalInput(raw);
    if (!normalized) {
        return null;
    }
    const { int, scale } = parseScaled(normalized);
    const one = 10n ** BigInt(scale);
    const next = int - one;
    if (next <= 0n) {
        return null;
    }
    return formatScaled(next, scale);
}
