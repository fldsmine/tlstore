<?php

namespace App\Livewire\Backoffice\Mailing;

use App\Enums\MailingCampaignStatus;
use App\Enums\MailingRecipientStatus;
use App\Jobs\SendMailingTestEmail;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingTemplate;
use App\Models\Package;
use App\Services\Mailing\MailingCampaignManager;
use App\Services\Mailing\MailingContentRenderer;
use App\Services\Mailing\MailingConfigurationException;
use App\Services\Mailing\MailingInfrastructureHealth;
use App\Services\Mailing\MailingRecipientAudience;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use LogicException;
use Throwable;
use Illuminate\Validation\ValidationException;

#[Layout('components.layouts.backoffice')]
#[Title('Mailing campaigns — Backoffice')]
class ManageMailingCampaigns extends Component
{
    use AuthorizesAdminActions, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 25;

    public bool $showComposer = false;

    public bool $showLaunchConfirmation = false;

    public bool $showActionConfirmation = false;

    public bool $showSaveTemplate = false;

    public ?string $editingCampaignId = null;

    public ?string $confirmCampaignId = null;

    public ?string $viewCampaignId = null;

    public ?string $templateId = null;

    public ?string $selectedTemplateId = null;

    public string $campaignName = '';

    public string $subject = '';

    public string $htmlBody = '<p>Hello {{first_name}},</p>';

    public string $textBody = '';

    public string $replyTo = '';

    public string $audienceMode = 'filters';

    /** @var list<string> */
    public array $selectedUserIds = [];

    public string $userSearch = '';

    public string $userStatus = 'all';

    public string $roleFilter = 'all';

    public string $activationFilter = 'all';

    public string $emailStatusFilter = 'all';

    public string $packageFilter = 'all';

    public string $shareFilter = 'all';

    public string $websiteFilter = 'all';

    public string $registeredFrom = '';

    public string $registeredTo = '';

    public string $scheduledAt = '';

    public string $testEmail = '';

    public string $templateName = '';

    public int $confirmRecipientCount = 0;

    public string $confirmAction = '';

    public ?string $confirmActionCampaignId = null;

    public function updated(string $property): void
    {
        if (in_array($property, [
            'search', 'statusFilter', 'perPage',
        ], true)) {
            $this->resetPage('campaignPage');
        }

        if (in_array($property, [
            'userSearch', 'userStatus', 'roleFilter', 'activationFilter', 'emailStatusFilter',
            'packageFilter', 'shareFilter', 'websiteFilter', 'registeredFrom', 'registeredTo',
        ], true)) {
            $this->resetPage('audiencePage');
        }
    }

    public function openComposer(): void
    {
        $this->authorizeWrite();
        $this->resetComposer();
        $this->showComposer = true;
    }

    public function editCampaign(string $campaignId): void
    {
        $this->authorizeWrite();
        $campaign = MailingCampaign::query()->findOrFail($campaignId);
        abort_unless($campaign->status === MailingCampaignStatus::DRAFT, 403, 'Only draft campaigns can be edited.');

        $this->resetComposer();
        $this->fillComposer($campaign);
        $this->showComposer = true;
    }

    public function duplicateCampaign(string $campaignId): void
    {
        $this->authorizeWrite();
        $source = MailingCampaign::query()->findOrFail($campaignId);
        $copy = MailingCampaign::query()->create([
            'admin_id' => auth('admin')->id(),
            'template_id' => $source->template_id,
            'name' => Str::limit($source->name.' (copy)', 160, ''),
            'subject' => $source->subject,
            'html_body' => $source->html_body,
            'text_body' => $source->text_body,
            'reply_to' => $source->reply_to,
            'filters' => $source->filters,
            'status' => MailingCampaignStatus::DRAFT,
            'scheduled_at' => null,
        ]);

        $this->resetComposer();
        $this->fillComposer($copy);
        $this->showComposer = true;
        $this->dispatch('toast', message: 'A new draft was created from the campaign.', type: 'success');
    }

    public function saveDraft(MailingRecipientAudience $audience, MailingContentRenderer $renderer): void
    {
        $this->authorizeWrite();
        $campaign = $this->persistDraft($audience, $renderer);
        $this->editingCampaignId = $campaign->id;
        $this->dispatch('toast', message: 'Campaign draft saved.', type: 'success');
    }

