<?php

namespace App\Enums;

enum ReviewQuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Rating = 'rating';
    case LongText = 'long_text';
    case Confirmation = 'confirmation';
}
