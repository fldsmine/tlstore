<?php

namespace App\Services;

use App\Models\SocialHandle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SocialFollowRequirement
{
    /** Active required pages this user has not confirmed following. */
    public function pendingHandlesFor(User $user): Builder
    {
        return SocialHandle::query()
            ->active()
            ->whereDoesntHave('userFollows', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->whereNull('unfollowed_at'));
    }

    public function hasPending(User $user): bool
    {
        return $this->pendingHandlesFor($user)->exists();
    }
}
