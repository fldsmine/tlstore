<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAnswer extends Model
{
    protected $table = 'review_answers';
    protected $fillable = ['review_submission_id', 'review_question_id', 'answer_value'];
    protected $casts = ['answer_value' => 'array'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmission::class, 'review_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ReviewQuestion::class, 'review_question_id');
    }
}
