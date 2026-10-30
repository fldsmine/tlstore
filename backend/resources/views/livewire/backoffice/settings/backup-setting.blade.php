<div>
    <x-backoffice.page title="Backups" subtitle="Back up the database & files to S3 storage">

        <x-backoffice.settings-tabs active="backup" />

        @php $input = 'h-[42px] w-full rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary'; @endphp

        <div class="flex flex-col gap-5">

            <x-backoffice.card>
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <span class="size-10 rounded-[12px] {{ $configured ? 'bg-success/12 text-success' : 'bg-muted/15 text-muted' }} flex items-center justify-center shrink-0"><i class="ri-{{ $configured ? 'checkbox-circle-fill' : 'cloud-off-line' }} text-[20px]"></i></span>
                        <div>
                            <p class="font-heading text-text text-[15px]">S3 storage {{ $configured ? 'connected' : 'not connected' }}</p>
                            <p class="font-sans text-muted text-[12px]">
                                @if (! $configured)
                                    Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_BUCKET in the environment to enable backups.
                                @elseif ($lastBackupAt)
                                    Backing up to <span class="text-text">{{ $bucket }}</span> · last success: <span class="text-text">{{ $lastBackupAt }}</span>
                                @else
                                    Backing up to <span class="text-text">{{ $bucket }}</span> · no backup yet — run one to test.
                                @endif
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="runBackup" wire:loading.attr="disabled" wire:target="runBackup" @disabled(! $configured)
                            class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                        <span wire:loading.remove wire:target="runBackup"><i class="ri-play-circle-line"></i> Back up now</span>
                        <span wire:loading wire:target="runBackup" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Starting…</span>
                    </button>
                </div>
            </x-backoffice.card>

            <form wire:submit="save" class="flex flex-col gap-5">
                <x-backoffice.card title="Schedule">
                    <div class="flex flex-col gap-4">
                        <x-backoffice.toggle wire:model="enabled" label="Automatic nightly backups"
                                             description="When on, a backup runs every night and old backups past the retention below are cleaned." />
                        <div class="flex flex-col gap-1.5 max-w-[260px]">
                            <label class="font-sans text-[12px] text-muted">Keep backups for (days)</label>
                            <input type="number" min="1" step="1" wire:model="keepDays" class="{{ $input }}">
                            @error('keepDays')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </x-backoffice.card>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[44px] px-6 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save changes</span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </x-backoffice.page>
</div>
