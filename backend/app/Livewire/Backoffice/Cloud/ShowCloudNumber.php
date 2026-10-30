<?php

namespace App\Livewire\Backoffice\Cloud;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CloudDistribution;
use App\Models\CloudNumber;
use App\Services\CloudCallService;
use App\Services\CloudDistributionService;
use App\Services\CloudInboxService;
use App\Services\CloudVoiceService;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Sponsor Number — Backoffice')]
class ShowCloudNumber extends Component
{
    use AuthorizesAdminActions, WithFileUploads, WithPagination;

    public CloudNumber $number;

    public bool $showEditModal = false;

    public string $name = '';

    public string $lineNumber = '';

    public string $brand = '';

    public string $callerName = '';

    public string $popupMessage = '';

    public string $claimUrl = '';

    public $avatar = null;

    public ?string $existingAvatarUrl = null;

    public string $earningPerCall = '';

    public string $sortOrder = '0';

    public bool $isActive = true;

    public string $title = '';

    public string $message = '';

    public string $voice = '';

    public string $lang = '';

    public string $smsSubject = '';

    public string $smsBody = '';

    public string $smsEarnRate = '';

    public string $bonusType = CloudDistribution::TYPE_PROMOTER;

    public string $bonusAmount = '';

    public string $bonusNote = '';

    public function mount(CloudNumber $number): void
    {
        $this->number = $number;
        // ElevenLabs on → default to the first realistic voice; off → Default (browser voice).
        $this->voice = app(CloudVoiceService::class)->voiceIds()[0] ?? '';
    }

    public function openEdit(): void
    {
        $this->authorizeWrite();
        $this->name = (string) $this->number->name;
        $this->lineNumber = (string) $this->number->line_number;
        $this->brand = (string) $this->number->brand;
        $this->callerName = (string) $this->number->caller_name;
        $this->popupMessage = (string) $this->number->popup_message;
        $this->claimUrl = (string) $this->number->claim_url;
        $this->avatar = null;
        $this->existingAvatarUrl = $this->number->avatar_url;
        $this->earningPerCall = (string) $this->number->earning_per_call;
        $this->sortOrder = (string) $this->number->sort_order;
        $this->isActive = (bool) $this->number->is_active;
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'lineNumber' => ['required', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'callerName' => ['nullable', 'string', 'max:120'],
            'popupMessage' => ['nullable', 'string', 'max:500'],
            'claimUrl' => ['nullable', 'url', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'earningPerCall' => ['required', 'numeric', 'min:0'],
            'sortOrder' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'name' => $this->name,
            'line_number' => $this->lineNumber,
            'brand' => $this->brand ?: null,
            'caller_name' => $this->callerName ?: null,
            'popup_message' => $this->popupMessage ?: null,
            'claim_url' => $this->claimUrl ?: null,
            'earning_per_call' => $this->earningPerCall,
            'sort_order' => (int) $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        $oldAvatar = $this->number->avatar;
        if ($this->avatar) {
            $attributes['avatar'] = app(ImageCompressionService::class)->compressAndStore($this->avatar, 'cloud-avatars', null, 82, 512, 512);
        }

        $this->number->update($attributes);

        if ($this->avatar && $oldAvatar && $oldAvatar !== $this->number->avatar) {
            Storage::delete($oldAvatar);
        }

        $this->avatar = null;
        $this->showEditModal = false;
        $this->number->refresh();
        $this->dispatch('toast', message: 'Sponsor number updated successfully', type: 'success');
    }

    public function distributeBonus(CloudDistributionService $service): void
    {
        $this->authorizeWrite();

        $this->validate([
            'bonusType' => ['required', 'in:'.CloudDistribution::TYPE_PROMOTER.','.CloudDistribution::TYPE_SHAREHOLDER],
            'bonusAmount' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
            'bonusNote' => ['nullable', 'string', 'max:140'],
        ], attributes: ['bonusAmount' => 'amount', 'bonusNote' => 'note']);

        $savers = $this->number->users()->count();
        if ($savers === 0) {
            $this->dispatch('toast', message: 'No one has saved this contact yet — nothing to distribute.', type: 'warning');

            return;
        }

        $distribution = $service->distribute($this->number, $this->bonusType, (float) $this->bonusAmount, trim($this->bonusNote), auth('admin')->id());

        $this->reset('bonusAmount', 'bonusNote');
        $this->bonusType = CloudDistribution::TYPE_PROMOTER;
        $this->resetValidation();
        $this->dispatch('toast', message: ucfirst($distribution->type).' bonus of '.money((float) $distribution->amount).' is now claimable by '.$distribution->recipients_count.' saver(s).', type: 'success');
    }

    public function sendSms(CloudInboxService $service): void
    {
        $this->authorizeWrite();

        $this->validate([
            'smsSubject' => ['required', 'string', 'max:140'],
            'smsBody' => ['required', 'string', 'max:1000'],
            'smsEarnRate' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
        ], attributes: ['smsSubject' => 'subject', 'smsBody' => 'message', 'smsEarnRate' => 'earn rate']);

        $message = $service->sendSponsored($this->number, trim($this->smsSubject), trim($this->smsBody), (float) $this->smsEarnRate, auth('admin')->id());

        $this->reset('smsSubject', 'smsBody', 'smsEarnRate');
        $this->resetValidation();
        $this->dispatch('toast', message: 'SMS sent to '.$message->recipients_count.' inbox(es).', type: 'success');
    }

    public function sendBroadcast(CloudCallService $service): void
    {
        $this->authorizeWrite();

        if (! $this->number->is_active) {
            $this->dispatch('toast', message: 'Activate this number before broadcasting.', type: 'warning');

            return;
        }

        if ($this->number->notes()->where('is_active', true)->doesntExist()) {
            $this->dispatch('toast', message: 'Add at least one voice note before broadcasting.', type: 'warning');

            return;
        }

        $minutes = (int) config('settings.cloud.broadcast_expiry_minutes', 10);
        $service->createBroadcast($this->number, auth('admin')->id(), $minutes);

        $this->dispatch('toast', message: 'Broadcast sent — online users will receive an incoming call.', type: 'success');
    }

    public function addNote(CloudVoiceService $tts): void
    {
        $this->authorizeWrite();

        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
            'voice' => ['nullable', Rule::in([...$tts->voiceIds(), ''])],
        ]);

        $voice = $this->voice; // '' = Default → the call reads it in the browser's own voice.

        $note = $this->number->notes()->create([
            'title' => $this->title,
            'message' => $this->message,
            'voice' => $voice ?: null,
            'voice_name' => $voice === '' ? 'Default' : $tts->labelFor($voice),
            'lang' => null,
            'sort_order' => (int) ($this->number->notes()->max('sort_order') ?? 0) + 1,
            'is_active' => ! $this->number->notes()->exists(),
        ]);

        $generated = $voice !== '' && $tts->generateForNote($note);

        $this->reset('title', 'message');
        $this->voice = app(CloudVoiceService::class)->voiceIds()[0] ?? '';
        $this->resetValidation();

        [$toast, $tone] = match (true) {
            $voice === '' => ['Voice message added — the call reads it in the browser’s default voice.', 'success'],
            $generated => ['Voice message added — realistic audio generated.', 'success'],
            default => ['Voice message added — audio couldn’t be generated this time, so the call will use the browser voice.', 'warning'],
        };

        $this->dispatch('toast', message: $toast, type: $tone);
    }

