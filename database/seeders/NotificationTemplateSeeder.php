<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = array_map(static fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now], $this->templates());
        DB::table('notification_templates')->insertOrIgnore($rows);
    }

    /** @return list<array<string,mixed>> */
    private function templates(): array
    {
        $rows = [
            ['review_decision', 'App review decision', 'Review update · {{app_name}}', 'Hello {{fullname}},\n\n{{message}}\n\nThanks,\n{{site_name}}', 'App review update: {{app_name}} — {{status}}. {{message}}'],
            ['ad_share_decision', 'Ad share decision', 'Ad share update · {{ad_title}}', 'Hello {{fullname}},\n\n{{message}}\n\nThanks,\n{{site_name}}', 'Ad share update: {{ad_title}} — {{status}}. {{message}}'],
            ['manual_deposit_decision', 'Manual top-up decision', 'Top-up update · {{status}}', 'Hello {{fullname}},\n\n{{message}}\n\nThanks,\n{{site_name}}', 'Top-up update: {{message}}'],
            ['withdrawal_decision', 'Withdrawal decision', 'Withdrawal update · {{status}}', 'Hello {{fullname}},\n\n{{message}}\n\nThanks,\n{{site_name}}', 'Withdrawal update: {{message}}'],
            ['review_submitted', 'App review submitted', 'Review received · {{app_name}}', 'Hello {{fullname}},\n\nWe received your review for {{app_name}}. It is awaiting review by our moderation team.\n\n{{site_name}}', 'We received your review for {{app_name}}. It is awaiting moderation.'],
            ['ad_share_submitted', 'Ad share proof submitted', 'Share proof received · {{ad_title}}', 'Hello {{fullname}},\n\nWe received your share proof for {{ad_title}}. It is awaiting verification.\n\n{{site_name}}', 'We received your share proof for {{ad_title}}. It is awaiting verification.'],
            ['subscription_activated', 'Subscription activated', 'You joined {{plan_name}}', 'Hello {{fullname}},\n\nYour {{plan_name}} package is active until {{ends_at}}.\n\n{{site_name}}', 'Your {{plan_name}} package is active until {{ends_at}}.'],
            ['withdrawal_requested', 'Withdrawal requested', 'Withdrawal request received · {{amount}} {{currency}}', 'Hello {{fullname}},\n\nYour withdrawal request for {{amount}} {{currency}} was received and is pending processing.\nReference: {{reference}}\n\n{{site_name}}', 'Your withdrawal request for {{amount}} {{currency}} was received. Reference: {{reference}}.'],
            ['manual_deposit_submitted', 'Manual top-up submitted', 'Top-up proof received · {{amount}} {{currency}}', 'Hello {{fullname}},\n\nYour manual top-up proof for {{amount}} {{currency}} was received and is awaiting approval.\nReference: {{reference}}\n\n{{site_name}}', 'Your manual top-up proof for {{amount}} {{currency}} was received and is awaiting approval.'],
            ['topup_completed', 'Top-up completed', 'Top-up received · {{amount}} {{currency}}', 'Hello {{fullname}},\n\nYour top-up of {{amount}} {{currency}} has been added to your Top-up Funds wallet.\nReference: {{reference}}\n\n{{site_name}}', 'Your top-up of {{amount}} {{currency}} has been credited.'],
            ['support_ticket_reply', 'Support ticket reply', 'Support replied · {{ticket_number}}', 'Hello {{fullname}},\n\nOur support team replied to ticket {{ticket_number}}: {{ticket_subject}}\n\n{{reply}}\n\nOpen the app to continue the conversation.\n{{site_name}}', 'Support replied to ticket {{ticket_number}}: {{reply}}'],
            ['password_reset', 'Password reset code', 'Reset your {{site_name}} password', 'Hello {{fullname}},\n\nUse this one-time code to reset your password:\n\n{{code}}\n\nThis code expires in 60 minutes. If you did not request it, ignore this email.\n\n{{site_name}}', ''],
        ];

        return array_map(static function (array $item): array {
            [$act, $name, $subject, $email, $push] = $item;
            $codes = [];
            preg_match_all('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', $subject."\n".$email."\n".$push, $matches);
            foreach (array_unique($matches[1] ?? []) as $code) $codes[$code] = ucfirst(str_replace(['_', '.'], ' ', $code));

            return [
                'act' => $act,
                'name' => $name,
                'subject' => $subject,
                'email_body' => $email,
                'sms_body' => null,
                'shortcodes' => json_encode($codes, JSON_THROW_ON_ERROR),
                'email_status' => true,
                'sms_status' => false,
                'firebase_status' => $act !== 'password_reset',
                'firebase_body' => $push,
            ];
        }, $rows);
    }
}
