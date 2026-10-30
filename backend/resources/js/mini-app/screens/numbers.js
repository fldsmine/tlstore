/**
 * Beneficiaries — mirrors the web Beneficiaries page: the user's allocated
 * Nextel numbers (GET /numbers) with package/additional source and expiry
 * state. Live calling itself stays in the web call screen.
 */

import { api } from '../api.js';
import { el, esc, dateLabel, skeleton, errorState, emptyState, appBar, toast, errMsg } from '../ui.js';
import { enableBack } from '../router.js';
import { haptic } from '../telegram.js';

export async function renderNumbers(view) {
    enableBack('wallet');

    view.innerHTML = '';
    view.appendChild(appBar('Beneficiaries'));
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/numbers');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Beneficiaries'));
        view.appendChild(errorState(errMsg(error), () => renderNumbers(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('Beneficiaries'));

    const numbers = data.numbers ?? [];

    if (!numbers.length) {
        view.appendChild(emptyState('ri-phone-line', 'No numbers yet', 'Activate an eSIM plan to receive your Nextel number.'));
        return;
    }

    const active = numbers.filter((number) => !number.is_expired);
    const expired = numbers.filter((number) => number.is_expired);

    const shell = el('<div></div>');
    view.appendChild(shell);

    shell.appendChild(el(`
        <div class="ma-card ma-card-tight mb-3 flex items-center justify-between">
            <span class="text-[0.82rem] font-semibold"><i class="ri-phone-line mr-1" style="color:var(--ma-primary-light)"></i>${active.length} active number${active.length === 1 ? '' : 's'}</span>
            <span class="ma-hint text-[0.7rem]">Live calls run from the web app</span>
        </div>`));

    [...active, ...expired].forEach((number) => {
        const row = el(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-phone-fill"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold tracking-wide">${esc(number.number)}</div>
                    <div class="ma-hint text-xs">
                        ${number.source === 'package' ? 'Package number' : 'Additional number'}
                        ${number.expires_at ? ` · ${number.is_expired ? 'expired' : 'until'} ${esc(dateLabel(number.expires_at))}` : ' · permanent'}
                    </div>
                </div>
                <span class="ma-chip ${number.is_expired ? 'ma-chip-danger' : 'ma-chip-success'}">${number.is_expired ? 'Expired' : 'Active'}</span>
                <button class="ma-icon" style="width:2.25rem;height:2.25rem" data-copy aria-label="Copy number"><i class="ri-file-copy-line"></i></button>
            </div>`);

        row.querySelector('[data-copy]').addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(number.number);
                haptic('success');
                toast('Number copied', 'success', 1500);
            } catch {
                toast(number.number, 'info', 2500);
            }
        });

        shell.appendChild(row);
    });
}


