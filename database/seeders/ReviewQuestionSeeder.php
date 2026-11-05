<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/review-question-model.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read review question model at {$path}");
        }

        $model = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $now = now();
        $rows = [];

        foreach ($model['steps'] as $step) {
            foreach ($step['questions'] as $question) {
                $settings = array_intersect_key($question, array_flip([
                    'risk_option_indexes',
                    'exclusive_option_index',
                    'min_words',
                    'scale',
                    'confirmation_label',
                ]));

                $rows[] = [
                    'question_key' => $question['key'],
                    'step_name' => $step['name'],
                    'step_order' => $step['order'],
                    'question_order' => $question['order'],
                    'prompt' => $question['prompt'],
                    'hint' => $question['hint'],
                    'type' => $question['type'],
                    'options' => isset($question['options'])
                        ? json_encode($question['options'], JSON_THROW_ON_ERROR)
                        : null,
                    'settings' => $settings !== []
                        ? json_encode($settings, JSON_THROW_ON_ERROR)
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('review_questions')->upsert(
            $rows,
            ['question_key'],
            [
                'step_name',
                'step_order',
                'question_order',
                'prompt',
                'hint',
                'type',
                'options',
                'settings',
                'updated_at',
            ]
        );
    }
}
