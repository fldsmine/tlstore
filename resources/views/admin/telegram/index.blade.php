@extends('admin.layout')
@section('content')
@php
    $statusBadge = fn (bool $on) => $on ? 'green' : '';
@endphp
<style>
    .tg-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:16px;align-items:start}
    .tg-toolbar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
    .tg-tool{background:#edf4ef;color:var(--green);border:1px solid var(--line);border-radius:8px;padding:5px 9px;font-size:11px;font-weight:800;cursor:pointer}
    .tg-tool:hover{background:#e2efe7}
    .tg-bubble{background:#17212b;color:#f5f5f5;border-radius:14px;padding:13px 15px;min-height:150px;white-space:pre-wrap;word-break:break-word;font-size:13px;line-height:1.55}
    .tg-bubble a{color:#64b5f6;text-decoration:underline}
    .tg-bubble code{background:rgba(255,255,255,.12);border-radius:4px;padding:1px 4px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}
    .tg-bubble pre{background:rgba(255,255,255,.08);border-radius:8px;padding:8px;overflow:auto;white-space:pre-wrap}
    .tg-bubble b,.tg-bubble strong{color:#fff}
    .tg-bubble img{max-width:100%;border-radius:10px;display:block;margin-bottom:9px}
    .tg-empty{color:#8ba3b5;font-style:italic}
    .tg-meta{display:flex;justify-content:space-between;gap:10px;margin-top:6px;font-size:10px;color:var(--muted)}
    .tg-counter.warn{color:#b45309;font-weight:800}
    .tg-counter.over{color:#b42318;font-weight:800}
    .tg-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:100px;background:#eff3f0;font-size:11px;font-weight:800;color:#58675f;margin:0 6px 6px 0}
    .tg-chip.on{background:#e6f5ec;color:#08734f}
    .tg-chip.off{background:#fff0ef;color:#b42318}
    .tg-help{font-size:11px;color:var(--muted);line-height:1.55}
    .tg-help code{background:#f1f5f2;border-radius:4px;padding:1px 5px}
    .tg-thumb{width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--line);display:block}
    @media(max-width:1100px){.tg-grid{grid-template-columns:1fr}}
</style>

<div class="eyebrow">Platform messages</div>
<div class="section-head">
    <div>
        <h2 class="page-title">Telegram messaging</h2>
        <p class="page-subtitle">Send announcements to the platform's Telegram group or channel, and review the notifications the platform posts automatically.</p>
    </div>
    <div>
        <span class="tg-chip {{ $configured ? 'on' : 'off' }}">{{ $configured ? '● Bot configured' : '● Not configured' }}</span>
        <span class="tg-chip {{ $enabled ? 'on' : 'off' }}">{{ $enabled ? 'Notifications on' : 'Notifications off' }}</span>
    </div>
</div>

@if($lastCheck)
    <div class="flash" style="background:#eef4ff;border-color:#cfdffb;color:#1f4fa3">
        Last connection check: {{ $lastCheck['summary'] ?? 'completed' }}
        @if(!empty($lastCheck['checked_at']))<span class="muted">· {{ \Illuminate\Support\Carbon::parse($lastCheck['checked_at'])->diffForHumans() }}</span>@endif
    </div>
@endif

<section class="card" style="margin-bottom:16px">
    <div class="section-head"><div><h2>Bot configuration</h2><p class="muted" style="margin:4px 0 0">The bot token is encrypted with the application key before it is stored and is never sent back to this page.</p></div></div>
    <form method="POST" action="{{ route('admin.telegram.settings') }}">@csrf @method('PATCH')
        <div class="form-grid">
            <div class="field">
                <label for="bot_token">Telegram bot token</label>
                <input class="input" id="bot_token" name="bot_token" type="password" autocomplete="new-password"
                       placeholder="{{ $maskedToken ?? '123456789:AAH… (from @BotFather)' }}">
                <span class="field-help">
                    {{ $maskedToken ? 'A token is already stored' : 'No token stored yet' }}@if($tokenFromEnvironment) — the server environment also defines one, which is used until a token is saved here.@else.@endif
                    Leave this field blank to keep the current token.
                </span>
            </div>
            <div class="field">
                <label for="chat_id">Destination chat id (group or channel)</label>
                <input class="input" id="chat_id" name="chat_id" value="{{ old('chat_id', $chatId) }}" placeholder="-1001234567890 or @tlstorechannel">
                <span class="field-help">Numeric ids work for private groups and channels; <code>@username</code> works for public channels. See the guide below for finding the id.</span>
            </div>
            <div class="field full">
                <label class="checkline"><input type="checkbox" name="enabled" value="1" @checked($enabled)> Enable Telegram notifications</label>
                <span class="field-help">Master switch. When off, nothing is posted to Telegram, including automatic withdrawal notifications.</span>
            </div>
            <div class="field">
                <label class="checkline"><input type="checkbox" name="announcements_enabled" value="1" @checked($announcementsEnabled)> Admin announcements</label>
                <span class="field-help">Allows the composer on this page to post to the chat.</span>
            </div>
            <div class="field">
                <label class="checkline"><input type="checkbox" name="withdrawals_enabled" value="1" @checked($withdrawalsEnabled)> Withdrawal notifications</label>
                <span class="field-help">Posts “NEW WITHDRAWAL REQUEST” with the generated receipt whenever a user submits a withdrawal.</span>
            </div>
            <div class="field">
                <label class="checkline"><input type="checkbox" name="status_updates_enabled" value="1" @checked($statusUpdatesEnabled)> Withdrawal status updates</label>
                <span class="field-help">Posts when a withdrawal is marked paid or rejected.</span>
            </div>
        </div>
        <div class="row-actions" style="margin-top:16px"><button class="button">Save Telegram settings</button></div>
    </form>

    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;padding-top:15px;border-top:1px solid var(--line)">
        <form method="POST" action="{{ route('admin.telegram.test-connection') }}" class="inline-form">@csrf<button class="button secondary" @disabled(!$configured)>Test connection</button></form>
        <form method="POST" action="{{ route('admin.telegram.test-message') }}" class="inline-form">@csrf<button class="button secondary" @disabled(!$configured)>Send test message</button></form>
        <span class="tg-help" style="align-self:center">Both actions post to the saved destination, so save your changes first.</span>
    </div>
</section>

<div class="tg-grid">
    <section class="card">
        <div class="section-head"><div><h2>Compose announcement</h2><p class="muted" style="margin:4px 0 0">Text-only or with one image. Telegram formatting: <code>&lt;b&gt;</code>, <code>&lt;i&gt;</code>, <code>&lt;u&gt;</code>, <code>&lt;s&gt;</code>, <code>&lt;a href="…"&gt;</code>, <code>&lt;code&gt;</code>, <code>&lt;pre&gt;</code>.</p></div></div>
        <form method="POST" action="{{ route('admin.telegram.send') }}" enctype="multipart/form-data" id="telegram-composer">@csrf
            <div class="tg-toolbar" aria-label="Formatting">
                <button type="button" class="tg-tool" data-wrap="b">Bold</button>
                <button type="button" class="tg-tool" data-wrap="i">Italic</button>
                <button type="button" class="tg-tool" data-wrap="u">Underline</button>
                <button type="button" class="tg-tool" data-wrap="s">Strike</button>
                <button type="button" class="tg-tool" data-wrap="code">Code</button>
                <button type="button" class="tg-tool" id="tg-insert-link">Link</button>
                <button type="button" class="tg-tool" data-insert="&#10;">Line break</button>
            </div>
            <div class="field">
                <label class="field-help" for="message" style="font-weight:800;color:var(--ink)">Message text</label>
                <textarea class="textarea" id="message" name="message" rows="9" required maxlength="{{ $maxLength }}"
                          data-max="{{ $maxLength }}" data-caption-max="{{ $captionMaxLength }}"
                          placeholder="🎉 <b>New payout window</b>&#10;Withdrawals are processed within 24 hours.&#10;Read more: <a href=&quot;https://tlstore.org&quot;>tlstore.org</a>">{{ old('message') }}</textarea>
            </div>
            <div class="tg-meta">
                <span class="tg-help">Line breaks are kept as typed. Unsupported tags are removed before sending.</span>
                <span class="tg-counter" id="tg-counter">0 / {{ $maxLength }}</span>
            </div>
            <div class="field" style="margin-top:10px">
                <label for="image">Attach an image (optional)</label>
                <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                <span class="field-help">JPG, PNG or WebP up to 5 MB. With an image the caption is limited to {{ $captionMaxLength }} characters.</span>
            </div>
            <div class="row-actions" style="margin-top:16px">
                <button class="button" @disabled(!$configured || !$enabled || !$announcementsEnabled)>Send message</button>
                @if(!$configured)<span class="tg-help">Save a bot token and chat id first.</span>@elseif(!$enabled)<span class="tg-help">Enable Telegram notifications first.</span>@elseif(!$announcementsEnabled)<span class="tg-help">Enable admin announcements first.</span>@endif
            </div>
        </form>
    </section>

    <section class="card">
        <div class="section-head"><div><h2>Preview</h2><p class="muted" style="margin:4px 0 0">Approximation of the message in Telegram.</p></div></div>
        <div class="tg-bubble" id="tg-preview"><span class="tg-empty">Your message will appear here…</span></div>
        <div class="tg-help" style="margin-top:12px">
            <strong>Formatting tips</strong><br>
            <code>&lt;b&gt;bold&lt;/b&gt;</code> · <code>&lt;i&gt;italic&lt;/i&gt;</code> · <code>&lt;u&gt;underline&lt;/u&gt;</code> · <code>&lt;s&gt;strike&lt;/s&gt;</code><br>
            <code>&lt;a href="https://tlstore.org"&gt;link text&lt;/a&gt;</code><br>
            <code>&lt;code&gt;inline code&lt;/code&gt;</code> · <code>&lt;pre&gt;block&lt;/pre&gt;</code><br>
            Press Enter for a line break.
        </div>
    </section>
</div>

<section class="card" style="margin-top:16px">
    <div class="section-head"><div><h2>Delivery history</h2><p class="muted" style="margin:4px 0 0">Every announcement, test message and automatic notification, newest first.</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sent</th><th>Type</th><th>Message</th><th>Status</th><th>By</th><th></th></tr></thead>
            <tbody>
            @forelse($messages as $message)
                <tr>
                    <td>
                        <strong>{{ ($message->sent_at ?? $message->created_at)?->format('d M Y, H:i') }}</strong>
                        <div class="muted">{{ ($message->sent_at ?? $message->created_at)?->diffForHumans() }}</div>
                    </td>
                    <td><span class="badge">{{ $message->kindLabel() }}</span></td>
                    <td style="max-width:340px">
                        <div>{{ $message->excerpt(110) }}</div>
                        @if($message->hasAttachment())
                            <div style="margin-top:6px">
                                @if($message->attachment_type === 'photo')
                                    <a href="{{ route('admin.telegram.image', $message) }}" target="_blank" rel="noopener">
                                        <img class="tg-thumb" src="{{ route('admin.telegram.image', $message) }}" alt="Announcement image">
                                    </a>
                                @else
                                    <span class="badge gold">Receipt attached ({{ $message->attachment_type }})</span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        @php
                            $badge = match ($message->status) {
                                'sent' => 'green',
                                'failed' => 'red',
                                'sending' => 'gold',
                                default => '',
                            };
                        @endphp
                        <span class="badge {{ $badge }}">{{ ucfirst($message->status) }}</span>
                        @if($message->error)
                            <div class="muted" style="max-width:260px;margin-top:5px">{{ \Illuminate\Support\Str::limit($message->error, 120) }}</div>
                        @endif
                    </td>
                    <td class="muted">{{ $message->admin?->full_name ?? 'Automated' }}</td>
                    <td>
                        @if($message->status !== 'sent')
                            <form method="POST" action="{{ route('admin.telegram.resend', $message) }}" class="inline-form">@csrf<button class="button secondary small" @disabled(!$configured)>Re-send</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">Nothing has been sent to Telegram yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination"><span>Showing {{ $messages->firstItem() ?? 0 }}–{{ $messages->lastItem() ?? 0 }} of {{ $messages->total() }}</span>{{ $messages->links() }}</div>
</section>

<section class="card" style="margin-top:16px">
    <div class="section-head"><div><h2>Setup guide</h2><p class="muted" style="margin:4px 0 0">Everything the integration needs, in order.</p></div></div>
    <div class="tg-help">
        <ol style="margin:0;padding-left:18px">
            <li>Open <b>@BotFather</b> in Telegram → <code>/newbot</code> → choose a name and username → copy the token it returns. Send one message to the new bot (or add it to the group) so it is allowed to post.</li>
            <li>Create the destination group or channel. Add the bot to it and promote it to <b>administrator</b> (channels need “Post messages”; groups only need to stay a member).</li>
            <li>For a <b>public channel</b>, use <code>@channelusername</code> as the destination id.</li>
            <li>For a <b>private group or channel</b>, use the numeric id: open <code>https://web.telegram.org</code>, select the chat and read the number in the URL (<code>…#-1001234567890</code>), or add <b>@RawDataBot</b> to the group, read <code>chat.id</code>, then remove it. Ids always start with <code>-100</code>.</li>
            <li>Paste the token and destination above, save, then press <b>Test connection</b> and <b>Send test message</b>.</li>
            <li>Turn on the automatic notifications you want. Withdrawals post the generated receipt as soon as the request is recorded; failures are retried in the background and appear here with a <span class="badge red">Failed</span> badge.</li>
        </ol>
        <p style="margin-top:10px">Queue deliveries in production run through the platform queue: keep <code>php artisan queue:work</code> (or your supervisor service) running so automatic notifications leave the server.</p>
    </div>
</section>

<script>
(function () {
    var input = document.getElementById('message');
    var preview = document.getElementById('tg-preview');
    var counter = document.getElementById('tg-counter');
    var imageInput = document.getElementById('image');
    var max = Number(input.dataset.max || 4096);
    var captionMax = Number(input.dataset.captionMax || 1024);
    var imageUrl = null;

    function escapeHtml(value) {
        return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Escapes everything first, then re-enables only the tags Telegram accepts,
    // so pasted markup can never execute in the console.
    function toTelegram(raw) {
        var text = escapeHtml(raw);
        text = text.replace(/&lt;a\s+href=&quot;(https?:\/\/[^\s&]+)&quot;&gt;([\s\S]*?)&lt;\/a&gt;/gi,
            function (match, href, label) {
                return '<a href="' + href + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
            });
        text = text.replace(/&lt;(\/?)(b|strong|i|em|u|s|strike|del|code|pre)&gt;/gi, '<$1$2>');
        text = text.replace(/&lt;br\s*\/?&gt;/gi, '<br>');
        return text;
    }

    function render() {
        var raw = input.value;
        var html = toTelegram(raw);
        var image = imageInput.files && imageInput.files[0];
        var prefix = '';

        if (image) {
            if (imageUrl) { URL.revokeObjectURL(imageUrl); }
            imageUrl = URL.createObjectURL(image);
            prefix = '<img src="' + imageUrl + '" alt="Attached image preview">';
        }

        preview.innerHTML = (html.trim() === '' && !image)
            ? '<span class="tg-empty">Your message will appear here…</span>'
            : prefix + html;

        var limit = image ? captionMax : max;
        var visible = raw.replace(/<[^>]*>/g, '').length;
        counter.textContent = visible + ' / ' + limit + (image ? ' (caption)' : '');
        counter.className = 'tg-counter' + (visible > limit ? ' over' : (visible > limit * 0.9 ? ' warn' : ''));
    }

    function wrap(tag) {
        var start = input.selectionStart, end = input.selectionEnd;
        var value = input.value;
        var selected = value.slice(start, end) || 'text';
        var replacement = '<' + tag + '>' + selected + '</' + tag + '>';
        input.value = value.slice(0, start) + replacement + value.slice(end);
        input.selectionStart = start + tag.length + 2;
        input.selectionEnd = input.selectionStart + selected.length;
        input.focus();
        render();
    }

    input.addEventListener('input', render);
    imageInput.addEventListener('change', render);

    document.querySelectorAll('[data-wrap]').forEach(function (button) {
        button.addEventListener('click', function () { wrap(button.dataset.wrap); });
    });
    document.querySelectorAll('[data-insert]').forEach(function (button) {
        button.addEventListener('click', function () {
            var start = input.selectionStart;
            input.value = input.value.slice(0, start) + '\n' + input.value.slice(input.selectionEnd);
            input.selectionStart = input.selectionEnd = start + 1;
            input.focus();
            render();
        });
    });
    document.getElementById('tg-insert-link').addEventListener('click', function () {
        var start = input.selectionStart, end = input.selectionEnd;
        var value = input.value;
        var label = value.slice(start, end) || 'link text';
        var replacement = '<a href="https://tlstore.org">' + label + '</a>';
        input.value = value.slice(0, start) + replacement + value.slice(end);
        input.selectionStart = start;
        input.selectionEnd = start + replacement.length;
        input.focus();
        render();
    });

    render();
})();
</script>
@endsection
