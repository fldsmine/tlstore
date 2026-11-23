<div>
    <x-backoffice.page title="Operational settings" subtitle="A small typed allowlist is editable here; everything else is read-only">
        <x-backoffice.card title="Editable allowlist">
            <p class="font-sans text-[12px] text-muted mb-4">Provider secrets and gateway maps are intentionally not editable from the browser. Saving writes a <code class="bg-background rounded px-1">system_setting.updated</code> audit entry with before/after values.</p>
            <div class="flex flex-col gap-3">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {{-- vas.enabled --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="label" for="input-vas.enabled">vas.enabled</label>
                        <select id="input-vas.enabled" wire:model="inputs.vas.enabled" class="input">
                            <option value="1">true</option>
                            <option value="0">false</option>
                        </select>
                        @error('inputs.vas.enabled')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    {{-- vas.catalog_cache_minutes --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="label" for="input-vas.catalog_cache_minutes">vas.catalog_cache_minutes <span class="text-muted">(5–1440)</span></label>
                        <input id="input-vas.catalog_cache_minutes" type="number" min="5" max="1440" step="1" wire:model="inputs.vas.catalog_cache_minutes" class="input">
                        @error('inputs.vas.catalog_cache_minutes')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    {{-- campaign.service_fee_percent --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="label" for="input-campaign.service_fee_percent">campaign.service_fee_percent <span class="text-muted">(0–100)</span></label>
                        <input id="input-campaign.service_fee_percent" type="number" min="0" max="100" step="0.01" wire:model="inputs.campaign.service_fee_percent" class="input">
                        @error('inputs.campaign.service_fee_percent')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    {{-- campaign.currency --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="label" for="input-campaign.currency">campaign.currency <span class="text-muted">(3-letter)</span></label>
                        <input id="input-campaign.currency" type="text" maxlength="3" wire:model="inputs.campaign.currency" class="input uppercase" placeholder="NGN">
                        @error('inputs.campaign.currency')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-muted/10">
                    @foreach ($editable as $key => $type)
                        <button type="button" wire:click="save('{{ $key }}')" class="btn btn-quiet btn-sm">
                            <i class="ri-save-line"></i> Save {{ $key }}
                        </button>
                    @endforeach
                </div>
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="All settings (read-only)">
            <div class="overflow-x-auto -mx-5">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-muted/10">
                            <th class="th">Key</th>
                            <th class="th">Value</th>
                            <th class="th">Public</th>
                            <th class="th">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settings as $setting)
                            <tr wire:key="setting-{{ $setting->setting_key }}" class="tr align-top">
                                <td class="td"><code class="font-mono text-[11px] text-text">{{ $setting->setting_key }}</code></td>
                                <td class="td"><code class="font-mono text-[11px] text-muted break-all">{{ \Illuminate\Support\Str::limit(is_bool($setting->value) ? ($setting->value ? 'true' : 'false') : (is_scalar($setting->value) ? (string) $setting->value : json_encode($setting->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 160) }}</code></td>
                                <td class="td">{{ $setting->is_public ? 'Yes' : 'No' }}</td>
                                <td class="td text-muted whitespace-nowrap">{{ $setting->updated_at?->format('j M, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="td text-muted" colspan="4">No settings stored yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-backoffice.card>
    </x-backoffice.page>
</div>
