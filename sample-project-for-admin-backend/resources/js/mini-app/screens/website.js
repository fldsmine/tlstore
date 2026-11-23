/**
 * My Website — mirrors the web Website page: subscription status
 * (GET /website), online renewal through the payment checkout
 * (POST /website/renew → /payments/{ref}/finalize) and renewal-code
 * redemption (POST /website/renew/code).
 */

import { api } from '../api.js';
import { el, esc, money, dateLabel, skeleton, errorState, toast, errMsg, appBar } from '../ui.js';
import { enableBack } from '../router.js';
import { openLink } from '../telegram.js';
import { haptic } from '../telegram.js';

export async function renderWebsite(view) {
    enableBack('services');
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(appBar('My Website'));
    view.appendChild(skeleton(3));

    let data;

    try {
        data = await api('/website');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('My Website'));
        view.appendChild(errorState(errMsg(error), () => load(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('My Website'));

    const shell = el('<div></div>');
    view.appendChild(shell);

    // Status card.
    shell.appendChild(el(`
        <div class="ma-card mb-3 text-center">
            <span class="ma-icon mx-auto mb-2" style="width:3.2rem;height:3.2rem;font-size:1.5rem"><i class="ri-global-line"></i></span>
            <div class="ma-heading text-lg font-bold">${data.active ? 'Website is live' : 'Website expired'}</div>
            <div class="ma-hint mt-1 text-[0.78rem]">
                ${data.expires_at
                    ? `${data.active ? 'Renews' : 'Expired'} ${esc(dateLabel(data.expires_at))} · ${data.duration_days}-day term`
                    : 'No active subscription yet.'}
            </div>
            <span class="ma-chip ${data.active ? 'ma-chip-success' : 'ma-chip-danger'} mt-2 inline-flex">${data.active ? 'Active' : 'Inactive'}</span>
        </div>`));

    // Renewal options.
    const online = data.renewal_mode === 'online';

    if (online) {
        const card = el(`
            <div class="ma-card mb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-bold">Renew online</div>
                        <div class="ma-hint text-[0.72rem]">Pay with card — instant activation.</div>
                    </div>
                    <div class="ma-heading text-lg font-bold">${money(data.renewal_price_usd)}</div>
                </div>
                <button class="ma-btn ma-btn-primary mt-3" data-renew><i class="ri-bank-card-line"></i> Pay & renew</button>
            </div>`);

        card.querySelector('[data-renew]').addEventListener('click', async () => {
            const button = card.querySelector('[data-renew]');
            button.disabled = true;
            button.textContent = 'Starting checkout…';

            try {
                const result = await api('/website/renew', { method: 'POST' });
                haptic('light');
                openLink(result.checkout_url);
                button.textContent = 'Waiting for payment…';
                pollFinalize(view, result.reference, button);
            } catch (error) {
                toast(errMsg(error), 'error');
                button.disabled = false;
                button.innerHTML = '<i class="ri-bank-card-line"></i> Pay & renew';
            }
        });

        shell.appendChild(card);
    } else {
        shell.appendChild(el(`
            <div class="ma-card ma-card-tight mb-3">
                <i class="ri-information-line mr-1" style="color:var(--ma-info)"></i>
                <span class="text-[0.78rem]">Online renewal is paused — redeem a code from your vendor below.</span>
            </div>`));
    }

    // Code redemption (always available).
    const codeCard = el(`
        <div class="ma-card mb-3">
            <div class="text-sm font-bold">Renew with a code</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Customer price ${money(data.code_price_usd)} — codes come from Nextel vendors.</div>
            <input class="ma-input mt-3" data-code placeholder="Enter renewal code" autocomplete="off">
            <button class="ma-btn ma-btn-accent mt-2" data-redeem><i class="ri-coupon-3-line"></i> Redeem code</button>
        </div>`);

    codeCard.querySelector('[data-redeem]').addEventListener('click', async () => {
        const code = codeCard.querySelector('[data-code]').value.trim();
        if (code.length < 6) return toast('Enter the renewal code.', 'error');

        const button = codeCard.querySelector('[data-redeem]');
        button.disabled = true;
        button.textContent = 'Redeeming…';

        try {
            const result = await api('/website/renew/code', { method: 'POST', body: { code } });
            haptic('success');
            toast(`Website renewed — now valid until ${dateLabel(result.expires_at)}.`, 'success', 4000);
            load(view);
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
            button.innerHTML = '<i class="ri-coupon-3-line"></i> Redeem code';
        }
    });

    shell.appendChild(codeCard);
}

/** Check the gateway a few times after the user pays in the checkout. */
function pollFinalize(view, reference, button) {
    let attempts = 0;

    const timer = setInterval(async () => {
        attempts += 1;

        try {
            const result = await api(`/payments/${encodeURIComponent(reference)}/finalize`, { method: 'POST' });
            const status = result?.status;

            if (status === 'completed') {
                clearInterval(timer);
                haptic('success');
                toast('Payment received — website renewed! 🎉', 'success', 4000);
                load(view);
                return;
            }

            if (status === 'pending' || status === 'unavailable') {
                clearInterval(timer);
                haptic('success');
                toast('Payment received — your website will activate shortly.', 'success', 4000);
                load(view);
                return;
            }
        } catch {
            /* not paid yet — keep polling */
        }

        if (attempts >= 12) {
            clearInterval(timer);
            button.disabled = false;
            button.innerHTML = '<i class="ri-bank-card-line"></i> Pay & renew';
            toast('Still waiting on the payment — reopen this page after paying.', 'info', 4200);
        }
    }, 5000);
}