    public function requestLaunch(MailingRecipientAudience $audience, MailingContentRenderer $renderer): void
    {
        $this->authorizeWrite();

        try {
            $campaign = $this->persistDraft($audience, $renderer);
            $recipientCount = $audience->countDeliverable($campaign->filters ?? []);
            if ($recipientCount < 1) {
                throw ValidationException::withMessages(['audience' => 'No eligible users with a valid email address match this audience.']);
            }
            if ($recipientCount > (int) config('mailing.max_campaign_recipients', 250000)) {
                throw ValidationException::withMessages(['audience' => 'This audience is larger than the configured campaign limit. Narrow the filters or ask an administrator to review the limit.']);
            }

            $this->confirmRecipientCount = $recipientCount;
            $this->confirmCampaignId = $campaign->id;
            $this->showLaunchConfirmation = true;
            session()->put($this->launchConfirmationSessionKey(), [
                'campaign_id' => (string) $campaign->id,
                'recipient_count' => $recipientCount,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
        } catch (MailingConfigurationException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'warning');
        }
    }

    public function confirmLaunch(MailingCampaignManager $manager, MailingRecipientAudience $audience): void
    {
        $this->authorizeWrite();
        $sessionKey = $this->launchConfirmationSessionKey();
        $confirmation = session()->get($sessionKey);
        if (! $this->showLaunchConfirmation
            || ! is_array($confirmation)
            || ($confirmation['campaign_id'] ?? null) !== $this->confirmCampaignId
            || (int) ($confirmation['expires_at'] ?? 0) < now()->timestamp) {
            $this->cancelLaunchConfirmation();
            $this->dispatch('toast', message: 'Review the campaign and recipient count before confirming the launch.', type: 'warning');

            return;
        }

        $campaign = MailingCampaign::query()->findOrFail((string) $confirmation['campaign_id']);
        $confirmedCount = (int) ($confirmation['recipient_count'] ?? -1);
        $this->confirmRecipientCount = $confirmedCount;

        try {
            $freshCount = $audience->countDeliverable($campaign->filters ?? []);
            if ($freshCount !== $confirmedCount) {
                $confirmation['recipient_count'] = $freshCount;
                $confirmation['expires_at'] = now()->addMinutes(10)->timestamp;
                session()->put($sessionKey, $confirmation);
                $this->confirmRecipientCount = $freshCount;
                $this->dispatch('toast', message: 'The audience changed since review. Confirm the updated recipient count before continuing.', type: 'warning');

                return;
            }
            $manager->launch($campaign);
            $scheduled = $campaign->scheduled_at?->isFuture() ?? false;
            session()->forget($sessionKey);
            $this->showLaunchConfirmation = false;
            $this->showComposer = false;
            $this->confirmCampaignId = null;
            $this->viewCampaignId = $campaign->id;
            $message = $scheduled
                ? 'Campaign audience preparation is queued. The messages will be released at the scheduled time.'
                : 'Campaign audience preparation is queued. Delivery will run through the configured queue.';
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (MailingConfigurationException|LogicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'warning');
        }
    }

    public function cancelLaunchConfirmation(): void
    {
        session()->forget($this->launchConfirmationSessionKey());
        $this->showLaunchConfirmation = false;
        $this->confirmCampaignId = null;
        $this->confirmRecipientCount = 0;
    }

    private function performCancellation(string $campaignId, MailingCampaignManager $manager): void
    {
        try {
            $manager->cancel($campaignId);
            $this->closeActionConfirmation();
            $this->dispatch('toast', message: 'Campaign cancelled. Queued recipients were stopped; an email already in progress may finish.', type: 'success');
        } catch (LogicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'warning');
        }
    }

