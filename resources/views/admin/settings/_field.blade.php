@php
    /** @var array<string, mixed> $field */
    $name = $field['name'];
    $id = 'setting-'.$field['form_key'];
    $publicName = 'public['.$field['form_key'].']';
    $type = $field['type'];
    $value = $field['value'];
    $wide = in_array($type, ['textarea', 'lines', 'key_value', 'toggle_map', 'gateways', 'campaign_services', 'json'], true);
    $labelled = ! in_array($type, ['toggle', 'toggle_map', 'gateways', 'campaign_services', 'key_value'], true);
@endphp
<div class="field {{ $wide ? 'full' : '' }}">
    <div class="field-head">
        <label @if($labelled) for="{{ $id }}" @endif>{{ $field['label'] }}</label>
        <span class="field-badges">
            @if($field['disabled'])
                <span class="badge">Read only</span>
            @elseif($field['editable_public'])
                <label class="public-chip" title="Send this value to the public website/app API">
                    <input type="hidden" name="{{ $publicName }}" value="0">
                    <input type="checkbox" name="{{ $publicName }}" value="1" @checked($field['is_public'])> Public
                </label>
            @endif
        </span>
    </div>

    @switch($type)
        @case('toggle')
            <label class="checkline" for="{{ $id }}" style="padding:0">
                @if($field['disabled'])
                    <input type="checkbox" id="{{ $id }}" disabled @checked((bool) $value)>
                    <span class="muted">{{ $value ? 'Enabled' : 'Disabled' }}</span>
                @else
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked((bool) $value)>
                    <span class="badge {{ $value ? 'green' : '' }}">{{ $value ? 'On' : 'Off' }}</span>
                @endif
            </label>
            @break

        @case('integer')
        @case('decimal')
            <div class="input-suffix">
                <input class="input" id="{{ $id }}" type="number" name="{{ $name }}"
                       value="{{ $value === null ? '' : $value }}"
                       step="{{ $field['step'] ?? '1' }}"
                       @if($field['min'] !== null) min="{{ $field['min'] }}" @endif
                       @if($field['max'] !== null) max="{{ $field['max'] }}" @endif
                       @required($field['required'])>
                @if($field['suffix'])<small>{{ $field['suffix'] }}</small>@endif
            </div>
            @break

        @case('lines')
            <textarea class="textarea" id="{{ $id }}" name="{{ $name }}" rows="6" spellcheck="false">{{ implode("\n", (array) $value) }}</textarea>
            @break

        @case('textarea')
            <textarea class="textarea" id="{{ $id }}" name="{{ $name }}" rows="{{ $field['rows'] }}">{{ $value }}</textarea>
            @break

        @case('select')
        @case('select_wrapped')
            <select class="select" id="{{ $id }}" name="{{ $name }}" @required($field['required'])>
                @unless($field['required'])
                    <option value="">— not set —</option>
                @endunless
                @foreach($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('key_value')
            <div class="stack" data-kv-rows>
                @foreach($value as $kvKey => $kvValue)
                    <div class="kv-row">
                        <input class="input" name="{{ $name }}[names][]" value="{{ $kvKey }}" placeholder="{{ $field['key_label'] }}" aria-label="{{ $field['key_label'] }}">
                        <input class="input" name="{{ $name }}[urls][]" value="{{ $kvValue }}" placeholder="{{ $field['value_label'] }}" aria-label="{{ $field['value_label'] }}">
                        <button type="button" class="button danger small" data-kv-remove aria-label="Remove row">✕</button>
                    </div>
                @endforeach
                @for($i = 0; $i < 2; $i++)
                    <div class="kv-row">
                        <input class="input" name="{{ $name }}[names][]" value="" placeholder="{{ $field['key_label'] }}" aria-label="{{ $field['key_label'] }}">
                        <input class="input" name="{{ $name }}[urls][]" value="" placeholder="{{ $field['value_label'] }}" aria-label="{{ $field['value_label'] }}">
                        <button type="button" class="button danger small" data-kv-remove aria-label="Remove row">✕</button>
                    </div>
                @endfor
            </div>
            <div><button type="button" class="button secondary small" data-kv-add>Add row</button></div>
            @break

        @case('toggle_map')
            <div class="toggle-grid">
                @foreach($field['options'] as $optionKey => $optionLabel)
                    <label class="checkline">
                        <input type="hidden" name="{{ $name }}[{{ $optionKey }}]" value="0">
                        <input type="checkbox" name="{{ $name }}[{{ $optionKey }}]" value="1" @checked((bool) ($value[$optionKey] ?? false))>
                        <span>{{ $optionLabel }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('gateways')
            <div class="matrix-wrap">
                <table class="matrix">
                    <thead>
                    <tr>
                        <th>Gateway</th>
                        @foreach($field['currencies'] as $currency)
                            <th>{{ $currency }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($field['gateways'] as $gateway)
                        <tr class="{{ ($gateway['configured'] || $gateway['manual']) ? '' : 'gw-off' }}">
                            <td>
                                <strong>{{ $gateway['label'] }}</strong>
                                @if($gateway['manual'])
                                    <div class="field-help">Bank transfer with receipt upload</div>
                                @elseif(! $gateway['configured'])
                                    <div class="field-help">No API keys on this server — saved but not offered</div>
                                @endif
                            </td>
                            @if($gateway['manual'])
                                <td colspan="{{ count($field['currencies']) }}">
                                    <input type="hidden" name="{{ $name }}[manual]" value="0">
                                    <input type="checkbox" name="{{ $name }}[manual]" value="1" @checked($gateway['enabled']) aria-label="Enable {{ $gateway['label'] }}">
                                </td>
                            @else
                                @foreach($field['currencies'] as $currency)
                                    @php $cellName = $name.'[gateways]['.$gateway['key'].'][currencies]['.$currency.']'; @endphp
                                    <td>
                                        <input type="hidden" name="{{ $cellName }}" value="0">
                                        <input type="checkbox" name="{{ $cellName }}" value="1"
                                               @checked($gateway['currencies'][$currency] ?? false)
                                               aria-label="{{ $gateway['label'] }} in {{ $currency }}">
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @break

        @case('campaign_services')
            <div class="matrix-wrap">
                <table class="matrix campaign-table">
                    <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Action</th>
                        <th>Price / min quantity</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody data-campaign-rows>
                    @foreach($field['rows'] as $index => $row)
                        <tr>
                            <td><input class="input" name="{{ $name }}[rows][{{ $index }}][platform]" value="{{ $row['platform'] }}" aria-label="Platform"></td>
                            <td><input class="input" name="{{ $name }}[rows][{{ $index }}][action]" value="{{ $row['action'] }}" aria-label="Action"></td>
                            <td><input class="input" type="number" step="0.01" min="0" name="{{ $name }}[rows][{{ $index }}][value]" value="{{ $row['value'] }}" aria-label="Price or minimum"></td>
                            <td><label class="field-help" style="display:flex;gap:5px;align-items:center"><input type="checkbox" name="{{ $name }}[rows][{{ $index }}][remove]" value="1"> Remove</label></td>
                        </tr>
                    @endforeach
                    @for($i = 0; $i < 2; $i++)
                        @php $blank = 'new'.$i; @endphp
                        <tr>
                            <td><input class="input" name="{{ $name }}[rows][{{ $blank }}][platform]" value="" placeholder="instagram" aria-label="Platform"></td>
                            <td><input class="input" name="{{ $name }}[rows][{{ $blank }}][action]" value="" placeholder="likes" aria-label="Action"></td>
                            <td><input class="input" type="number" step="0.01" min="0" name="{{ $name }}[rows][{{ $blank }}][value]" value="" aria-label="Price or minimum"></td>
                            <td><span class="field-help">New</span></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>
            <div><button type="button" class="button secondary small" data-campaign-add>Add row</button></div>
            @break

        @case('json')
            <textarea class="textarea mono" id="{{ $id }}" name="{{ $name }}" rows="10" spellcheck="false">{{ $field['json_text'] }}</textarea>
            <span class="field-help">Advanced editor: this key has no dedicated control yet. Keep valid JSON — saving replaces the stored value.</span>
            @break

        @default
            <input class="input" id="{{ $id }}" type="text" name="{{ $name }}" value="{{ $value }}"
                   @if($field['max']) maxlength="{{ $field['max'] }}" @endif
                   @if($field['uppercase']) style="text-transform:uppercase" @endif
                   @required($field['required'])>
    @endswitch

    @if($field['help'])
        <span class="field-help">{{ $field['help'] }}</span>
    @endif

    @if($field['link_tab'])
        <a class="link" style="font-size:11px" href="{{ route('admin.settings.index', ['tab' => $field['link_tab']]) }}">Set the provider keys →</a>
    @endif

    @if($field['updated_at'])
        <span class="field-help">Last updated {{ $field['updated_at'] }}@if($field['updated_by']) by {{ $field['updated_by'] }}@endif.</span>
    @endif

    @error($field['rule_name'])
        <span class="errors">{{ $message }}</span>
    @enderror
</div>
