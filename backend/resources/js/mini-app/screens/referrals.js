/**
 * Referrals: code, shareable link, counts and team list (GET /referrals).
 * Sharing uses the native Telegram share sheet via the WebApp API.
 */

import { api } from '../api.js';
import { el, esc, money, skeleton, errorState, toast } from '../ui.js';
import { haptic, shareUrl } from '../telegram.js';
import { enableBack } from '../router.js';

export async function renderReferrals(view) {
    enableBack('home');
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/referrals');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(errorState(error.message, () => load(view)));
        return;
    }

    view.innerHTML = `
        <h1 class="py-2 text-xl font-bold">Referrals</h1>
        <div class="ma-card text-center">
            <div class="ma-hint text-xs uppercase tracking-wider">Your code</div>
            <div class="mt-1 text-2xl font-bold tracking-widest">${esc(data.code)}</div>
            <div class="ma-hint mt-1 text-xs">Earn ${money(data.earned_usd)} so far from your team</div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">Direct</div><div class="mt-0.5 text-lg font-bold">${data.direct_count}</div></div>
            <div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">Indirect</div><div class="mt-0.5 text-lg font-bold">${data.indirect_count}</div></div>
        </div>
        <button class="ma-btn ma-btn-primary mt-3" id="share"><i class="ri-share-forward-line"></i> Invite friends</button>
        <button class="ma-btn mt-2" id="copy"><i class="ri-link"></i> Copy link</button>
        <h2 class="ma-muted mb-1 mt-5 text-sm font-bold uppercase tracking-wide">Direct team</h2>
        <div id="direct"></div>
        <h2 class="ma-muted mb-1 mt-4 text-sm font-bold uppercase tracking-wide">Indirect team</h2>
        <div id="indirect"></div>`;

    const link = data.link;

    view.querySelector('#share').addEventListener('click', () => {
        haptic('light');
        shareUrl(link, 'Join me on Nextel — earn from every call. Use my code: ' + data.code);
    });

    view.querySelector('#copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(link);
            haptic('success');
            toast('Link copied', 'success');
        } catch {
            toast('Could not copy — long-press the link instead', 'error');
        }
    });

    fillMembers(view.querySelector('#direct'), data.direct ?? []);
    fillMembers(view.querySelector('#indirect'), data.indirect ?? []);
}

function fillMembers(container, members) {
    if (!members.length) {
        container.appendChild(el('<div class="ma-hint py-3 text-sm">No one here yet.</div>'));
        return;
    }

    members.forEach((member) => {
        const name = [member.first_name, member.last_name].filter(Boolean).join(' ') || member.username;

        container.appendChild(el(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-user-line"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${esc(name)}</div>
                    <div class="ma-hint text-xs">@${esc(member.username)}</div>
                </div>
                <span class="ma-chip ${member.package_name ? 'ma-chip-success' : 'ma-chip-muted'}">${esc(member.package_name ?? 'Joined')}</span>
            </div>`));
    });
}