    private function performFailedRecipientRetry(string $campaignId, MailingCampaignManager $manager): void
    {
        try {
            $count = $manager->retryFailed($campaignId);
            $this->closeActionConfirmation();
            $this->dispatch('toast', message: number_format($count).' failed recipient(s) queued for a safe retry.', type: 'success');
        } catch (MailingConfigurationException|LogicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'warning');
        }
    }

    public function sendTestEmail(
        MailingRecipientAudience $audience,
        MailingContentRenderer $renderer,
        MailingInfrastructureHealth $health,
    ): void {
        $this->authorizeWrite();
        $campaign = $this->persistDraft($audience, $renderer);

        $this->validateOnly('testEmail', ['testEmail' => ['required', 'email:rfc', 'max:254']]);
        try {
            $health->assertReady();
            SendMailingTestEmail::dispatch($campaign->id, (string) auth('admin')->id(), $this->testEmail);
            $this->dispatch('toast', message: 'Test email queued. It will be sent through the configured mailer and queue.', type: 'success');
        } catch (MailingConfigurationException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'warning');
        } catch (Throwable $exception) {
            logger()->warning('Mailing test email could not be queued.', [
                'campaign_id' => $campaign->id,
                'admin_id' => auth('admin')->id(),
                'error_type' => class_basename($exception),
            ]);
            $this->dispatch('toast', message: 'The test email could not be queued. Verify the queue configuration and try again.', type: 'warning');
        }
    }

    public function saveAsTemplate(MailingRecipientAudience $audience, MailingContentRenderer $renderer): void
    {
        $this->authorizeWrite();
        $this->validate();
        $templateName = trim($this->templateName);
        if ($templateName === '') {
            $this->addError('templateName', 'Enter a name for this reusable template.');

            return;
        }

        $this->assertSafeHeaders();
        $template = MailingTemplate::query()->create([
            'admin_id' => auth('admin')->id(),
            'name' => Str::limit($templateName, 160, ''),
            'subject' => trim($this->subject),
            'html_body' => $renderer->sanitizeHtml($this->htmlBody),
            'text_body' => trim($this->textBody) !== '' ? $this->textBody : null,
            'reply_to' => trim($this->replyTo) !== '' ? trim($this->replyTo) : null,
        ]);

        $this->selectedTemplateId = $template->id;
        $this->templateId = $template->id;
        $this->showSaveTemplate = false;
        $this->templateName = '';
        $this->dispatch('toast', message: 'Reusable email template saved.', type: 'success');
    }

    public function loadTemplate(): void
    {
        if ($this->templateId !== null && $this->templateId !== '') {
            $this->applyTemplate($this->templateId);
        }
    }

    public function applyTemplate(string $templateId): void
    {
        $this->authorizeWrite();
        $template = MailingTemplate::query()->findOrFail($templateId);
        $this->templateId = $template->id;
        $this->selectedTemplateId = $template->id;
        $this->subject = $template->subject;
        $this->htmlBody = $template->html_body;
        $this->textBody = (string) ($template->text_body ?? '');
        $this->replyTo = (string) ($template->reply_to ?? '');
        $this->resetValidation(['subject', 'htmlBody', 'textBody', 'replyTo']);
    }

    public function deleteTemplate(string $templateId): void
    {
        $this->authorizeWrite();
        $template = MailingTemplate::query()->findOrFail($templateId);
        $template->delete();
        if ($this->selectedTemplateId === $templateId) {
            $this->selectedTemplateId = null;
        }
        if ($this->templateId === $templateId) {
            $this->templateId = null;
        }
        $this->dispatch('toast', message: 'Reusable template deleted. Existing campaigns are unchanged.', type: 'success');
    }

    public function selectVisibleUsers(MailingRecipientAudience $audience): void
    {
        $this->authorizeWrite();
        $query = $audience->query($this->currentFilters());
        $this->applyUserSearch($query);
        $page = max(1, $this->getPage('audiencePage'));
        $ids = $query->orderBy('users.id')->forPage($page, 10)->pluck('users.id')->map(fn ($id): string => (string) $id)->all();
        $selected = array_fill_keys($this->selectedUserIds, true);
        foreach ($ids as $id) {
            $selected[$id] = true;
        }
        $this->selectedUserIds = array_slice(array_keys($selected), 0, (int) config('mailing.max_selected_recipients', 10000));
    }

    public function clearSelection(): void
    {
        $this->authorizeWrite();
        $this->selectedUserIds = [];
    }

    public function viewCampaign(string $campaignId): void
    {
        $this->viewCampaignId = $campaignId;
        $this->resetPage('recipientPage');
    }

    public function closeDetail(): void
    {
        $this->viewCampaignId = null;
    }

    public function askToCancel(string $campaignId): void
    {
        $this->authorizeWrite();
        $this->storeActionConfirmation('cancel', $campaignId);
    }

    public function askToRetry(string $campaignId): void
    {
        $this->authorizeWrite();
        $this->storeActionConfirmation('retry', $campaignId);
    }

    public function confirmAction(MailingCampaignManager $manager): void
    {
        $this->authorizeWrite();
        $confirmation = session()->get($this->actionConfirmationSessionKey());
        if (! $this->showActionConfirmation
            || ! is_array($confirmation)
            || ($confirmation['action'] ?? null) !== $this->confirmAction
            || ($confirmation['campaign_id'] ?? null) !== $this->confirmActionCampaignId
            || (int) ($confirmation['expires_at'] ?? 0) < now()->timestamp) {
            $this->closeActionConfirmation();
            $this->dispatch('toast', message: 'Review and confirm this campaign action before continuing.', type: 'warning');

            return;
        }

        $campaignId = (string) $confirmation['campaign_id'];
        if ($confirmation['action'] === 'cancel') {
            $this->performCancellation($campaignId, $manager);
        } elseif ($confirmation['action'] === 'retry') {
            $this->performFailedRecipientRetry($campaignId, $manager);
        }
    }

    public function closeActionConfirmation(): void
    {
        session()->forget($this->actionConfirmationSessionKey());
        $this->showActionConfirmation = false;
        $this->confirmAction = '';
        $this->confirmActionCampaignId = null;
    }

    public function maskedEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'Address unavailable';
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefix = mb_substr($local, 0, 1);

        return $prefix.'•••@'.$domain;
    }

    public function render(
        MailingRecipientAudience $audience,
        MailingContentRenderer $renderer,
        MailingInfrastructureHealth $health,
    ): View {
        $campaignQuery = MailingCampaign::query()->with('admin')->latest();
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $campaignQuery->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', $term)
                ->orWhere('subject', 'like', $term));
        }
        if ($this->statusFilter !== 'all' && MailingCampaignStatus::tryFrom($this->statusFilter)) {
            $campaignQuery->where('status', $this->statusFilter);
        }

        $campaigns = $campaignQuery
            ->paginate($this->safePerPage(), ['*'], 'campaignPage')
            ->withQueryString();
        $detailCampaign = $this->viewCampaignId !== null
            ? MailingCampaign::query()->find($this->viewCampaignId)
            : null;
        $detailRecipients = $detailCampaign
            ? MailingCampaignRecipient::query()
                ->with('user')
                ->where('campaign_id', $detailCampaign->id)
                ->latest('updated_at')
                ->paginate(20, ['*'], 'recipientPage')
            : null;

        $filters = $this->currentFilters();
        $audienceCount = 0;
        $audienceUsers = null;
        $previewHtml = new HtmlString('');
        $previewSubject = '';
        if ($this->showComposer) {
            $audienceCount = $audience->count($filters, $this->audienceMode, $this->selectedUserIds);
            $userQuery = $audience->query($filters)->with('package')->latest('created_at')->orderBy('users.id');
            $this->applyUserSearch($userQuery);
            $audienceUsers = $userQuery->paginate(10, ['*'], 'audiencePage');
            $preview = $renderer->render(new \App\Services\Mailing\MailingCampaignContent(
                subject: $this->subject,
                html: $this->htmlBody,
                text: $this->textBody,
                replyTo: $this->replyTo !== '' ? $this->replyTo : null,
            ), $renderer->sampleUser());
            $previewHtml = new HtmlString($preview['html']);
            $previewSubject = $preview['subject'];
        }

        $healthReport = $health->report();
        $stats = [
            'campaigns' => MailingCampaign::query()->count(),
            'active' => MailingCampaign::query()->active()->count(),
            'queued' => (int) MailingCampaign::query()->sum('queued_count'),
            'sent' => (int) MailingCampaign::query()->sum('sent_count'),
            'failed' => (int) MailingCampaign::query()->sum('failed_count'),
        ];

        return view('livewire.backoffice.mailing.manage-mailing-campaigns', [
            'campaigns' => $campaigns,
            'detailCampaign' => $detailCampaign,
            'detailRecipients' => $detailRecipients,
            'audienceUsers' => $audienceUsers,
            'audienceCount' => $audienceCount,
            'previewHtml' => $previewHtml,
            'previewSubject' => $previewSubject,
            'packages' => Package::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'templates' => MailingTemplate::query()->latest()->limit(50)->get(),
            'healthReport' => $healthReport,
            'stats' => $stats,
            'campaignStatuses' => MailingCampaignStatus::cases(),
            'recipientStatuses' => MailingRecipientStatus::cases(),
        ]);
    }

    protected function rules(): array
    {
        $maxSelected = max(1, (int) config('mailing.max_selected_recipients', 10000));

        return [
            'campaignName' => ['required', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:180'],
            'htmlBody' => ['required', 'string', 'max:'.(int) config('mailing.max_html_bytes', 100000)],
            'textBody' => ['nullable', 'string', 'max:'.(int) config('mailing.max_text_bytes', 100000)],
            'replyTo' => ['nullable', 'email:rfc', 'max:254'],
            'audienceMode' => ['required', Rule::in(['filters', 'selected'])],
            'selectedUserIds' => ['array', 'max:'.$maxSelected],
            'selectedUserIds.*' => ['string', 'uuid'],
            'userStatus' => [Rule::in(['all', 'active', 'suspended'])],
            'roleFilter' => [Rule::in(['all', 'member', 'agent', 'special'])],
            'activationFilter' => [Rule::in(['all', 'activated', 'not_activated'])],
            'emailStatusFilter' => [Rule::in(['all', 'verified', 'unverified'])],
            'packageFilter' => ['required', 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail): void {
                if (in_array($value, ['all', 'active', 'inactive', 'none'], true)) {
                    return;
                }
                if (! is_string($value) || ! Str::isUuid($value) || ! Package::query()->whereKey($value)->exists()) {
                    $fail('Choose a valid package filter.');
                }
            }],
            'shareFilter' => [Rule::in(['all', 'active', 'inactive'])],
            'websiteFilter' => [Rule::in(['all', 'active', 'inactive'])],
            'registeredFrom' => ['nullable', 'date_format:Y-m-d'],
            'registeredTo' => ['nullable', 'date_format:Y-m-d'],
            'scheduledAt' => ['nullable', 'date', 'after:now'],
            'testEmail' => ['nullable', 'email:rfc', 'max:254'],
            'templateName' => ['nullable', 'string', 'max:160'],
            'templateId' => ['nullable', 'uuid', 'exists:mailing_templates,id'],
            'selectedTemplateId' => ['nullable', 'uuid', 'exists:mailing_templates,id'],
            'userSearch' => ['nullable', 'string', 'max:200'],
        ];
    }

    private function persistDraft(MailingRecipientAudience $audience, MailingContentRenderer $renderer): MailingCampaign
    {
        $this->validate();
        $this->assertSafeHeaders();
        if ($this->registeredFrom !== '' && $this->registeredTo !== '' && $this->registeredFrom > $this->registeredTo) {
            throw ValidationException::withMessages(['registeredTo' => 'The registration end date must be on or after the start date.']);
        }

        $campaign = $this->editingCampaignId !== null
            ? MailingCampaign::query()->findOrFail($this->editingCampaignId)
            : new MailingCampaign;
        if ($campaign->exists && $campaign->status !== MailingCampaignStatus::DRAFT) {
            throw new LogicException('Only draft campaigns can be edited.');
        }

        $filters = $audience->campaignFilters($this->currentFilters(), $this->audienceMode, $this->selectedUserIds);
        $campaign->fill([
            'admin_id' => $campaign->admin_id ?? auth('admin')->id(),
            'template_id' => $this->selectedTemplateId,
            'name' => trim($this->campaignName),
            'subject' => trim($this->subject),
            'html_body' => $renderer->sanitizeHtml($this->htmlBody),
            'text_body' => trim($this->textBody) !== '' ? $this->textBody : null,
            'reply_to' => trim($this->replyTo) !== '' ? trim($this->replyTo) : null,
            'filters' => $filters,
            'status' => MailingCampaignStatus::DRAFT,
            'scheduled_at' => trim($this->scheduledAt) !== '' ? Carbon::parse($this->scheduledAt) : null,
        ]);
        $campaign->save();

        $this->editingCampaignId = $campaign->id;

        return $campaign->refresh();
    }

    private function currentFilters(): array
    {
        return [
            'status' => $this->userStatus,
            'role' => $this->roleFilter,
            'activation' => $this->activationFilter,
            'email_status' => $this->emailStatusFilter,
            'package' => $this->packageFilter,
            'share' => $this->shareFilter,
            'website' => $this->websiteFilter,
            'registered_from' => $this->registeredFrom,
            'registered_to' => $this->registeredTo,
        ];
    }

    private function applyUserSearch(Builder $query): void
    {
        $term = trim($this->userSearch);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(fn (Builder $users): Builder => $users
            ->where('username', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like));
    }

    private function fillComposer(MailingCampaign $campaign): void
    {
        $filters = $campaign->filters ?? [];
        $this->editingCampaignId = $campaign->id;
        $this->selectedTemplateId = $campaign->template_id;
        $this->templateId = $campaign->template_id;
        $this->campaignName = $campaign->name;
        $this->subject = $campaign->subject;
        $this->htmlBody = $campaign->html_body;
        $this->textBody = (string) ($campaign->text_body ?? '');
        $this->replyTo = (string) ($campaign->reply_to ?? '');
        $this->audienceMode = ($filters['audience_mode'] ?? 'filters') === 'selected' ? 'selected' : 'filters';
        $this->selectedUserIds = is_array($filters['selected_user_ids'] ?? null) ? $filters['selected_user_ids'] : [];
        $this->userStatus = (string) ($filters['status'] ?? 'all');
        $this->roleFilter = (string) ($filters['role'] ?? 'all');
        $this->activationFilter = (string) ($filters['activation'] ?? 'all');
        $this->emailStatusFilter = (string) ($filters['email_status'] ?? 'all');
        $this->packageFilter = (string) ($filters['package'] ?? 'all');
        $this->shareFilter = (string) ($filters['share'] ?? 'all');
        $this->websiteFilter = (string) ($filters['website'] ?? 'all');
        $this->registeredFrom = (string) ($filters['registered_from'] ?? '');
        $this->registeredTo = (string) ($filters['registered_to'] ?? '');
        $this->scheduledAt = $campaign->scheduled_at?->format('Y-m-d\TH:i') ?? '';
        $this->testEmail = (string) (auth('admin')->user()?->email ?? '');
    }

    private function resetComposer(): void
    {
        $this->resetValidation();
        $this->editingCampaignId = null;
        $this->selectedTemplateId = null;
        $this->templateId = null;
        $this->campaignName = '';
        $this->subject = '';
        $this->htmlBody = '<p>Hello {{first_name}},</p>';
        $this->textBody = '';
        $this->replyTo = '';
        $this->audienceMode = 'filters';
        $this->selectedUserIds = [];
        $this->userSearch = '';
        $this->userStatus = 'all';
        $this->roleFilter = 'all';
        $this->activationFilter = 'all';
        $this->emailStatusFilter = 'all';
        $this->packageFilter = 'all';
        $this->shareFilter = 'all';
        $this->websiteFilter = 'all';
        $this->registeredFrom = '';
        $this->registeredTo = '';
        $this->scheduledAt = '';
        $this->testEmail = (string) (auth('admin')->user()?->email ?? '');
        $this->templateName = '';
        $this->showSaveTemplate = false;
    }

    private function assertSafeHeaders(): void
    {
        if (preg_match('/[\r\n\0]/', $this->subject)) {
            throw ValidationException::withMessages(['subject' => 'Subject lines cannot contain line breaks.']);
        }
        if (preg_match('/[\r\n\0]/', $this->replyTo)) {
            throw ValidationException::withMessages(['replyTo' => 'Reply-to must be a single valid email address.']);
        }
    }

    private function launchConfirmationSessionKey(): string
    {
        return 'mailing.launch_confirmation.'.(string) auth('admin')->id();
    }

    private function actionConfirmationSessionKey(): string
    {
        return 'mailing.action_confirmation.'.(string) auth('admin')->id();
    }

    private function storeActionConfirmation(string $action, string $campaignId): void
    {
        $this->confirmAction = $action;
        $this->confirmActionCampaignId = $campaignId;
        $this->showActionConfirmation = true;
        session()->put($this->actionConfirmationSessionKey(), [
            'action' => $action,
            'campaign_id' => $campaignId,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    private function safePerPage(): int
    {
        return in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
    }
}
