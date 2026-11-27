@extends('admin.layout')
@section('content')
<div class="eyebrow">Message template · {{ $template->act }}</div>
<div class="section-head"><div><h2 class="page-title">{{ $template->name }}</h2><p class="page-subtitle">Edit the content and channel switches for this event.</p></div><a class="button secondary" href="{{ route('admin.notifications.index') }}">← Templates</a></div>
<section class="card" style="margin-bottom:16px"><h2 style="margin-top:0;font-size:15px">Available shortcodes</h2><div class="table-wrap"><table><thead><tr><th>Shortcode</th><th>Description</th></tr></thead><tbody>@forelse($template->shortcodes??[] as $code=>$description)<tr><td class="mono">@{{ $code }}</td><td>{{ $description }}</td></tr>@empty<tr><td colspan="2" class="muted">No shortcodes available.</td></tr>@endforelse</tbody></table></div></section>
<form class="card" method="POST" action="{{ route('admin.notifications.templates.update',$template) }}">@csrf @method('PATCH')
    <div class="form-grid">
        <div class="field"><label>Display name</label><input class="input" name="name" maxlength="120" required value="{{ old('name',$template->name) }}"></div>
        <div class="field"><label>Email subject</label><input class="input" name="subject" maxlength="191" value="{{ old('subject',$template->subject) }}"></div>
        <div class="field full">
            <label>Email design</label>
            <div class="email-editor" data-email-editor>
                <div class="editor-toolbar" role="toolbar" aria-label="Email formatting tools">
                    <button type="button" data-command="bold"><b>B</b></button><button type="button" data-command="italic"><i>I</i></button><button type="button" data-command="underline"><u>U</u></button>
                    <span class="editor-divider"></span>
                    <button type="button" data-block="h2">Heading</button><button type="button" data-block="p">Paragraph</button>
                    <button type="button" data-command="insertUnorderedList">• List</button><button type="button" data-command="insertOrderedList">1. List</button>
                    <span class="editor-divider"></span>
                    <button type="button" data-link>Link</button><button type="button" data-command="unlink">Unlink</button>
                    <button type="button" data-command="justifyLeft">Left</button><button type="button" data-command="justifyCenter">Center</button>
                    <button type="button" data-command="formatBlock" data-value="blockquote">Quote</button>
                    <button type="button" data-source-toggle>HTML source</button><button type="button" data-preview-toggle>Preview</button>
                </div>
                <div class="email-editor-canvas" contenteditable="true" data-editor-canvas aria-label="Email template editor">{!! $editorHtml !!}</div>
                <textarea class="textarea email-source" data-editor-source aria-label="HTML source" spellcheck="false"></textarea>
                <iframe class="email-preview" data-editor-preview title="Email preview" sandbox=""></iframe>
                <textarea name="email_body" data-editor-value hidden>{{ old('email_body',$template->email_body) }}</textarea>
            </div>
            <span class="field-help">Format the email visually or switch to HTML source. Shortcodes such as <code>@{{ fullname }}</code> remain available. The system places this content inside the standard TLStore email layout.</span>
        </div>
        <div class="field full"><label>Push body</label><textarea class="textarea" style="min-height:90px" name="firebase_body" maxlength="5000">{{ old('firebase_body',$template->firebase_body) }}</textarea></div>
        <div class="field full"><label>SMS body (reserved for later)</label><textarea class="textarea" style="min-height:90px" name="sms_body" maxlength="5000">{{ old('sms_body',$template->sms_body) }}</textarea><span class="field-help">This content is saved for future SMS integration. No SMS will be sent.</span></div>
        <div class="field"><label class="checkline"><input type="checkbox" name="email_status" value="1" @checked(old('email_status',$template->email_status))> Email active</label></div>
        <div class="field"><label class="checkline"><input type="checkbox" name="firebase_status" value="1" @checked(old('firebase_status',$template->firebase_status))> Push active</label></div>
        <div class="field"><label class="checkline"><input type="checkbox" name="sms_status" value="1" @checked(old('sms_status',$template->sms_status))> SMS option enabled (not delivered)</label></div>
    </div>
    <div class="row-actions" style="margin-top:18px"><button class="button">Save template</button><a class="button secondary" href="{{ route('admin.notifications.index') }}">Cancel</a></div>
