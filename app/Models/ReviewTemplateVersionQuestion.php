<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewTemplateVersionQuestion extends Model
{
    protected $table = 'review_template_version_questions';
    protected $fillable = ['review_template_version_id', 'review_question_id', 'step_order', 'step_name', 'question_order'];
    protected $casts = ['step_order' => 'integer', 'question_order' => 'integer'];

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewTemplateVersion::class, 'review_template_version_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ReviewQuestion::class, 'review_question_id');
    }
}
