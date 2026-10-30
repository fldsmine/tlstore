/**
 * Live Chats — mirrors the web Chats area: conversation list (GET /chats),
 * opening a thread with an agent (POST /chats), message history with
 * light polling (GET /chats/{id}/messages), sending (POST) and marking read.
 */

import { api } from '../api.js';
import { el, esc, skeleton, errorState, emptyState, toast, when, errMsg, appBar } from '../ui.js';
import { enableBack } from '../router.js';
import { state } from '../store.js';
import { haptic } from '../telegram.js';

let pollTimer = null;

export async function renderChats(view) {
    enableBack('services');
    stopPolling();
    await loadThreads(view);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

/* ── Thread list ──────────────────────────────────────────────────── */

async function loadThreads(view) {
    stopPolling();
    enableBack('services');

    view.innerHTML = '';
    view.appendChild(appBar('Live Chats'));
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/chats');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Live Chats'));
        view.appendChild(errorState(errMsg(error), () => loadThreads(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('Live Chats'));

    const shell = el('<div></div>');
    view.appendChild(shell);

    // New conversation.
    const openCard = el(`
        <div class="ma-card mb-4">
            <div class="text-sm font-bold">Start a conversation</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Enter the username of your vendor or a Nextel agent.</div>
            <div class="mt-2 flex gap-2">
                <input class="ma-input flex-1" data-username placeholder="@username (no @)">
                <button class="ma-btn ma-btn-primary ma-btn-auto" data-open>Open</button>
            </div>
        </div>`);

    openCard.querySelector('[data-open]').addEventListener('click', async () => {
        const username = openCard.querySelector('[data-username]').value.trim().replace(/^@/, '');
        if (!username) return toast('Enter the agent’s username.', 'error');

        const button = openCard.querySelector('[data-open]');
        button.disabled = true;

        try {
            const result = await api('/chats', { method: 'POST', body: { agent_username: username } });
            haptic('success');
            openThread(view, result.chat);
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
        }
    });

    shell.appendChild(openCard);

    const chats = data.chats ?? [];

    if (!chats.length) {
        shell.appendChild(emptyState('ri-chat-3-line', 'No conversations yet', 'Open one above — replies arrive right here.'));
        return;
    }

    shell.appendChild(el('<h3 class="ma-heading mb-2 text-base font-bold">Conversations</h3>'));

    chats.forEach((chat) => {
        const other = chat.other ?? {};
        const unread = chat.unread_count ?? 0;

        const row = el(`
            <button class="ma-card mb-2 flex w-full items-center gap-3 text-left">
                ${other.avatar_url
                    ? `<img src="${esc(other.avatar_url)}" alt="" class="h-11 w-11 rounded-full object-cover">`
                    : `<span class="ma-icon">${other.is_agent ? '<i class="ri-customer-service-2-line"></i>' : '<i class="ri-user-3-line"></i>'}</span>`}
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">${esc(other.full_name ?? other.username ?? 'Conversation')}</span>
                    <span class="ma-hint block truncate text-[0.72rem]">@${esc(other.username ?? '')}</span>
                </span>
                <span class="flex flex-col items-end gap-1">
                    <span class="ma-hint text-[0.65rem]">${esc(when(chat.last_message_at))}</span>
                    ${unread ? `<span class="ma-chip ma-chip-accent">${unread} new</span>` : ''}
                </span>
            </button>`);

        row.addEventListener('click', () => {
            haptic('light');
            openThread(view, chat);
        });

        shell.appendChild(row);
    });
}

/* ── Conversation ─────────────────────────────────────────────────── */

async function openThread(view, chat) {
    stopPolling();
    enableBack(null);

    const other = chat.other ?? {};
    const title = esc(other.full_name ?? other.username ?? 'Chat');

    view.innerHTML = '';
    view.appendChild(appBar(title, other.is_agent ? 'Agent' : null));

    const back = el('<button class="ma-btn ma-btn-ghost ma-btn-auto mb-2"><i class="ri-arrow-left-line"></i> All chats</button>');
    back.addEventListener('click', () => loadThreads(view));
    view.appendChild(back);

    const scroll = el('<div class="ma-card mb-3 flex min-h-[40vh] flex-col gap-2 overflow-y-auto" style="max-height:52vh"></div>');
    view.appendChild(scroll);

    const composer = el(`
        <div class="flex gap-2" style="padding-bottom:0.25rem">
            <input class="ma-input flex-1" data-body placeholder="Write a message…" maxlength="5000">
            <button class="ma-btn ma-btn-primary ma-btn-auto" data-send aria-label="Send"><i class="ri-send-plane-fill"></i></button>
        </div>`);
    view.appendChild(composer);

    const input = composer.querySelector('[data-body]');
    const sendButton = composer.querySelector('[data-send]');
    let messages = [];

    const paint = () => {
        scroll.innerHTML = '';
        messages.forEach((message) => scroll.appendChild(bubble(message)));
        scroll.scrollTop = scroll.scrollHeight;
    };

    const refresh = async () => {
        try {
            const data = await api(`/chats/${chat.id}/messages?per_page=60`);
            messages = [...(data.items ?? [])].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            paint();
        } catch {
            if (!messages.length) scroll.appendChild(errorState('Could not load messages.', refresh));
        }
    };

    const send = async () => {
        const body = input.value.trim();
        if (!body) return;

        sendButton.disabled = true;

        try {
            const result = await api(`/chats/${chat.id}/messages`, { method: 'POST', body: { body } });
            input.value = '';
            haptic('light');
            if (result?.message) messages.push(result.message);
            paint();
            await refresh();
        } catch (error) {
            toast(errMsg(error), 'error');
        } finally {
            sendButton.disabled = false;
        }
    };

    sendButton.addEventListener('click', send);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') send();
    });

    scroll.appendChild(spinnerRow());
    await refresh();

    // Mark read + gentle polling while this thread is open.
    api(`/chats/${chat.id}/read`, { method: 'POST' }).catch(() => {});
    pollTimer = setInterval(refresh, 6000);
}

function bubble(message) {
    if (message.type === 'system') {
        return el(`<div class="ma-hint my-1 text-center text-[0.7rem]">${esc(message.body)}</div>`);
    }

    // Sender values are fixed roles; "mine" depends on the logged-in user's role.
    const isMine = state.user?.is_agent ? message.sender === 'agent' : message.sender === 'customer';

    return el(`
        <div class="flex ${isMine ? 'justify-end' : 'justify-start'}">
            <div class="ma-bubble ${isMine ? 'ma-bubble-me' : 'ma-bubble-them'}">
                ${esc(message.body)}
                ${message.attachment_url ? `<a href="${esc(message.attachment_url)}" class="mt-1 block underline" target="_blank" rel="noopener">${esc(message.attachment_name ?? 'Attachment')}</a>` : ''}
                <div class="mt-1 text-right text-[0.6rem] opacity-60">${esc(when(message.created_at))}</div>
            </div>
        </div>`);
}

function spinnerRow() {
    return el('<div class="flex justify-center py-6"><div class="ma-spinner"></div></div>');
}