</form>
<style>
.email-editor{border:1px solid #cbd5d0;border-radius:12px;overflow:hidden;background:#fff;color:#172b23}.editor-toolbar{display:flex;flex-wrap:wrap;gap:6px;padding:9px;background:#f2f6f3;border-bottom:1px solid #dce5df}.editor-toolbar button{border:1px solid #d2ddd6;background:#fff;color:#26372f;border-radius:6px;padding:6px 9px;cursor:pointer}.editor-toolbar button:hover{background:#e7f2eb}.editor-divider{width:1px;background:#cbd5d0;margin:2px 3px}.email-editor-canvas,.email-source{box-sizing:border-box;width:100%;min-height:340px;padding:20px;border:0;outline:none;background:#fff;color:#172b23}.email-editor-canvas:focus{box-shadow:inset 0 0 0 2px #2b8b63}.email-source{display:none;font:13px/1.5 ui-monospace,monospace;white-space:pre;resize:vertical}.email-preview{display:none;width:100%;height:420px;border:0;background:#f1f5f2}.email-editor.source-mode .email-editor-canvas{display:none}.email-editor.source-mode .email-source{display:block}.email-editor.preview-mode .email-editor-canvas,.email-editor.preview-mode .email-source{display:none}.email-editor.preview-mode .email-preview{display:block}
</style>
<script>
(() => {
    const editor = document.querySelector('[data-email-editor]');
    if (!editor) return;
    const canvas = editor.querySelector('[data-editor-canvas]');
    const source = editor.querySelector('[data-editor-source]');
    const value = editor.querySelector('[data-editor-value]');
    const preview = editor.querySelector('[data-editor-preview]');
    const sample = (html) => html.replace(/\{\{\s*fullname\s*\}\}/g, 'Taylor Reviewer').replace(/\{\{\s*username\s*\}\}/g, 'taylor').replace(/\{\{\s*email\s*\}\}/g, 'taylor@example.com').replace(/\{\{\s*site_name\s*\}\}/g, 'TLStore').replace(/\{\{\s*site_url\s*\}\}/g, 'https://tlstore.org').replace(/\{\{\s*title\s*\}\}/g, 'A message from TLStore').replace(/\{\{\s*message\s*\}\}/g, 'Your notification message will appear here.');
    const currentHtml = () => editor.classList.contains('source-mode') ? source.value : canvas.innerHTML;
    const sync = () => { value.value = currentHtml(); };
    const refreshPreview = () => {
        const body = sample(currentHtml());
        preview.srcdoc = '<!doctype html><html><body style="margin:0;background:#f1f5f2;color:#132d23;font:15px/1.65 Arial,sans-serif;padding:28px 14px"><main style="max-width:620px;margin:auto;background:#fff;border:1px solid #dce6df;border-radius:16px;padding:30px"><div style="font-weight:800;font-size:18px;color:#08734f;margin-bottom:20px">TLStore</div>'+body+'<div style="border-top:1px solid #e6ece8;margin-top:24px;padding-top:14px;color:#64756d;font-size:12px">TLStore · Review · Share · Earn</div></main></body></html>';
    };
    editor.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => {
        if (editor.classList.contains('source-mode')) return;
        canvas.focus(); document.execCommand(button.dataset.command, false, button.dataset.value || null); sync();
    }));
    editor.querySelectorAll('[data-block]').forEach(button => button.addEventListener('click', () => {
        if (editor.classList.contains('source-mode')) return;
        canvas.focus(); document.execCommand('formatBlock', false, button.dataset.block); sync();
    }));
    editor.querySelector('[data-link]').addEventListener('click', () => {
        if (editor.classList.contains('source-mode')) return;
        const url = prompt('Enter the link URL (https://, mailto: or tel:)');
        if (url && /^(https?:\/\/|mailto:|tel:)/i.test(url)) { canvas.focus(); document.execCommand('createLink', false, url); sync(); }
    });
    editor.querySelector('[data-source-toggle]').addEventListener('click', event => {
        if (!editor.classList.contains('source-mode')) { source.value = canvas.innerHTML; editor.classList.add('source-mode'); event.currentTarget.textContent = 'Visual editor'; }
        else { canvas.innerHTML = source.value; editor.classList.remove('source-mode'); event.currentTarget.textContent = 'HTML source'; }
        sync();
    });
    editor.querySelector('[data-preview-toggle]').addEventListener('click', event => {
        if (!editor.classList.contains('preview-mode')) { sync(); refreshPreview(); editor.classList.add('preview-mode'); event.currentTarget.textContent = 'Back to editor'; }
        else { editor.classList.remove('preview-mode'); event.currentTarget.textContent = 'Preview'; }
    });
    canvas.addEventListener('input', sync); source.addEventListener('input', sync);
    editor.closest('form').addEventListener('submit', sync);
    sync();
})();
</script>
@endsection