    public function previewVoice(CloudVoiceService $tts): void
    {
        $this->authorizeWrite();

        $this->validate([
            'message' => ['required', 'string', 'max:1000'],
            'voice' => ['nullable', Rule::in([...$tts->voiceIds(), ''])],
        ]);

        // Default (no ElevenLabs voice) → preview in the browser's own voice, client-side.
        if ($this->voice === '') {
            $this->dispatch('play-audio', text: $this->message);

            return;
        }

        $uri = $tts->previewDataUri($this->message, $this->voice);

        // Generation unavailable — preview in the browser voice instead of surfacing an error.
        if (! $uri) {
            $this->dispatch('play-audio', text: $this->message);

            return;
        }

        $this->dispatch('play-audio', src: $uri);
    }

    public function refreshVoices(CloudVoiceService $tts): void
    {
        $this->authorizeWrite();

        $count = count($tts->refreshVoices());

        $this->dispatch('toast',
            message: $count > 0
                ? 'Voice list refreshed — '.$count.' voice(s) from ElevenLabs.'
                : 'No voices found — add some in your ElevenLabs library, then refresh.',
            type: $count > 0 ? 'success' : 'info');
    }

    public function toggleNote(string $id): void
    {
        $this->authorizeWrite();
        $note = $this->number->notes()->findOrFail($id);

        if ($note->is_active) {
            $note->update(['is_active' => false]);
            $this->dispatch('toast', message: 'Voice message turned off — set one active so it plays on calls.', type: 'success');

            return;
        }

        DB::transaction(function () use ($note): void {
            $this->number->notes()->whereKeyNot($note->id)->update(['is_active' => false]);
            $note->update(['is_active' => true]);
        });

        $this->dispatch('toast', message: 'Voice message set active — it will play on calls.', type: 'success');
    }

    public function deleteNote(string $id): void
    {
        $this->authorizeWrite();

        $note = $this->number->notes()->findOrFail($id);

        $this->safeDelete(fn () => $note->delete(), 'voice message');
    }

    public function render(): View
    {
        $voices = app(CloudVoiceService::class)->voices();

        return view('livewire.backoffice.cloud.show-cloud-number', [
            'number' => $this->number,
            'notes' => $this->number->notes()->orderBy('sort_order')->get(),
            'savedCount' => $this->number->users()->count(),
            'voices' => $voices,
            'ttsEnabled' => count($voices) > 0,
        ]);
    }
}
