import { requestJson } from '../api.js';
import { extractLabelReadiness, renderDeclaredTable } from './table-format.js';

export async function init() {
    const root = document.getElementById('label-app');
    if (!root) {
        return;
    }

    const declaredId = root.dataset.declaredId;
    const banner = document.getElementById('label-readiness-banner');
    const tableEl = document.querySelector('[data-nutrition-table]');

    if (!declaredId) {
        return;
    }

    async function loadDeclared() {
        const res = await requestJson(`/api/declared-nutrition-calculations/${encodeURIComponent(declaredId)}`);
        if (!res.ok) {
            if (banner) {
                banner.classList.remove('hidden');
                const msg = document.createElement('span');
                msg.textContent = res.error.message;
                banner.replaceChildren(msg);
                if (res.error.retryable) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = 'Tentar novamente';
                    btn.className = 'ml-3 rounded-lg border border-current px-3 py-1 text-xs font-medium cursor-pointer';
                    btn.addEventListener('click', loadDeclared);
                    banner.appendChild(btn);
                }
            }
            return;
        }

        const declared = res.data?.data;
        renderDeclaredTable(tableEl, declared);

        const readiness = extractLabelReadiness(declared);
        if (!readiness.ready && banner) {
            banner.classList.remove('hidden');
            const title = document.createElement('p');
            title.className = 'font-semibold';
            title.textContent = 'Resultado não apto à rotulagem.';
            banner.replaceChildren(title);
            readiness.causes.forEach((c) => {
                const p = document.createElement('p');
                p.className = 'mt-1 text-rn-muted';
                p.textContent = c;
                banner.appendChild(p);
            });
        }
    }

    await loadDeclared();
}
