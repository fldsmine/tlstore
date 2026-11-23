<?php

namespace App\Services\Mailing;

use App\Models\Package;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MailingRecipientAudience
{
    /**
     * Normalize only the supported server-side filters. Values stored with a campaign are
     * normalized again when the audience builder runs; browser-supplied counts are never trusted.
     *
     * @return array<string, mixed>
     */
    public function normalize(array $filters): array
    {
        $invalid = false;
        $status = $this->enumFilter($filters['status'] ?? 'all', ['all', 'active', 'suspended'], $invalid);
        $role = $this->enumFilter($filters['role'] ?? 'all', ['all', 'member', 'agent', 'special'], $invalid);
        $activation = $this->enumFilter($filters['activation'] ?? 'all', ['all', 'activated', 'not_activated'], $invalid);
        $emailStatus = $this->enumFilter($filters['email_status'] ?? 'all', ['all', 'verified', 'unverified'], $invalid);
        $share = $this->enumFilter($filters['share'] ?? 'all', ['all', 'active', 'inactive'], $invalid);
        $website = $this->enumFilter($filters['website'] ?? 'all', ['all', 'active', 'inactive'], $invalid);

        $rawPackage = $filters['package'] ?? 'all';
        $package = is_string($rawPackage) ? $rawPackage : '__invalid__';
        if (! is_string($rawPackage)) {
            $invalid = true;
        }
        if (! in_array($package, ['all', 'active', 'inactive', 'none'], true)
            && (! Str::isUuid($package) || ! Package::query()->whereKey($package)->exists())) {
            $invalid = true;
        }

        $rawFrom = $filters['registered_from'] ?? null;
        $rawTo = $filters['registered_to'] ?? null;
        $from = $this->validDate($rawFrom);
        $to = $this->validDate($rawTo);
        if (($rawFrom !== null && $rawFrom !== '' && $from === null)
            || ($rawTo !== null && $rawTo !== '' && $to === null)
            || ($from !== null && $to !== null && $from > $to)) {
            $invalid = true;
        }

        return [
            'status' => $status,
            'role' => $role,
            'activation' => $activation,
            'email_status' => $emailStatus,
            'package' => $package,
            'share' => $share,
            'website' => $website,
            'registered_from' => $from,
            'registered_to' => $to,
            '_invalid' => $invalid,
        ];
    }

    /** @return array<string, mixed> */
    public function campaignFilters(array $filters, string $mode, array $selectedIds = []): array
    {
        $mode = in_array($mode, ['filters', 'selected'], true) ? $mode : 'invalid';
        $selectedIds = $mode === 'selected' ? $this->normalizeSelectedIds($selectedIds) : [];

        return array_merge($this->normalize($filters), [
            'audience_mode' => $mode,
            'selected_user_ids' => $selectedIds,
        ]);
    }

    /** @return list<string> */
    public function normalizeSelectedIds(array $ids): array
    {
        $limit = max(1, (int) config('mailing.max_selected_recipients', 10000));
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id): string => is_string($id) ? trim($id) : '',
            $ids,
        ), static fn (string $id): bool => Str::isUuid($id))));

        return array_slice($ids, 0, $limit);
    }

    public function campaignQuery(array $storedFilters): Builder
    {
        $storedMode = $storedFilters['audience_mode'] ?? 'filters';
        $mode = in_array($storedMode, ['filters', 'selected'], true) ? $storedMode : 'invalid';
        $ids = $mode === 'selected'
            ? $this->normalizeSelectedIds(is_array($storedFilters['selected_user_ids'] ?? null) ? $storedFilters['selected_user_ids'] : [])
            : [];

        return $this->query($storedFilters, $mode, $ids);
    }

    /** Build the eligible-user query from persisted filters, not client-supplied recipient totals. */
    public function query(array $filters, string $mode = 'filters', array $selectedIds = []): Builder
    {
        $filters = $this->normalize($filters);
        $query = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            // Keep legitimate accounts without a username; omit only the two known system users.
            ->where(fn (Builder $users): Builder => $users
                ->whereNull('username')
                ->orWhereNotIn('username', User::SYSTEM_USERNAMES));

        if (($filters['_invalid'] ?? false) || ! in_array($mode, ['filters', 'selected'], true)) {
            return $query->whereRaw('1 = 0');
        }

        if ($mode === 'selected') {
            $ids = $this->normalizeSelectedIds($selectedIds);
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('users.id', $ids);
            }
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        match ($filters['role']) {
            'member' => $query->where('is_agent', false),
            'agent' => $query->where('is_agent', true)->where('is_special', false),
            'special' => $query->where('is_agent', true)->where('is_special', true),
            default => null,
        };

        if ($filters['activation'] === 'activated') {
            $query->whereNotNull('package_id');
        } elseif ($filters['activation'] === 'not_activated') {
            $query->whereNull('package_id');
        }

        if ($filters['email_status'] === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($filters['email_status'] === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        if ($filters['package'] === 'active') {
            $query->whereHas('package', fn (Builder $packages): Builder => $packages->where('is_active', true));
        } elseif ($filters['package'] === 'inactive') {
            $query->whereNotNull('package_id')
                ->whereDoesntHave('package', fn (Builder $packages): Builder => $packages->where('is_active', true));
        } elseif ($filters['package'] === 'none') {
            $query->whereNull('package_id');
        } elseif ($filters['package'] !== 'all') {
            $query->where('package_id', $filters['package']);
        }

        if ($filters['share'] === 'active') {
            $query->whereHas('shareSubscriptions', fn (Builder $subscriptions): Builder => $subscriptions->current());
        } elseif ($filters['share'] === 'inactive') {
            $query->whereDoesntHave('shareSubscriptions', fn (Builder $subscriptions): Builder => $subscriptions->current());
        }

        if ($filters['website'] === 'active') {
            $query->whereNotNull('website_expires_at')->where('website_expires_at', '>', now());
        } elseif ($filters['website'] === 'inactive') {
            $query->where(fn (Builder $users): Builder => $users
                ->whereNull('website_expires_at')
                ->orWhere('website_expires_at', '<=', now()));
        }

        if ($filters['registered_from'] !== null) {
            $query->whereDate('created_at', '>=', $filters['registered_from']);
        }
        if ($filters['registered_to'] !== null) {
            $query->whereDate('created_at', '<=', $filters['registered_to']);
        }

        return $query;
    }

    public function count(array $filters, string $mode = 'filters', array $selectedIds = []): int
    {
        return $this->query($filters, $mode, $selectedIds)->count();
    }

    public function countDeliverable(array $storedFilters): int
    {
        $count = 0;
        $cap = max(1, (int) config('mailing.max_campaign_recipients', 250000)) + 1;
        $this->campaignQuery($storedFilters)
            ->select(['users.id', 'users.email'])
            ->orderBy('users.id')
            ->chunkById(1000, function ($users) use (&$count, $cap): bool {
                foreach ($users as $user) {
                    if ($this->hasDeliverableAddress($user->email)) {
                        $count++;
                        if ($count >= $cap) {
                            return false;
                        }
                    }
                }

                return true;
            }, 'users.id', 'id');

        return $count;
    }

    public function hasDeliverableAddress(?string $email): bool
    {
        return is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function enumFilter(mixed $value, array $allowed, bool &$invalid): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            $invalid = true;

            return '__invalid__';
        }

        return $value;
    }

    private function validDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        $date = trim($date);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        return $parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === $date ? $date : null;
    }
}
