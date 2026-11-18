<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewQuestionSeeder extends Seeder
{
    /**
     * Question models live in database/data. Every model is seeded into the
     * shared review_questions bank, and any model that declares a "template"
     * block also seeds that template, its published version and its question
     * order, so a single run keeps the bank and its templates in step.
     */
    private const MODELS = [
        'review-question-model.json',
        'simple-app-review-question-model.json',
        'deep-dive-review-question-model.json',
        'local-reality-review-question-model.json',
    ];

    public function run(): void
    {
        foreach (self::MODELS as $file) {
            $model = $this->loadModel($file);

            DB::transaction(function () use ($model): void {
                $this->seedQuestions($model);
                $this->seedTemplate($model);
            });
        }
    }

    private function loadModel(string $file): array
    {
        $path = database_path('data/' . $file);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read review question model at {$path}");
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function seedQuestions(array $model): void
    {
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
                    'is_attention_check' => (bool) ($question['is_attention_check'] ?? false),
                    'attention_check_answer' => isset($question['attention_check_answer'])
                        ? json_encode($question['attention_check_answer'], JSON_THROW_ON_ERROR)
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
                'is_attention_check',
                'attention_check_answer',
                'updated_at',
            ]
        );
    }

    /**
     * Models without a "template" block are seeded elsewhere,
     * so they are skipped here.
     */
    private function seedTemplate(array $model): void
    {
        if (!isset($model['template']['template_key'])) {
            return;
        }

        $template = $model['template'];
        $version = (int) ($model['version'] ?? 1);
        $status = $template['status'] ?? 'published';
        $now = now();

        DB::table('review_templates')->upsert([[
            'template_key' => $template['template_key'],
            'name' => $template['name'],
            'description' => $template['description'] ?? null,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['template_key'], ['name', 'description', 'status', 'updated_at']);

        $templateId = (int) DB::table('review_templates')
            ->where('template_key', $template['template_key'])
            ->value('id');

        DB::table('review_template_versions')->upsert([[
            'review_template_id' => $templateId,
            'version' => $version,
            'status' => $status,
            'published_at' => $status === 'published' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['review_template_id', 'version'], ['status', 'published_at', 'updated_at']);

        $versionId = (int) DB::table('review_template_versions')
            ->where('review_template_id', $templateId)
            ->where('version', $version)
            ->value('id');

        $questionIds = DB::table('review_questions')->pluck('id', 'question_key')->all();
        $rows = [];

        foreach ($model['steps'] as $step) {
            foreach ($step['questions'] as $question) {
                if (!isset($questionIds[$question['key']])) {
                    continue;
                }

                $rows[] = [
                    'review_template_version_id' => $versionId,
                    'review_question_id' => $questionIds[$question['key']],
                    'step_order' => $step['order'],
                    'step_name' => $step['name'],
                    'question_order' => $question['order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $pivot = DB::table('review_template_version_questions')
            ->where('review_template_version_id', $versionId);
        if ($rows === []) {
            $pivot->delete();

            return;
        }

        DB::table('review_template_version_questions')->upsert(
            $rows,
            ['review_template_version_id', 'review_question_id'],
            ['step_order', 'step_name', 'question_order', 'updated_at']
        );

        $questionIdsForVersion = array_column($rows, 'review_question_id');
        DB::table('review_template_version_questions')
            ->where('review_template_version_id', $versionId)
            ->whereNotIn('review_question_id', $questionIdsForVersion)
            ->delete();
    }
}
