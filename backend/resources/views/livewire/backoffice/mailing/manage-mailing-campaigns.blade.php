<div>
    <x-backoffice.page title="Mailing campaigns" subtitle="Build targeted audiences and deliver messages through the configured mailer and queue">
        <x-slot:action>
            @if ($this->canWrite())
                <button type="button" wire:click="openComposer" class="inline-flex h-[40px] items-center gap-2 rounded-full bg-primary px-4 font-sans text-[13px] font-medium text-white hover:opacity-90">
                    <i class="ri-mail-add-line text-[16px]"></i> New campaign
                </button>
            @endif
        </x-slot:action>

        @if (! $healthReport['mailer_ready'] || ! $healthReport['queue_ready'] || ! $healthReport['cache_ready'])
            <div class="flex items-start gap-3 rounded-[16px] border border-danger/20 bg-danger/5 px-4 py-3.5">
                <i class="ri-error-warning-line mt-0.5 text-[20px] text-danger"></i>
                <div class="min-w-0 flex-1 font-sans text-[12px] leading-relaxed">
                    <p class="font-medium text-text">Campaign delivery is not ready. Sending and test-email actions are blocked.</p>
                    @unless ($healthReport['mailer_ready'])<p class="mt-1 text-muted">Mailer: {{ $healthReport['mailer_message'] }}</p>@endunless
                    @unless ($healthReport['queue_ready'])<p class="mt-1 text-muted">Queue: {{ $healthReport['queue_message'] }}</p>@endunless
                    @unless ($healthReport['cache_ready'])<p class="mt-1 text-muted">Cache: {{ $healthReport['cache_message'] }}</p>@endunless
                </div>
                @if ($this->canWrite())
                    <a href="{{ route('backoffice.settings.services') }}" wire:navigate class="shrink-0 font-sans text-[12px] text-primary underline">Review settings</a>
                @endif
            </div>
        @else
            <div class="flex items-start gap-3 rounded-[16px] border border-success/15 bg-success/5 px-4 py-3">
                <i class="ri-checkbox-circle-line mt-0.5 text-[19px] text-success"></i>
                <p class="font-sans text-[12px] leading-relaxed text-muted">
                    Mailer <span class="font-medium text-text">{{ strtoupper($healthReport['mailer_name']) }}</span>, durable queue <span class="font-medium text-text">{{ $healthReport['queue_name'] }}</span> and persistent cache <span class="font-medium text-text">{{ $healthReport['cache_name'] }}</span> are configured.
                    {{ $healthReport['cache_message'] }} A queue worker and the Laravel scheduler must be running for delivery and scheduled campaigns.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
            <x-backoffice.stat label="Campaigns" :value="number_format($stats['campaigns'])" icon="ri-mail-line" />
            <x-backoffice.stat label="Active campaigns" :value="number_format($stats['active'])" icon="ri-send-plane-line" tone="warning" hint="building, scheduled or delivering" />
            <x-backoffice.stat label="Queued recipients" :value="number_format($stats['queued'])" icon="ri-time-line" tone="info" />
            <x-backoffice.stat label="Sent" :value="number_format($stats['sent'])" icon="ri-checkbox-circle-line" tone="success" />
            <x-backoffice.stat label="Failed recipients" :value="number_format($stats['failed'])" icon="ri-error-warning-line" tone="danger" />
        </div>

        <x-backoffice.card>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(220px,1fr)_220px_120px]">
                <label class="flex h-[42px] items-center gap-2 rounded-[12px] border border-muted/15 bg-background px-3">
                    <i class="ri-search-line text-[16px] text-muted"></i>
                    <input type="search" wire:model.live.debounce.350ms="search" placeholder="Campaign name or subject…" class="min-w-0 flex-1 border-0 bg-transparent p-0 font-sans text-[13px] text-text placeholder:text-muted focus:ring-0">
                </label>
                <select wire:model.live="statusFilter" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="all">All campaign statuses</option>
                    @foreach ($campaignStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="perPage" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @foreach ([10, 25, 50, 100] as $value)<option value="{{ $value }}">{{ $value }} / page</option>@endforeach
                </select>
            </div>
        </x-backoffice.card>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($campaigns->isEmpty())
                <div class="p-10 text-center">
                    <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"><i class="ri-mail-open-line text-[22px]"></i></span>
                    <p class="font-heading text-[15px] font-medium text-text">No campaigns found</p>
                    <p class="mt-1 font-sans text-[12px] text-muted">Save a draft or create a new targeted email campaign.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] text-left">
                        <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                            <th class="px-5 py-3 font-medium">Campaign</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Progress</th>
                            <th class="px-4 py-3 font-medium text-right">Sent</th>
                            <th class="px-4 py-3 font-medium text-right">Failed</th>
                            <th class="px-4 py-3 font-medium">Schedule / updated</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($campaigns as $campaign)
                                @php
                                    $done = $campaign->sent_count + $campaign->failed_count + $campaign->cancelled_count;
                                    $progress = $campaign->recipient_count > 0 ? min(100, (int) round($done * 100 / $campaign->recipient_count)) : 0;
                                @endphp
                                <tr wire:key="mailing-campaign-{{ $campaign->id }}" class="border-b border-muted/5 last:border-0 hover:bg-background/40">
                                    <td class="px-5 py-3.5">
                                        <button type="button" wire:click="viewCampaign('{{ $campaign->id }}')" class="text-left font-sans text-[13px] font-medium text-text hover:text-primary">{{ $campaign->name }}</button>
                                        <p class="mt-0.5 max-w-[360px] truncate font-sans text-[11px] text-muted">{{ $campaign->subject }}</p>
                                        <p class="mt-0.5 font-sans text-[10px] text-muted/80">{{ number_format($campaign->recipient_count) }} recipient(s) · created {{ $campaign->created_at?->format('j M Y, g:i A') }}</p>
                                    </td>
                                    <td class="px-4 py-3.5"><x-backoffice.badge :tone="$campaign->status->tone()">{{ $campaign->status->label() }}</x-backoffice.badge></td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-muted/15"><div class="h-full rounded-full bg-primary" style="width: {{ $progress }}%"></div></div>
                                            <span class="font-sans text-[11px] tabular-nums text-muted">{{ $progress }}%</span>
                                        </div>
                                        <p class="mt-1 font-sans text-[10px] text-muted">{{ number_format($campaign->queued_count) }} queued · {{ number_format($campaign->sending_count) }} in progress</p>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-sans text-[13px] tabular-nums text-text">{{ number_format($campaign->sent_count) }}</td>
                                    <td class="px-4 py-3.5 text-right font-sans text-[13px] tabular-nums {{ $campaign->failed_count > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($campaign->failed_count) }}</td>
                                    <td class="px-4 py-3.5 font-sans text-[11px] text-muted">
                                        @if ($campaign->scheduled_at)
                                            <p>{{ $campaign->scheduled_at->format('j M Y, g:i A') }}</p>
                                        @endif
                                        <p>{{ $campaign->updated_at?->diffForHumans() }}</p>
                                        <p class="text-[10px]">by {{ $campaign->admin?->name ?? 'Former admin' }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex justify-end gap-1.5">
                                            <button type="button" wire:click="viewCampaign('{{ $campaign->id }}')" class="rounded-full bg-background px-3 py-2 font-sans text-[11px] text-text hover:bg-primary/10 hover:text-primary">View log</button>
                                            @if ($this->canWrite())
                                                @if ($campaign->status === \App\Enums\MailingCampaignStatus::DRAFT)
                                                    <button type="button" wire:click="editCampaign('{{ $campaign->id }}')" class="size-8 rounded-full bg-background text-muted hover:text-primary" aria-label="Edit draft"><i class="ri-edit-2-line"></i></button>
                                                @endif
                                                <button type="button" wire:click="duplicateCampaign('{{ $campaign->id }}')" class="size-8 rounded-full bg-background text-muted hover:text-primary" aria-label="Duplicate campaign"><i class="ri-file-copy-line"></i></button>
                                                @if ($campaign->status->isActive())
                                                    <button type="button" wire:click="askToCancel('{{ $campaign->id }}')" class="size-8 rounded-full bg-background text-muted hover:text-danger" aria-label="Cancel campaign"><i class="ri-stop-circle-line"></i></button>
                                                @endif
                                                @if ($campaign->failed_count > 0 && in_array($campaign->status, [\App\Enums\MailingCampaignStatus::COMPLETED_WITH_ERRORS, \App\Enums\MailingCampaignStatus::FAILED], true))
                                                    <button type="button" wire:click="askToRetry('{{ $campaign->id }}')" class="size-8 rounded-full bg-background text-muted hover:text-primary" aria-label="Retry failed recipients"><i class="ri-refresh-line"></i></button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($campaigns->hasPages())<div class="border-t border-muted/10 px-5 py-3">{{ $campaigns->links('vendor.pagination.nextel') }}</div>@endif
            @endif
        </x-backoffice.card>

        <x-backoffice.modal :show="$showComposer" :title="$editingCampaignId ? 'Edit campaign draft' : 'New mailing campaign'" size="xl" closeAction="$set('showComposer', false)">
            <div class="max-h-[76vh] space-y-5 overflow-y-auto pr-1">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Campaign name
                        <input type="text" wire:model="campaignName" maxlength="160" placeholder="April service update" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('campaignName')<span class="text-danger">{{ $message }}</span>@enderror
                    </label>
                    <div class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Reusable templates
                        <div class="flex gap-2">
                            <select wire:model="templateId" class="h-[42px] min-w-0 flex-1 rounded-[12px] border border-muted/15 bg-background px-3 text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                <option value="">Choose a saved template…</option>
                                @foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach
                            </select>
                            <button type="button" wire:click="loadTemplate" class="h-[42px] rounded-full bg-background px-3 font-sans text-[12px] text-text hover:text-primary">Load</button>
                            @if ($templateId && $this->canWrite())
                                <button type="button" wire:click="deleteTemplate('{{ $templateId }}')" wire:confirm="Delete this reusable template? Existing drafts and campaigns will not change." class="size-[42px] rounded-full bg-background text-muted hover:text-danger" aria-label="Delete selected template"><i class="ri-delete-bin-line"></i></button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[1fr_280px]">
                    <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Subject
                        <input type="text" wire:model.live="subject" maxlength="180" placeholder="A useful update for @{{ first_name }}" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('subject')<span class="text-danger">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Reply-to address <span class="text-muted/70">optional</span>
                        <input type="email" wire:model="replyTo" maxlength="254" placeholder="support@example.com" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('replyTo')<span class="text-danger">{{ $message }}</span>@enderror
                    </label>
                </div>

                <div class="rounded-[14px] border border-primary/15 bg-primary/5 px-4 py-3 font-sans text-[11px] leading-relaxed text-muted">
                    <p class="font-medium text-text">Personalization fields</p>
                    <p class="mt-1"><code>@{{ name }}</code>, <code>@{{ first_name }}</code>, <code>@{{ last_name }}</code>, <code>@{{ username }}</code>, <code>@{{ email }}</code>, <code>@{{ phone }}</code>, <code>@{{ package }}</code>, <code>@{{ status }}</code>, <code>@{{ joined_date }}</code>. Values are escaped in HTML. Unknown placeholders are removed.</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">HTML message
                        <textarea wire:model.live.debounce.400ms="htmlBody" rows="13" maxlength="100000" class="rounded-[12px] border border-muted/15 bg-background px-3 py-2 font-mono text-[12px] leading-relaxed text-text focus:outline-none focus:ring-2 focus:ring-primary/40" placeholder="<p>Hello @{{ first_name }},</p>"></textarea>
                        <span class="text-[10px] text-muted/70">HTML is sanitized before preview and delivery. Scripts, event handlers, forms and unsafe links are removed.</span>
                        @error('htmlBody')<span class="text-danger">{{ $message }}</span>@enderror
                    </label>
                    <div class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Live preview
                        <div class="min-h-[190px] rounded-[12px] border border-muted/15 bg-white p-4 text-[13px] text-gray-800">
                            <p class="mb-3 border-b border-gray-100 pb-2 font-sans text-[11px] text-gray-500">Subject: <span class="font-medium text-gray-800">{{ $previewSubject }}</span></p>
                            <div class="mailing-preview">{!! $previewHtml !!}</div>
                        </div>
                        <p class="font-sans text-[10px] text-muted/70">Preview uses a synthetic sample profile; no real recipient details are exposed here.</p>
                    </div>
                </div>

                <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Plain-text alternative <span class="text-muted/70">optional — generated from the sanitized HTML if left blank</span>
                    <textarea wire:model="textBody" rows="5" maxlength="100000" class="rounded-[12px] border border-muted/15 bg-background px-3 py-2 font-mono text-[12px] leading-relaxed text-text focus:outline-none focus:ring-2 focus:ring-primary/40" placeholder="Hello @{{ first_name }},&#10;&#10;Your update is here."></textarea>
                    @error('textBody')<span class="text-danger">{{ $message }}</span>@enderror
                </label>

                <div class="border-t border-muted/10 pt-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-heading text-[14px] font-medium text-text">Audience builder</p>
                            <p class="mt-0.5 font-sans text-[11px] text-muted">Eligible user records with a non-empty email; the server rechecks every target before queueing.</p>
                        </div>
                        <div class="rounded-full bg-primary/10 px-3 py-1.5 font-sans text-[12px] font-medium text-primary">
                            {{ number_format($audienceCount) }} {{ $audienceMode === 'selected' ? 'selected eligible' : 'matching' }} recipient(s)
                        </div>
                    </div>
                    @error('audience')<p class="mb-2 font-sans text-[12px] text-danger">{{ $message }}</p>@enderror
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-2 rounded-[12px] border border-muted/15 bg-background p-3 font-sans text-[12px] text-text">
                            <input type="radio" wire:model.live="audienceMode" value="filters" class="mt-0.5 accent-primary">
                            <span><strong>All users matching filters</strong><span class="mt-0.5 block text-[11px] text-muted">The recipient list is built from current eligible accounts when launched.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 rounded-[12px] border border-muted/15 bg-background p-3 font-sans text-[12px] text-text">
                            <input type="radio" wire:model.live="audienceMode" value="selected" class="mt-0.5 accent-primary">
                            <span><strong>Selected users only</strong><span class="mt-0.5 block text-[11px] text-muted">Checkbox selections are deduplicated and revalidated server-side.</span></span>
                        </label>
                    </div>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <select wire:model.live="userStatus" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text"><option value="all">Any account status</option><option value="active">Active account</option><option value="suspended">Suspended account</option></select>
                        <select wire:model.live="roleFilter" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text"><option value="all">All roles</option><option value="member">Members</option><option value="agent">Agents</option><option value="special">Special agents</option></select>
                        <select wire:model.live="activationFilter" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text"><option value="all">Any account activation</option><option value="activated">Package activated</option><option value="not_activated">No package activated</option></select>
                        <select wire:model.live="emailStatusFilter" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text"><option value="all">Email verification: any</option><option value="verified">Verified email</option><option value="unverified">Unverified email</option></select>
                        <select wire:model.live="packageFilter" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text">
                            <option value="all">Any package</option><option value="active">Active package</option><option value="inactive">Inactive / unavailable package</option><option value="none">No package</option>
                            @foreach ($packages as $package)<option value="{{ $package->id }}">{{ $package->name }}{{ $package->is_active ? '' : ' (inactive)' }}</option>@endforeach
                        </select>
                        <select wire:model.live="shareFilter" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text"><option value="all">Share subscription: any</option><option value="active">Current share subscription</option><option value="inactive">No current share subscription</option></select>
                        <select wire:model.live="websiteFilter" class="h-[40px] rounded-[10px] border border-muted/15 bg-background px-2.5 font-sans text-[12px] text-text"><option value="all">Website service: any</option><option value="active">Active website service</option><option value="inactive">No active website service</option></select>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" wire:model.live="registeredFrom" aria-label="Registered from" class="h-[40px] min-w-0 rounded-[10px] border border-muted/15 bg-background px-2 font-sans text-[11px] text-text">
                            <input type="date" wire:model.live="registeredTo" aria-label="Registered to" class="h-[40px] min-w-0 rounded-[10px] border border-muted/15 bg-background px-2 font-sans text-[11px] text-text">
                        </div>
                    </div>
                    @error('registeredFrom')<p class="mt-1 font-sans text-[11px] text-danger">{{ $message }}</p>@enderror
                    @error('registeredTo')<p class="mt-1 font-sans text-[11px] text-danger">{{ $message }}</p>@enderror

                    <p class="mt-3 font-sans text-[10px] text-muted/80">The search below is for finding checkbox recipients; it does not narrow the “all users matching filters” audience.</p>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <label class="flex h-[40px] min-w-[220px] flex-1 items-center gap-2 rounded-[10px] border border-muted/15 bg-background px-3 sm:max-w-[420px]">
                            <i class="ri-search-line text-[14px] text-muted"></i>
                            <input type="search" wire:model.live.debounce.300ms="userSearch" placeholder="Search name, username, email or phone…" class="min-w-0 flex-1 border-0 bg-transparent p-0 font-sans text-[12px] text-text placeholder:text-muted focus:ring-0">
                        </label>
                        <div class="flex items-center gap-2 font-sans text-[11px] text-muted">
                            <span>{{ number_format(count($selectedUserIds)) }} selected across pages</span>
                            @if ($this->canWrite())
                                <button type="button" wire:click="selectVisibleUsers" class="rounded-full bg-background px-3 py-2 text-text hover:text-primary">Select this page</button>
                                <button type="button" wire:click="clearSelection" class="rounded-full bg-background px-3 py-2 text-text hover:text-danger">Clear</button>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2 overflow-x-auto rounded-[12px] border border-muted/10">
                        <table class="w-full min-w-[620px] text-left">
                            <thead><tr class="bg-background font-sans text-[10px] uppercase tracking-wide text-muted"><th class="w-10 px-3 py-2">Select</th><th class="px-3 py-2">User</th><th class="px-3 py-2">Account / service</th><th class="px-3 py-2">Joined</th></tr></thead>
                            <tbody>
							@if (is_iterable($audienceUsers ?? null))
								@forelse ($audienceUsers as $recipientUser)
									<tr wire:key="audience-user-{{ $recipientUser->id }}" class="border-t border-muted/5 font-sans text-[11px]">
										<td class="px-3 py-2">
											<input type="checkbox" wire:model.live="selectedUserIds" value="{{ $recipientUser->id }}" @disabled(! $this->canWrite()) class="size-4 rounded border-muted/30 accent-primary">
										</td>
										<td class="px-3 py-2">
											<p class="font-medium text-text">{{ $recipientUser->full_name ?: 'Unnamed user' }}</p>
											<p class="text-muted">{{ $this->maskedEmail($recipientUser->email) }} · {{ $recipientUser->username ?: 'no username' }}</p>
										</td>
										<td class="px-3 py-2 text-muted">
											<p>{{ $recipientUser->status?->value ?? $recipientUser->status }}</p>
											<p>{{ $recipientUser->package?->name ?? 'No package' }}{{ $recipientUser->is_agent ? ' · agent' : '' }}</p>
										</td>
										<td class="px-3 py-2 text-muted">{{ $recipientUser->created_at?->format('j M Y') }}</td>
									</tr>
								@empty
									<tr>
										<td colspan="4" class="px-3 py-7 text-center font-sans text-[12px] text-muted">
											No eligible users match these filters and search.
										</td>
									</tr>
								@endforelse
							@else
								<tr>
									<td colspan="4" class="px-3 py-7 text-center font-sans text-[12px] text-muted">
										No eligible users match these filters and search.
									</td>
								</tr>
							@endif
                            </tbody>
                        </table>
                    </div>
                    @if ($audienceUsers?->hasPages())<div class="mt-2">{{ $audienceUsers->links('vendor.pagination.nextel') }}</div>@endif
                </div>

                <div class="grid gap-3 border-t border-muted/10 pt-4 md:grid-cols-2">
                    <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Schedule send <span class="text-muted/70">leave blank to queue immediately after confirmation</span>
                        <input type="datetime-local" wire:model="scheduledAt" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('scheduledAt')<span class="text-danger">{{ $message }}</span>@enderror
                    </label>
                    <label class="flex flex-col gap-1.5 font-sans text-[12px] text-muted">Test email address
                        <input type="email" wire:model="testEmail" maxlength="254" placeholder="{{ auth('admin')->user()?->email }}" class="h-[42px] rounded-[12px] border border-muted/15 bg-background px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('testEmail')<span class="text-danger">{{ $message }}</span>@enderror
                    </label>
                </div>

                @if ($showSaveTemplate)
                    <div class="grid gap-2 rounded-[12px] border border-primary/15 bg-primary/5 p-3 sm:grid-cols-[1fr_auto]">
                        <label class="flex flex-col gap-1 font-sans text-[11px] text-muted">Template name<input type="text" wire:model="templateName" maxlength="160" class="h-[38px] rounded-[10px] border border-muted/15 bg-background px-3 text-[12px] text-text"></label>
                        <button type="button" wire:click="saveAsTemplate" class="self-end rounded-full bg-primary px-4 py-2.5 font-sans text-[12px] text-white">Save reusable template</button>
                        @error('templateName')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-muted/10 pt-4">
                    <button type="button" wire:click="$toggle('showSaveTemplate')" class="rounded-full bg-background px-3.5 py-2.5 font-sans text-[12px] text-text hover:text-primary"><i class="ri-bookmark-line mr-1"></i> Save as template</button>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="h-[42px] rounded-full bg-background px-4 font-sans text-[12px] text-text disabled:opacity-50">Save draft</button>
                        <button type="button" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail" @disabled(! $healthReport['ready']) class="h-[42px] rounded-full border border-primary/20 px-4 font-sans text-[12px] text-primary disabled:cursor-not-allowed disabled:opacity-40">Queue test email</button>
                        <button type="button" wire:click="requestLaunch" wire:loading.attr="disabled" wire:target="requestLaunch" @disabled(! $healthReport['ready']) class="inline-flex h-[42px] items-center gap-2 rounded-full bg-primary px-5 font-sans text-[12px] font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">
                            <i class="ri-send-plane-line"></i> Review and send
                        </button>
                    </div>
                </div>
            </div>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showLaunchConfirmation" title="Confirm campaign launch" size="lg" closeAction="cancelLaunchConfirmation">
            <div class="space-y-4">
                <div class="rounded-[14px] border border-[#d79937]/25 bg-[#d79937]/8 p-4 font-sans text-[13px] leading-relaxed text-muted">
                    <p class="font-medium text-text">You are about to queue {{ number_format($confirmRecipientCount) }} individual email(s).</p>
                    <p class="mt-1">Each message is sent separately through the configured mailer, with rate limiting, retries and a recipient-level delivery log. The audience and addresses are revalidated by the server. This cannot be recalled after a message is delivered.</p>
                    @if ($confirmCampaignId && ($confirmCampaign = \App\Models\MailingCampaign::query()->find($confirmCampaignId))?->scheduled_at)
                        <p class="mt-2 font-medium text-text">Scheduled for {{ $confirmCampaign->scheduled_at->format('j M Y, g:i A T') }}.</p>
                    @endif
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelLaunchConfirmation" class="h-[42px] rounded-full bg-background px-4 font-sans text-[13px] text-muted">Go back</button>
                    <button type="button" wire:click="confirmLaunch" wire:loading.attr="disabled" wire:target="confirmLaunch" class="h-[42px] rounded-full bg-primary px-5 font-sans text-[13px] font-medium text-white disabled:opacity-50">Confirm and queue</button>
                </div>
            </div>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showActionConfirmation" :title="$confirmAction === 'cancel' ? 'Cancel campaign?' : 'Retry failed recipients?'" size="lg" closeAction="closeActionConfirmation">
            <div class="space-y-4">
                @if ($confirmAction === 'cancel')
                    <div class="rounded-[14px] border border-danger/15 bg-danger/5 p-4 font-sans text-[13px] leading-relaxed text-muted">
                        <p class="font-medium text-text">Queued messages will be stopped.</p>
                        <p class="mt-1">Messages already in progress may finish. Delivered messages cannot be recalled. The campaign and recipient log will remain available.</p>
                    </div>
                @else
                    <div class="rounded-[14px] border border-[#d79937]/25 bg-[#d79937]/8 p-4 font-sans text-[13px] leading-relaxed text-muted">
                        <p class="font-medium text-text">Only recipients currently marked failed will be queued again.</p>
                        <p class="mt-1">Previously sent recipients are not selected, and the delivery job checks recipient status before sending to avoid duplicate delivery on normal retries.</p>
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeActionConfirmation" class="h-[42px] rounded-full bg-background px-4 font-sans text-[13px] text-muted">Keep campaign</button>
                    <button type="button" wire:click="confirmAction" wire:loading.attr="disabled" class="h-[42px] rounded-full {{ $confirmAction === 'cancel' ? 'bg-danger' : 'bg-primary' }} px-5 font-sans text-[13px] font-medium text-white disabled:opacity-50">{{ $confirmAction === 'cancel' ? 'Cancel campaign' : 'Queue safe retry' }}</button>
                </div>
            </div>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$detailCampaign !== null" title="Campaign delivery log" size="xl" closeAction="closeDetail">
            @if ($detailCampaign)
                <div class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="font-heading text-[15px] font-medium text-text">{{ $detailCampaign->name }}</p><p class="mt-1 font-sans text-[12px] text-muted">{{ $detailCampaign->subject }}</p></div>
                        <x-backoffice.badge :tone="$detailCampaign->status->tone()">{{ $detailCampaign->status->label() }}</x-backoffice.badge>
                    </div>
                    @if ($detailCampaign->failure_code)
                        <p class="rounded-[10px] border border-danger/15 bg-danger/5 px-3 py-2 font-sans text-[11px] text-danger">Campaign issue: {{ $detailCampaign->failure_code }}</p>
                    @endif
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div class="rounded-[10px] bg-background p-2.5"><p class="font-sans text-[10px] text-muted">Recipients</p><p class="font-sans text-[14px] font-medium text-text">{{ number_format($detailCampaign->recipient_count) }}</p></div>
                        <div class="rounded-[10px] bg-background p-2.5"><p class="font-sans text-[10px] text-muted">Queued</p><p class="font-sans text-[14px] font-medium text-text">{{ number_format($detailCampaign->queued_count) }}</p></div>
                        <div class="rounded-[10px] bg-background p-2.5"><p class="font-sans text-[10px] text-muted">Sending</p><p class="font-sans text-[14px] font-medium text-text">{{ number_format($detailCampaign->sending_count) }}</p></div>
                        <div class="rounded-[10px] bg-background p-2.5"><p class="font-sans text-[10px] text-muted">Sent</p><p class="font-sans text-[14px] font-medium text-success">{{ number_format($detailCampaign->sent_count) }}</p></div>
                        <div class="rounded-[10px] bg-background p-2.5"><p class="font-sans text-[10px] text-muted">Failed</p><p class="font-sans text-[14px] font-medium text-danger">{{ number_format($detailCampaign->failed_count) }}</p></div>
                    </div>
                    <div class="overflow-x-auto rounded-[12px] border border-muted/10">
                        <table class="w-full min-w-[700px] text-left">
                            <thead><tr class="bg-background font-sans text-[10px] uppercase tracking-wide text-muted"><th class="px-3 py-2">User</th><th class="px-3 py-2">Delivery</th><th class="px-3 py-2 text-right">Attempts</th><th class="px-3 py-2">Last attempt</th><th class="px-3 py-2">Failure type</th></tr></thead>
                            <tbody>
                                @forelse ($detailRecipients as $log)
                                    <tr wire:key="mailing-recipient-{{ $log->id }}" class="border-t border-muted/5 font-sans text-[11px]">
                                        <td class="px-3 py-2"><p class="font-medium text-text">{{ $log->user?->full_name ?: 'Account no longer available' }}</p><p class="text-muted">{{ $this->maskedEmail($log->user?->email) }}</p></td>
                                        <td class="px-3 py-2"><x-backoffice.badge :tone="$log->status->tone()">{{ $log->status->label() }}</x-backoffice.badge></td>
                                        <td class="px-3 py-2 text-right tabular-nums text-text">{{ $log->attempts }}</td>
                                        <td class="px-3 py-2 text-muted">{{ $log->last_attempt_at?->format('j M Y, g:i A') ?? '—' }}@if ($log->sent_at)<p class="text-success">sent {{ $log->sent_at->format('j M, g:i A') }}</p>@endif</td>
                                        <td class="px-3 py-2 text-danger">{{ $log->last_error_code ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center font-sans text-[12px] text-muted">No recipient log entries are available yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($detailRecipients?->hasPages())<div>{{ $detailRecipients->links('vendor.pagination.nextel') }}</div>@endif
                    <p class="font-sans text-[10px] leading-relaxed text-muted">Recipient addresses are not copied into campaign logs. Addresses are masked in the backoffice and fetched from the current user account only for delivery.</p>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
