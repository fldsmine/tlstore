@php($selectedProof = $proofs[$handle->id] ?? null)

<div wire:key="social-proof-upload-{{ $handle->id }}" class="mt-3 rounded-[14px] bg-background p-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="font-sans text-[12px] font-medium text-text">
                Upload proof of task completion <span class="text-muted">(Compulsory)</span>
            </p>
            <p class="mt-1 max-w-[460px] font-sans text-[11px] leading-5 text-muted">
                This is required for task documentation and is mandatory for your withdrawal approval.
            </p>
            @if ($proof && $proof->target_url === $handle->url)
                <p class="mt-2 font-sans text-[11px] text-success">
                    <i class="ri-check-line"></i> Proof on file, uploaded {{ $proof->created_at?->diffForHumans() }}.
                </p>
            @elseif ($proof)
                <p class="mt-2 font-sans text-[11px] text-warning">The saved proof documents a previous page link. You may upload a new one for this link; it is still optional.</p>
            @endif
        </div>

        <div class="flex shrink-0 flex-col gap-2 sm:w-[240px]">
            <label for="social-follow-proof-{{ $handle->id }}" class="font-sans text-[11px] text-muted">
                Screenshot or PDF (max 5 MB)
            </label>
            <input id="social-follow-proof-{{ $handle->id }}" type="file" accept="image/jpeg,image/png,application/pdf"
                   wire:model="proofs.{{ $handle->id }}"
                   class="block w-full font-sans text-[11px] text-muted file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:font-sans file:text-[11px] file:text-primary" style="border: 1px solid var(--color-muted); border-radius: 4px; padding: 4px;" >
            <p wire:loading wire:target="proofs.{{ $handle->id }}" class="font-sans text-[11px] text-muted">Preparing file…</p>
            @if ($selectedProof)
                <p class="truncate font-sans text-[11px] text-muted">Selected: {{ $selectedProof->getClientOriginalName() }}</p>
            @endif
            @error('proofs.'.$handle->id)
                <span class="font-sans text-[11px] text-danger">{{ $message }}</span>
            @enderror
            <button type="button" wire:click="uploadProof('{{ $handle->id }}')"
                    wire:loading.attr="disabled" wire:target="uploadProof('{{ $handle->id }}')"
                    @disabled(! $selectedProof)
                    class="inline-flex h-[36px] items-center justify-center gap-1.5 rounded-full border border-primary/20 px-3 font-sans text-[11px] font-medium text-primary transition hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50">
                <i class="ri-upload-2-line" wire:loading.remove wire:target="uploadProof('{{ $handle->id }}')"></i>
                <span wire:loading.remove wire:target="uploadProof('{{ $handle->id }}')">{{ $proof && $proof->target_url === $handle->url ? 'Replace proof' : 'Upload proof' }}</span>
                <span wire:loading wire:target="uploadProof('{{ $handle->id }}')">Saving…</span>
            </button>
        </div>
    </div>
</div>
