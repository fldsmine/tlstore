/**
 * Share-to-earn ads: list active campaigns (GET /ads), open the advertiser
 * page, then record the share (POST /ads/{id}/share) which credits the Ads
 * Revenue wallet server-side.
 */

import { api, ApiError } from '../api.js';
import { el, esc, money, skeleton, errorState, emptyState, toast } from '../ui.js';
import { haptic, openLink } from '../telegram.js';
import { enableBack } from '../router.js';

export async function renderAds(view) {
    enableBack('home');
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/ads');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(errorState(error.message, () => load(view)));
        return;
    }

    view.innerHTML = `
        <h1 class="py-2 text-xl font-bold">Share &amp; Earn</h1>
        <div class="grid grid-cols-3 gap-2">
            ${stat('Today', data.today_usd)}
            ${stat('This week', data.week_usd)}
            ${stat('Lifetime', data.lifetime_usd)}
        </div>
        <div id="ads" class="mt-4"></div>`;

    const list = view.querySelector('#ads');

    if (data.enabled === false) {
        list.appendChild(emptyState('ri-megaphone-line', 'Ads are paused', 'Check back later — sharing will resume soon.'));
        return;
    }

    const ads = data.ads ?? [];

    if (!ads.length) {
        list.appendChild(emptyState('ri-megaphone-line', 'No ads right now', 'New campaigns appear here when advertisers launch them.'));
        return;
    }

    ads.forEach((ad) => list.appendChild(buildAd(ad, view)));
}

function stat(label, value) {
    return `<div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">${label}</div><div class="mt-0.5 text-sm font-bold">${money(value)}</div></div>`;
}

function buildAd(ad, view) {
    const earned = Boolean(ad.earned_current);

    const card = el(`
        <div class="ma-card mb-3">
            <div class="flex items-start gap-3">
                ${ad.image_url ? `<img src="${esc(ad.image_url)}" alt="" class="h-12 w-12 flex-none rounded-xl object-cover" loading="lazy">` : '<div class="ma-icon"><i class="ri-megaphone-line"></i></div>'}
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${esc(ad.name)}</div>
                    <div class="ma-hint text-xs">${esc(ad.advertiser ?? '')}</div>
                    <div class="mt-1 text-xs font-bold" style="color:var(--ma-success)">+${money(ad.reward_usd)}</div>
                </div>
                ${earned ? '<span class="ma-chip ma-chip-success">Earned</span>' : ''}
            </div>
            ${ad.description ? `<p class="ma-hint mt-2 text-xs">${esc(ad.description)}</p>` : ''}
            <div class="mt-3 grid grid-cols-2 gap-2">
                ${ad.target_url ? `<button class="ma-btn ma-btn-sm" data-open><i class="ri-external-link-line"></i> View</button>` : ''}
                <button class="ma-btn ma-btn-primary ma-btn-sm" data-share ${earned ? 'disabled' : ''}>
                    ${earned ? 'Already earned' : 'I shared it'}
                </button>
            </div>
        </div>`);

    card.querySelector('[data-open]')?.addEventListener('click', () => openLink(ad.target_url));

    const shareButton = card.querySelector('[data-share]');

    if (!earned) {
        shareButton.addEventListener('click', async () => {
            shareButton.disabled = true;
            shareButton.textContent = 'Recording…';

            try {
                const result = await api(`/ads/${ad.id}/share`, { method: 'POST' });
                haptic('success');
                toast(`+${money(result.amount_usd)} added to Ads Revenue`, 'success');
                await load(view);
            } catch (error) {
                const message = error instanceof ApiError ? (error.fieldMessage() ?? error.message) : error.message;
                toast(message, 'error');
                shareButton.disabled = false;
                shareButton.textContent = 'I shared it';
            }
        });
    }

    return card;
}
