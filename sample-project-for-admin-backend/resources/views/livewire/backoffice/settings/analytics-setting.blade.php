<div>
    <x-backoffice.page title="Settings" subtitle="Platform configuration">
        <x-backoffice.settings-tabs active="analytics" />

        @php $area = 'rounded-[12px] bg-background border border-muted/15 px-3.5 py-3 font-mono text-[12px] text-text leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">
            <x-backoffice.card title="Tracking & verification code">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Paste analytics / verification snippets (Google Analytics, Meta Pixel, Search Console, etc.). The code is injected raw into <span class="font-mono">every</span> layout — header code just before <span class="font-mono">&lt;/head&gt;</span>, footer code just before <span class="font-mono">&lt;/body&gt;</span>. Only paste code from sources you trust.</p>

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Header code <span class="text-muted/60">(before &lt;/head&gt;)</span></label>
                        <textarea wire:model="headerCode" rows="6" spellcheck="false" placeholder="&lt;!-- e.g. Google Analytics --&gt;&#10;&lt;script src=&quot;...&quot;&gt;&lt;/script&gt;" class="{{ $area }}"></textarea>
                        @error('headerCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Footer code <span class="text-muted/60">(before &lt;/body&gt;)</span></label>
                        <textarea wire:model="footerCode" rows="6" spellcheck="false" placeholder="&lt;!-- e.g. chat widget / pixel --&gt;" class="{{ $area }}"></textarea>
                        @error('footerCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <div class="flex justify-end">
                <button type="submit" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px]">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
