@extends('admin.layout')
@section('content')
<style>
    .settings-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}
    .settings-tab{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border-radius:11px;border:1px solid var(--line);background:#fff;font-weight:800;font-size:12px;color:#3f574c}
    .settings-tab:hover{border-color:#87bea1;color:var(--green)}
    .settings-tab.active{background:var(--green);border-color:var(--green);color:#fff}
    .settings-tab .count{background:rgba(0,0,0,.07);border-radius:100px;padding:1px 7px;font-size:10px;font-weight:800}
    .settings-tab.active .count{background:rgba(255,255,255,.24);color:#fff}
    .settings-card{margin-bottom:16px}
    .field-head{align-items:center}
    .field-badges{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
    .public-chip{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:800;color:var(--muted);background:#f4f7f5;border:1px solid var(--line);border-radius:100px;padding:2px 9px;cursor:pointer}
    .public-chip input{accent-color:var(--green)}
    .input-suffix{display:flex;align-items:center;gap:9px}
    .input-suffix small{font-size:11px;font-weight:800;color:var(--muted);white-space:nowrap}
    .stack{display:grid;gap:8px}
    .kv-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr) auto;gap:8px;align-items:center}
    .toggle-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px 16px}
    .matrix-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}
    .matrix{min-width:0;width:100%}
    .matrix th,.matrix td{padding:8px 10px;font-size:11px;text-align:center;white-space:nowrap}
    .matrix th:first-child,.matrix td:first-child{text-align:left}
    .matrix input[type=checkbox]{accent-color:var(--green);width:16px;height:16px}
    .matrix.campaign-table input.input{padding:6px 8px;font-size:12px;min-width:90px}
    .matrix.campaign-table th,.matrix.campaign-table td{text-align:left;white-space:normal}
    .gw-off{opacity:.55}
    .save-bar{position:sticky;bottom:0;z-index:4;display:flex;justify-content:space-between;align-items:center;gap:12px;background:rgba(255,255,255,.97);border:1px solid var(--line);border-radius:14px;padding:12px 16px;box-shadow:var(--shadow);margin-top:8px}
    .save-bar .muted{font-size:11px}
    .tab-hint{display:flex;gap:10px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;margin:0 0 14px}
    .tab-hint p{margin:0;color:var(--muted);font-size:12px;max-width:760px}
    @media(max-width:760px){.toggle-grid{grid-template-columns:1fr}.kv-row{grid-template-columns:1fr}.save-bar{flex-direction:column;align-items:stretch;position:static}}
</style>

<div class="eyebrow">Platform configuration</div>
<div class="section-head">
    <div>
        <h2 class="page-title">System settings</h2>
        <p class="page-subtitle">Every platform setting with plain controls — switches, numbers, pickers and lists. Values are stored in the exact format the app reads, so nothing breaks when you save.</p>
    </div>
    <div class="row-actions">
        <a class="button secondary small" href="{{ route('admin.modules.index', 'system-settings') }}">Raw records</a>
    </div>
</div>

<nav class="settings-tabs" aria-label="Settings groups">
    @foreach($tabs as $tabId => $tab)
        <a class="settings-tab {{ $tabId === $activeTab ? 'active' : '' }}"
           href="{{ route('admin.settings.index', ['tab' => $tabId]) }}"
           @if($tabId === $activeTab) aria-current="page" @endif>
            <span aria-hidden="true">{{ $tab['icon'] }}</span>{{ $tab['label'] }}
            <span class="count">{{ $tab['count'] }}</span>
        </a>
    @endforeach
</nav>

<div class="tab-hint">
    <p>{{ $activeTabMeta['description'] }}</p>
    @if(! empty($activeTabMeta['link']))
        <a class="button secondary small" href="{{ route($activeTabMeta['link']['route']) }}">{{ $activeTabMeta['link']['label'] }}</a>
    @endif
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
    @csrf
    @method('PATCH')
    <input type="hidden" name="tab" value="{{ $activeTab }}">

    @php
        $sections = [];
        foreach ($fields as $field) {
            $sections[$field['section']][] = $field;
        }
    @endphp

    @forelse($sections as $section => $sectionFields)
        <section class="card settings-card">
            <div class="section-head"><div><h2>{{ $section }}</h2></div></div>
            <div class="form-grid">
                @foreach($sectionFields as $field)
                    @include('admin.settings._field', ['field' => $field])
                @endforeach
            </div>
        </section>
    @empty
        <section class="card settings-card">
            <p class="muted" style="margin:0">There is nothing to edit in this group right now.</p>
        </section>
    @endforelse

    <div class="save-bar">
        <span class="muted">
            {{ count($fields) }} {{ \Illuminate\Support\Str::plural('setting', count($fields)) }} in this group · {{ $settingCount }} stored in total.
            Changes are audited against your admin account.
        </span>
        <button class="button" type="submit">Save {{ \Illuminate\Support\Str::lower($activeTabMeta['label']) }} settings</button>
    </div>
</form>

@if($sensitiveKeys !== [])
    <section class="card" style="margin-top:16px">
        <div class="section-head"><div><h2>Protected values</h2><p class="muted" style="margin:4px 0 0">Secrets are never shown in this console. Configure them on their own screens or in the server environment.</p></div></div>
        <div class="stack">
            @foreach($sensitiveKeys as $sensitiveKey)
                <div class="detail-row"><span class="mono">{{ $sensitiveKey }}</span><span class="badge">hidden</span></div>
            @endforeach
        </div>
    </section>
@endif

<script>
    (function () {
        function addKvRow(button) {
            var field = button.closest('.field');
            var rows = field ? field.querySelector('[data-kv-rows]') : null;
            if (!rows) { return; }
            var last = rows.querySelector('.kv-row:last-child');
            if (!last) { return; }
            var clone = last.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            rows.appendChild(clone);
        }

        function addCampaignRow(button) {
            var field = button.closest('.field');
            var body = field ? field.querySelector('[data-campaign-rows]') : null;
            if (!body) { return; }
            var blank = body.querySelector('tr:last-child');
            if (!blank) { return; }
            var clone = blank.cloneNode(true);
            var index = 'row' + Date.now() + Math.floor(Math.random() * 1000);
            clone.querySelectorAll('input').forEach(function (input) {
                input.value = '';
                input.checked = false;
                input.name = input.name.replace(/\[rows\]\[[^\]]+\]/, '[rows][' + index + ']');
            });
            body.appendChild(clone);
        }

        document.addEventListener('click', function (event) {
            var kvAdd = event.target.closest('[data-kv-add]');
            if (kvAdd) { addKvRow(kvAdd); return; }
            var campaignAdd = event.target.closest('[data-campaign-add]');
            if (campaignAdd) { addCampaignRow(campaignAdd); return; }
            var kvRemove = event.target.closest('[data-kv-remove]');
            if (kvRemove) {
                var row = kvRemove.closest('.kv-row');
                if (row) { row.remove(); }
            }
        });
    })();
</script>
@endsection
