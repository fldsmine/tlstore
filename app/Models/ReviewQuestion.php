<?php

namespace App\Models;

use App\Enums\ReviewQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewQuestion extends Model
{
    protected $table = 'review_questions';
    protected $fillable = [
        'question_key', 'step_name', 'step_order', 'question_order', 'prompt', 'hint',
        'type', 'options', 'settings', 'is_attention_check', 'attention_check_answer',
    ];
    protected $casts = [
        'step_order' => 'integer',
        'question_order' => 'integer',
        'type' => ReviewQuestionType::class,
        'options' => 'array',
        'settings' => 'array',
        'is_attention_check' => 'boolean',
        'attention_check_answer' => 'json',
    ];

    public function templateVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            ReviewTemplateVersion::class,
            'review_template_version_questions',
            'review_question_id',
            'review_template_version_id'
        )->withPivot(['step_order', 'step_name', 'question_order'])->withTimestamps();
    }

    public function templateVersionLinks(): HasMany
    {
        return $this->hasMany(ReviewTemplateVersionQuestion::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ReviewAnswer::class);
    }
}
