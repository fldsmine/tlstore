<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Models\AuditLog;
use App\Models\NotificationTemplate;
use App\Models\SystemSetting;
use App\Features\Notifications\Services\NotificationTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class NotificationTemplateAdminController
{
    private const MAILERS = ['smtp', 'resend', 'mailgun', 'mailjet'];

    public function index(): View
    {
        $this->authorizeAccess();
        $settings = SystemSetting::query()->whereIn('setting_key', [
            'notifications.email.enabled', 'notifications.email.mailer',
            'notifications.email.from_address', 'notifications.email.from_name',
            'notifications.push.enabled', 'notifications.sms.enabled',
        ])->get()->keyBy('setting_key');

        return view('admin.notifications.index', [
            'title' => 'Email & notification templates',
            'templates' => NotificationTemplate::query()->orderBy('name')->paginate(30),
            'settings' => $settings,
            'mailers' => self::MAILERS,
            'mailerStatus' => $this->mailerStatus(),
        ]);
    }

    public function edit(NotificationTemplate $template, NotificationTemplateService $templates): View
    {
        $this->authorizeAccess();
        return view('admin.notifications.edit', [
            'title' => 'Edit notification template',
            'template' => $template,
            'editorHtml' => $templates->sanitizeTemplateHtml((string) old('email_body', $template->email_body)),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $this->authorizeAccess();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:191'],
            'email_body' => ['nullable', 'string', 'max:30000'],
            'firebase_body' => ['nullable', 'string', 'max:5000'],
            'sms_body' => ['nullable', 'string', 'max:5000'],
            'email_status' => ['sometimes', 'boolean'],
            'firebase_status' => ['sometimes', 'boolean'],
            'sms_status' => ['sometimes', 'boolean'],
        ]);
        foreach (['email_status', 'firebase_status', 'sms_status'] as $flag) $data[$flag] = $request->boolean($flag);
        $data['email_body'] = app(NotificationTemplateService::class)->sanitizeTemplateHtml((string) ($data['email_body'] ?? ''));
        $before = $template->only(array_keys($data));
        $template->fill($data)->save();
        $this->audit('notification_template.updated', $template, $before, $template->only(array_keys($data)));

        return redirect()->route('admin.notifications.index')->with('success', 'Notification template updated.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $data = $request->validate([
            'email_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'mailer' => ['required', Rule::in(self::MAILERS)],
            'from_address' => ['required', 'email:rfc', 'max:191'],
            'from_name' => ['required', 'string', 'max:120'],
        ]);
        $values = [
            'notifications.email.enabled' => $request->boolean('email_enabled'),
            'notifications.push.enabled' => $request->boolean('push_enabled'),
            'notifications.email.mailer' => $data['mailer'],
            'notifications.email.from_address' => $data['from_address'],
            'notifications.email.from_name' => $data['from_name'],
        ];
        foreach ($values as $key => $value) {
            $setting = SystemSetting::query()->firstOrNew(['setting_key' => $key]);
            $before[$key] = $setting->value;
            $setting->fill(['value' => $value, 'is_public' => false, 'updated_by_admin_id' => Auth::guard('admin')->id()])->save();
            $after[$key] = $setting->value;
        }
        $this->audit('notification_settings.updated', $setting, $before, $after);

        return back()->with('success', 'Notification delivery settings saved. Mail-provider credentials remain in the server environment configuration.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:191']]);
        $emailEnabled = SystemSetting::query()->where('setting_key', 'notifications.email.enabled')->first()?->value ?? false;
        abort_unless(filter_var($emailEnabled, FILTER_VALIDATE_BOOLEAN), 422, 'Enable email notifications before sending a test.');

        $mailer = (string) (SystemSetting::query()->where('setting_key', 'notifications.email.mailer')->first()?->value ?? config('mail.default', 'smtp'));
        abort_unless(in_array($mailer, self::MAILERS, true) && array_key_exists($mailer, config('mail.mailers', [])), 422, 'The selected mailer is not configured.');
        $fromAddress = (string) (SystemSetting::query()->where('setting_key', 'notifications.email.from_address')->first()?->value ?? config('mail.from.address'));
        $fromName = (string) (SystemSetting::query()->where('setting_key', 'notifications.email.from_name')->first()?->value ?? config('mail.from.name'));
        try {
            Mail::mailer($mailer)->html('<p>TLStore email delivery is configured and responding.</p>', static function ($message) use ($data, $fromAddress, $fromName): void {
                $message->to($data['email'])->from($fromAddress, $fromName)->subject('TLStore email configuration test');
            });
        } catch (\Throwable $error) {
            report($error);
            return back()->withErrors(['email' => 'Test email failed. Check the selected provider configuration and server mail logs.']);
        }

        return back()->with('success', 'Test email sent to '.$data['email'].'.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(in_array(Auth::guard('admin')->user()?->role, ['super_admin', 'admin'], true), 403);
    }

    private function mailerStatus(): array
    {
        $status = [];
        foreach (self::MAILERS as $mailer) {
            $config = config('mail.mailers.'.$mailer, []);
            $ready = match ($mailer) {
                'smtp' => ! empty($config['host']) && $config['host'] !== '127.0.0.1' && ! empty($config['username']) && ! empty($config['password']),
                'resend' => ! empty($config['password']),
                'mailgun' => ! empty($config['username']) && ! empty($config['password']),
                'mailjet' => ! empty($config['username']) && ! empty($config['password']),
            };
            $status[$mailer] = $ready ? 'Configured' : 'Needs server credentials';
        }
        return $status;
    }

    private function audit(string $action, NotificationTemplate|SystemSetting $entity, array $before, array $after): void
    {
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => (string) $entity->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
