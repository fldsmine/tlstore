<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Application\Telegram\TelegramClient;
use App\Application\Telegram\TelegramDispatcher;
use App\Application\Telegram\TelegramHtml;
use App\Application\Telegram\TelegramNotifier;
use App\Application\Telegram\TelegramSettings;
use App\Models\AuditLog;
use App\Models\TelegramMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin console → Telegram messaging.
 *
 * The section owns three things: the bot configuration (token stored
 * encrypted, destination chat id, feature switches), the announcement
 * composer with its history, and diagnostics (connection check + test
 * message). Access follows the console's existing admin guard plus the
 * super_admin/admin roles; finance, moderator and content roles cannot open it.
 */
final class TelegramAdminController
{
    private const MANAGE_ROLES = ['super_admin', 'admin'];

    public function __construct(
        private readonly TelegramSettings $settings,
        private readonly TelegramDispatcher $dispatcher,
        private readonly TelegramClient $client,
        private readonly TelegramNotifier $notifier,
    ) {}

    public function index(): View
    {
        $this->authorizeAccess();

        return view('admin.telegram.index', [
            'title' => 'Telegram messaging',
            'settings' => $this->settings,
            'maskedToken' => $this->settings->maskedToken(),
            'chatId' => $this->settings->chatId(),
            'configured' => $this->settings->configured(),
            'enabled' => $this->settings->enabled(),
            'announcementsEnabled' => $this->settings->announcementsEnabled(),
            'withdrawalsEnabled' => $this->settings->withdrawalNotificationsEnabled(),
            'statusUpdatesEnabled' => $this->settings->withdrawalStatusUpdatesEnabled(),
            'tokenFromEnvironment' => $this->settings->tokenFromEnvironment(),
            'lastCheck' => $this->settings->lastCheck(),
            'messages' => TelegramMessage::query()
                ->with(['admin:id,full_name'])
                ->latest('id')
                ->paginate(20),
            'maxLength' => (int) config('telegram.message_max_length', 4096),
            'captionMaxLength' => (int) config('telegram.caption_max_length', 1024),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'bot_token' => ['nullable', 'string', 'max:200', 'regex:/^\d{5,20}:[A-Za-z0-9_-]{20,}$/'],
            'chat_id' => ['nullable', 'string', 'max:64', 'regex:/^(-?\d{4,20}|@[A-Za-z0-9_]{5,64})$/'],
        ], [
            'bot_token.regex' => 'A bot token looks like 123456789:AAH… — copy the full token from @BotFather.',
            'chat_id.regex' => 'Use a numeric id (e.g. -1001234567890) or a public @channelusername.',
        ]);

        $adminId = Auth::guard('admin')->id();
        $before = $this->settingsSnapshot();

        if (filled($data['bot_token'] ?? null)) {
            $this->settings->saveToken((string) $data['bot_token'], $adminId);
        }
        $this->settings->saveChatId($data['chat_id'] ?? null, $adminId);
        $this->settings->saveFlag(TelegramSettings::ENABLED, $request->boolean('enabled'), $adminId);
        $this->settings->saveFlag(TelegramSettings::ANNOUNCEMENTS, $request->boolean('announcements_enabled'), $adminId);
        $this->settings->saveFlag(TelegramSettings::WITHDRAWALS, $request->boolean('withdrawals_enabled'), $adminId);
        $this->settings->saveFlag(TelegramSettings::STATUS_UPDATES, $request->boolean('status_updates_enabled'), $adminId);

        $after = $this->settingsSnapshot();
        $after['token_updated'] = filled($data['bot_token'] ?? null);

        $this->audit('telegram.settings_updated', $before, $after);

        return back()->with('success', 'Telegram settings saved.'.($after['token_updated'] ? ' The bot token was replaced and is stored encrypted.' : ''));
    }

    public function testConnection(): RedirectResponse
    {
        $this->authorizeAccess();

        if ($this->settings->token() === null) {
            return back()->withErrors(['telegram' => 'Add a Telegram bot token before testing the connection.']);
        }

        $me = $this->client->getMe();
        if ($me->failed()) {
            return back()->withErrors(['telegram' => 'Telegram rejected the bot token: '.$me->error]);
        }

        $chatId = $this->settings->chatId();
        if ($chatId === null) {
            return back()->withErrors(['telegram' => 'Add the destination group or channel id before testing the connection.']);
        }

        $chat = $this->client->getChat($chatId);
        if ($chat->failed()) {
            return back()->withErrors([
                'telegram' => 'The bot cannot reach that chat: '.$chat->error.' Add the bot to the group/channel (as an administrator) and try again.',
            ]);
        }

        $username = (string) data_get($me->payload, 'result.username', 'unknown');
        $title = (string) (data_get($chat->payload, 'result.title') ?: data_get($chat->payload, 'result.username') ?: $chatId);
        $type = (string) data_get($chat->payload, 'result.type', 'chat');
        $summary = 'Connected to '.$title.' ('.$type.') as @'.$username;

        $this->settings->recordCheck($summary, Auth::guard('admin')->id());

        return back()->with('success', $summary.'.');
    }

