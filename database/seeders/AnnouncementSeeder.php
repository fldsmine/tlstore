<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            [
                'title' => 'Scheduled maintenance this weekend',
                'body' => 'The platform will undergo brief maintenance on Sunday between 1am and 3am. Withdrawals may be delayed.',
                'action_url' => null, 'action_label' => null, 'is_dismissible' => true,
                'type' => 'info', 'display_type' => 1, 'is_active' => false,
                'starts_at' => null, 'ends_at' => null,
                'created_at' => '2026-10-01 02:24:14', 'updated_at' => '2026-10-01 02:24:14',
            ],
            [
                'title' => 'New referral bonus rates are live',
                'body' => 'We have increased direct and indirect Connect Bonuses. Invite more friends and earn more!',
                'action_url' => null, 'action_label' => null, 'is_dismissible' => true,
                'type' => 'success', 'display_type' => 0, 'is_active' => false,
                'starts_at' => '2026-09-29 02:24:14', 'ends_at' => '2026-10-06 02:24:14',
                'created_at' => '2026-10-01 02:24:14', 'updated_at' => '2026-10-01 02:24:14',
            ],
            [
                'title' => 'Upcoming Pay Later policy update',
                'body' => 'Starting next month, defaulting on a Pay Later credit will pause new minute purchases until repaid.',
                'action_url' => null, 'action_label' => null, 'is_dismissible' => true,
                'type' => 'warning', 'display_type' => 2, 'is_active' => false,
                'starts_at' => '2026-10-08 02:24:14', 'ends_at' => '2026-10-22 02:24:14',
                'created_at' => '2026-10-01 02:24:14', 'updated_at' => '2026-10-01 02:24:14',
            ],
            [
                'title' => 'Beware of phishing attempts',
                'body' => 'We will never ask for your transaction PIN. Report any suspicious messages to support immediately.',
                'action_url' => null, 'action_label' => null, 'is_dismissible' => false,
                'type' => 'danger', 'display_type' => 2, 'is_active' => false,
                'starts_at' => '2026-09-01 02:24:14', 'ends_at' => '2026-09-21 02:24:14',
                'created_at' => '2026-10-01 02:24:14', 'updated_at' => '2026-10-01 02:24:14',
            ],
            [
                'title' => 'Welcome to TrustLens',
                'body' => 'Explore app reviews and sponsored sharing tasks. Your rewards are collected in separate wallets and appear after approval where required.',
                'action_url' => null, 'action_label' => null, 'is_dismissible' => true,
                'type' => 'success', 'display_type' => 0, 'is_active' => true,
                'starts_at' => null, 'ends_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'title' => 'Keep your account secure',
                'body' => 'TrustLens support will never ask for your password or one-time login code. Keep these private.',
                'action_url' => null, 'action_label' => null, 'is_dismissible' => false,
                'type' => 'warning', 'display_type' => 2, 'is_active' => false,
                'starts_at' => null, 'ends_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('announcements')->updateOrInsert(
                ['title' => $row['title']],
                [...$row, 'updated_at' => $now],
            );
        }
    }
}
