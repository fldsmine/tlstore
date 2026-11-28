@php
    /** @var array<string, array<string, mixed>> $envSections */
    $statusLabel = ['configured' => 'Ready', 'incomplete' => 'Incomplete', 'missing' => 'Not configured'];
    $statusClass = ['configured' => 'green', 'incomplete' => 'gold', 'missing' => ''];
@endphp
<style>
    .provider{border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:10px}
    .provider[open]{box-shadow:0 2px 10px rgba(19,45,35,.05)}
    .provider > summary{display:flex;align-items:center;gap:10px;padding:12px 14px;cursor:pointer;list-style:none}
    .provider > summary::-webkit-details-marker{display:none}
    .provider > summary::before{content:'▸';color:var(--green);font-weight:900;transition:transform .15s}
    .provider[open] > summary::before{transform:rotate(90deg)}
    .provider-name{font-weight:800;font-size:13px}
    .provider > summary .muted{font-size:11px;margin-left:auto}
    .provider-body{padding:0 14px 14px;border-top:1px solid var(--line);margin-top:2px;padding-top:14px}
    .provider-docs{font-size:11px;font-weight:800;color:var(--green)}
    .clear-flag{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--red);cursor:pointer}
    .clear-flag input{accent-color:var(--red)}
    .env-meta{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:12px;color:var(--muted);margin-top:6px}
    .env-meta strong{color:var(--ink)}
    .env-chips{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 0}
    .env-chips a{font-size:11px;font-weight:800;color:var(--green);background:#edf4ef;border:1px solid var(--line);border-radius:100px;padding:3px 9px}
    .env-warning{background:#fff4d9;border:1px solid #f0dca6;color:#6d4d00;padding:12px 14px;border-radius:12px;font-size:12px;font-weight:700;margin-bottom:16px}
    .env-note{font-size:12px;color:var(--muted)}
    .env-note code{background:#f4f7f5;padding:1px 5px;border-radius:5px;font-size:11px}
    .server-list{display:grid;gap:8px}
    .server-list div{font-size:12px;color:var(--muted);padding-bottom:8px;border-bottom:1px solid #edf1ee}
    .server-list div:last-child{border-bottom:0;padding-bottom:0}
    .server-list code{font-size:11px;font-weight:800;color:var(--ink)}
</style>

@if($envProblem)
    <div class="env-warning">{{ $envProblem }}</div>
@else
    <section class="card" style="margin-bottom:16px">
        <div class="section-head">
            <div>
                <h2>Provider credentials</h2>
                <p class="muted" style="margin:4px 0 0">API keys, portal IDs and switches, written straight to the server's <code>.env</code> file. No developer or redeploy needed.</p>
            </div>
            <span class="badge green">{{ $envConfigured }}/{{ $envProviderCount }} providers ready</span>
        </div>
        <div class="env-meta">
            <span>File: <strong class="mono">{{ $envPath }}</strong></span>
            <span>Backup written on each save: <strong class="mono">{{ $envBackup }}</strong></span>
            <span>Secrets are write-only — an empty secret field always keeps the stored value.</span>
        </div>
        <div class="env-chips">
            @foreach($envSections as $sectionId => $section)
                <a href="#env-{{ $sectionId }}">{{ $section['label'] }}</a>
            @endforeach
            <a href="{{ route('admin.notifications.index') }}">Sender identity &amp; templates →</a>
            <a href="{{ route('admin.telegram.index') }}">Telegram bot token →</a>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.settings.environment.update') }}" autocomplete="off">
        @csrf
        @method('PATCH')
        <input type="hidden" name="tab" value="environment">

        @foreach($envSections as $sectionId => $section)
            @php
                $ready = collect($section['providers'])->where('status', 'configured')->count();
            @endphp
            <section class="card settings-card" id="env-{{ $sectionId }}">
                <div class="section-head">
                    <div>
                        <h2>{{ $section['label'] }}</h2>
                        <p class="muted" style="margin:4px 0 0">{{ $section['description'] }}</p>
                    </div>
                    <span class="badge {{ $ready === count($section['providers']) ? 'green' : '' }}">{{ $ready }}/{{ count($section['providers']) }} ready</span>
                </div>

                @foreach($section['providers'] as $provider)
                    <details class="provider">
                        <summary>
                            <span class="provider-name">{{ $provider['label'] }}</span>
                            <span class="badge {{ $statusClass[$provider['status']] }}">{{ $statusLabel[$provider['status']] }}</span>
                            <span class="muted">{{ $provider['set_count'] }}/{{ $provider['key_count'] }} set</span>
                            @if($provider['docs'])
                                <a class="provider-docs" href="{{ $provider['docs'] }}" target="_blank" rel="noreferrer">Provider docs ↗</a>
                            @endif
                        </summary>
                        <div class="provider-body">
                            @if($provider['status'] !== 'configured' && $provider['missing_required'] !== [])
                                <p class="field-help" style="margin:0 0 10px">Required: <strong>{{ implode(', ', $provider['missing_required']) }}</strong></p>
                            @endif
                            <div class="form-grid">
                                @foreach($provider['fields'] as $field)
                                    <div class="field {{ in_array($field['type'], ['list'], true) ? 'full' : '' }}">
                                        <div class="field-head">
                                            <label for="env-{{ $field['key'] }}">{{ $field['label'] }}</label>
                                            @if($field['is_secret'])
                                                <span class="field-badges">
                                                    <span class="badge {{ $field['is_set'] ? 'green' : '' }}">{{ $field['is_set'] ? 'Set' : 'Not set' }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        @if($field['type'] === 'secret')
                                            <input class="input" id="env-{{ $field['key'] }}" type="password" name="{{ $field['name'] }}"
                                                   value="" placeholder="{{ $field['is_set'] ? '•••••••••• (stored — leave blank to keep)' : 'Paste the value' }}"
                                                   autocomplete="new-password" spellcheck="false">
                                            @if($field['is_set'])
                                                <label class="clear-flag">
                                                    <input type="hidden" name="{{ $field['clear_name'] }}" value="0">
                                                    <input type="checkbox" name="{{ $field['clear_name'] }}" value="1"> Remove the stored value
                                                </label>
                                            @endif
                                        @elseif($field['type'] === 'boolean')
                                            <select class="select" id="env-{{ $field['key'] }}" name="{{ $field['name'] }}">
                                                <option value="true" @selected(old($field['rule_name'], $field['effective']) === 'true')>Enabled</option>
                                                <option value="false" @selected(old($field['rule_name'], $field['effective']) === 'false')>Disabled</option>
                                            </select>
                                        @elseif($field['type'] === 'select')
                                            <select class="select" id="env-{{ $field['key'] }}" name="{{ $field['name'] }}">
                                                @foreach($field['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) old($field['rule_name'], $field['effective']) === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($field['type'] === 'integer')
                                            <div class="input-suffix">
                                                <input class="input" id="env-{{ $field['key'] }}" type="number" name="{{ $field['name'] }}"
                                                       value="{{ old($field['rule_name'], $field['display']) }}"
                                                       @if($field['min'] !== null) min="{{ $field['min'] }}" @endif
                                                       @if($field['max'] !== null) max="{{ $field['max'] }}" @endif
                                                       step="1">
                                                @if($field['suffix'])<small>{{ $field['suffix'] }}</small>@endif
                                            </div>
                                        @else
                                            <input class="input" id="env-{{ $field['key'] }}" type="{{ $field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text') }}"
                                                   name="{{ $field['name'] }}" value="{{ old($field['rule_name'], $field['display']) }}"
                                                   spellcheck="false" @if($field['type'] === 'url') inputmode="url" @endif>
                                        @endif

                                        @if($field['help'])
                                            <span class="field-help">{{ $field['help'] }}</span>
                                        @endif

                                        @if($field['default'] !== null && ! $field['is_set'] && $field['type'] !== 'secret')
                                            <span class="field-help">Default when unset: {{ $field['default'] === '' ? 'automatic' : $field['default'] }}</span>
                                        @endif

                                        @error($field['rule_name'])
                                            <span class="errors">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </section>
        @endforeach

        <div class="save-bar">
            <span class="muted">Empty text fields remove the variable and fall back to the built-in default. Secrets are never shown again once saved.</span>
            <button class="button" type="submit">Save credentials</button>
        </div>
    </form>
@endif

<section class="card" style="margin-top:16px">
    <div class="section-head"><div><h2>Kept on the server</h2><p class="muted" style="margin:4px 0 0">These need filesystem or shell access, so they are not editable from the console.</p></div></div>
    <div class="server-list">
        @foreach($envServerManaged as $key => $reason)
            <div><code>{{ $key }}</code> — {{ $reason }}</div>
        @endforeach
    </div>
</section>