    public function sendTest(): RedirectResponse
    {
        $this->authorizeAccess();

        if (! $this->settings->configured()) {
            return back()->withErrors(['telegram' => 'Save a bot token and destination chat before sending a test message.']);
        }

        $message = TelegramMessage::query()->create([
            'kind' => TelegramMessage::KIND_TEST,
            'chat_id' => (string) $this->settings->chatId(),
            'body' => $this->notifier->testMessage(),
            'status' => TelegramMessage::STATUS_QUEUED,
            'sent_by_admin_id' => Auth::guard('admin')->id(),
            'meta' => ['disable_web_page_preview' => true],
        ]);

        $result = $this->dispatcher->deliver($message);

        return $result->ok
            ? back()->with('success', 'Test message delivered to '.$this->settings->chatId().'.')
            : back()->withErrors(['telegram' => 'Test message failed: '.$result->error]);
    }

    public function send(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        if (! $this->settings->configured()) {
            return back()->withErrors(['telegram' => 'Configure the Telegram bot token and destination chat first.']);
        }
        if (! $this->settings->enabled()) {
            return back()->withErrors(['telegram' => 'Telegram notifications are switched off. Enable them to send announcements.']);
        }
        if (! $this->settings->announcementsEnabled()) {
            return back()->withErrors(['telegram' => 'Admin announcements are switched off in the settings above.']);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4096'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $html = TelegramHtml::sanitize((string) $data['message']);
        if (TelegramHtml::visibleLength($html) === 0) {
            return back()->withInput()->withErrors(['message' => 'Write a message with some visible text before sending.']);
        }

        $image = $request->file('image');
        if ($image !== null && TelegramHtml::visibleLength($html) > (int) config('telegram.caption_max_length', 1024)) {
            return back()->withInput()->withErrors([
                'message' => 'Messages sent with an image are limited to '.(int) config('telegram.caption_max_length', 1024).' characters. Send the long text as a text-only announcement, or shorten the caption.',
            ]);
        }

        $path = $image?->store((string) config('telegram.announcement_directory', 'telegram/announcements'), 'local');

        $message = TelegramMessage::query()->create([
            'kind' => TelegramMessage::KIND_ANNOUNCEMENT,
            'chat_id' => (string) $this->settings->chatId(),
            'body' => $html,
            'attachment_type' => $path !== null ? 'photo' : null,
            'attachment_path' => $path,
            'status' => TelegramMessage::STATUS_QUEUED,
            'sent_by_admin_id' => Auth::guard('admin')->id(),
            'meta' => ['disable_web_page_preview' => true],
        ]);

        $result = $this->dispatcher->deliver($message);

        if ($result->failed()) {
            return back()->withInput()->withErrors([
                'telegram' => 'Telegram rejected the message: '.$result->error.' It is saved in the history so it can be re-sent.',
            ]);
        }

        $this->audit('telegram.announcement_sent', [], [
            'message_id' => $message->id,
            'chat_id' => $message->chat_id,
            'with_image' => $path !== null,
        ]);

        return back()->with('success', $path !== null
            ? 'Announcement with image sent to '.$this->settings->chatId().'.'
            : 'Announcement sent to '.$this->settings->chatId().'.');
    }

    public function resend(TelegramMessage $message): RedirectResponse
    {
        $this->authorizeAccess();

        if ($message->status === TelegramMessage::STATUS_SENT) {
            return back()->withErrors(['telegram' => 'That message has already been delivered.']);
        }
        if (! $this->settings->configured()) {
            return back()->withErrors(['telegram' => 'Configure the Telegram bot before re-sending.']);
        }

        $message->forceFill(['chat_id' => (string) $this->settings->chatId()])->save();
        $result = $this->dispatcher->deliver($message);

        return $result->ok
            ? back()->with('success', 'Message re-sent successfully.')
            : back()->withErrors(['telegram' => 'Re-send failed: '.$result->error]);
    }

    /** Admin-only passthrough for uploaded announcement images (kept out of `public/`). */
    public function image(TelegramMessage $message)
    {
        $this->authorizeAccess();

        $path = (string) $message->attachment_path;
        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function authorizeAccess(): void
    {
        abort_unless(in_array(Auth::guard('admin')->user()?->role, self::MANAGE_ROLES, true), 403);
    }

    /** @return array<string, mixed> */
    private function settingsSnapshot(): array
    {
        return [
            'chat_id' => $this->settings->chatId(),
            'enabled' => $this->settings->enabled(),
            'announcements_enabled' => $this->settings->announcementsEnabled(),
            'withdrawals_enabled' => $this->settings->withdrawalNotificationsEnabled(),
            'status_updates_enabled' => $this->settings->withdrawalStatusUpdatesEnabled(),
            'token_configured' => $this->settings->token() !== null,
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function audit(string $action, array $before, array $after): void
    {
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'entity_type' => 'SystemSetting',
            'entity_id' => 'telegram',
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
