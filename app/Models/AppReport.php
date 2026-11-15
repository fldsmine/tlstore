<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppReport extends Model
{
    protected $fillable = [
        'reporter_user_id', 'app_id', 'app_review_campaign_id', 'reason_code', 'details',
        'status', 'admin_notes', 'reviewed_by_admin_id', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AppReviewCampaign::class, 'app_review_campaign_id');
    }

    public function reviewerAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
