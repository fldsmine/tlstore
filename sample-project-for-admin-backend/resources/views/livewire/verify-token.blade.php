<div>
    <x-public.page-hero badge="Verify Token" title="Verify<br>Your Token" breadcrumb="Verify Token" />

    <x-public.shell class="bg-surface">
        <div class="max-w-[640px] mx-auto flex flex-col gap-6">
            <form wire:submit="verify"
                  class="bg-primary rounded-[20px] md:rounded-[28px] px-5 md:px-12 py-8 md:py-14 flex flex-col gap-6">

                <div class="flex flex-col gap-2">
                    <input type="text" wire:model="code" placeholder="Enter Your Token"
                           class="w-full bg-white rounded-full px-6 h-[52px] font-sans text-[14px] text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-secondary">
                    @error('code')
                        <span class="px-2 font-sans text-[13px] text-secondary">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit"
                        class="mx-auto px-12 h-[48px] bg-secondary text-primary rounded-full font-sans font-semibold text-[14px] hover:brightness-95 transition">
                    <span wire:loading.remove wire:target="verify">Verify</span>
                    <span wire:loading wire:target="verify">Verifying…</span>
                </button>
            </form>

            @if ($result)
                @php
                    $ok = $result['status'] === 'valid';
                    $accent = $ok ? 'text-success' : ($result['status'] === 'used' ? 'text-[#C98A00]' : 'text-danger');
                @endphp
                <div class="bg-surface border border-border rounded-[20px] md:rounded-[28px] px-5 md:px-10 py-6 md:py-8 flex flex-col gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="size-2.5 rounded-full {{ $ok ? 'bg-success' : ($result['status'] === 'used' ? 'bg-[#C98A00]' : 'bg-danger') }}"></span>
                        <p class="font-heading font-semibold text-[18px] {{ $accent }}">{{ $result['title'] }}</p>
                    </div>
                    <p class="font-sans text-[14px] text-muted">{{ $result['message'] }}</p>
                    @if (! empty($result['type']))
                        <div class="flex flex-col gap-1.5 pt-3 border-t border-border font-sans text-[14px]">
                            <p class="text-text"><span class="text-muted">Code:</span> <span class="font-medium tracking-wide">{{ $result['code'] }}</span></p>
                            <p class="text-text"><span class="text-muted">Type:</span> {{ $result['type'] }}</p>
                            <p class="text-text"><span class="text-muted">Grants:</span> {{ $result['grants'] }}</p>
                            @if (! empty($result['minutes']))
                                <p class="text-text"><span class="text-muted">{{ $result['details_label'] ?? 'Includes' }}:</span> {{ $result['minutes'] }}</p>
                            @endif
                            @if (! empty($result['amount']))
                                <p class="text-text"><span class="text-muted">Value:</span> {{ $result['amount'] }}</p>
                            @endif
                            @if (! empty($result['vendor']))
                                <p class="text-text"><span class="text-muted">Vendor:</span> {{ $result['vendor'] }}</p>
                            @endif
                            @if (! empty($result['used_by']))
                                <p class="text-text"><span class="text-muted">Redeemed by:</span> {{ $result['used_by'] }}@if (! empty($result['used_at'])) <span class="text-muted">on</span> {{ $result['used_at'] }}@endif</p>
                            @endif
                            @if (! empty($result['upline']))
                                <p class="text-text"><span class="text-muted">Upline:</span> {{ $result['upline'] }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-public.shell>
</div>
