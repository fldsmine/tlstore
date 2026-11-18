<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewTemplateVersion extends Model
{
    protected $table = 'review_template_versions';
    protected $fillable = ['review_template_id', 'version', 'status', 'published_at'];
    protected $casts = ['version' => 'integer', 'published_at' => 'datetime'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReviewTemplate::class, 'review_template_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(
            ReviewQuestion::class,
            'review_template_version_questions',
            'review_template_version_id',
            'review_question_id'
        )->withPivot(['step_order', 'step_name', 'question_order'])
            ->withTimestamps()
            ->orderByPivot('step_order')
            ->orderByPivot('question_order');
    }

    public function questionLinks(): HasMany
    {
        return $this->hasMany(ReviewTemplateVersionQuestion::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ReviewTask::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ReviewSubmission::class);
    }
}
