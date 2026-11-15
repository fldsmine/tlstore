<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ReviewQuestion;
use App\Models\ReviewTemplate;
use App\Models\ReviewTemplateVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QuestionnaireController
{
    public function questions(): JsonResponse
    {
        return response()->json(['data' => ReviewQuestion::query()->with('templateVersionLinks.templateVersion')
            ->orderBy('step_order')->orderBy('question_order')->paginate(100)]);
    }

    public function createQuestion(Request $request): JsonResponse
    {
        $data = $this->validatedQuestion($request, true);
        $question = ReviewQuestion::query()->create($data);
        $this->audit($request->user(), 'review_question.created', 'review_question', (string) $question->id, null, $data);

        return response()->json(['data' => $question], 201);
    }

    public function updateQuestion(Request $request, ReviewQuestion $question): JsonResponse
    {
        abort_if($question->templateVersionLinks()->whereHas('templateVersion', fn ($query) => $query->where('status', 'published'))->exists(), 409,
            'Questions used by a published questionnaire are immutable. Create a new question for the next version.');
        $data = $this->validatedQuestion($request, false);
        $before = $question->only(array_keys($data));
        $question->fill($data)->save();
        $this->audit($request->user(), 'review_question.updated', 'review_question', (string) $question->id, $before, $data);

        return response()->json(['data' => $question->refresh()]);
    }

    public function templates(): JsonResponse
    {
        return response()->json(['data' => ReviewTemplate::query()->with(['versions.questions'])
            ->orderBy('name')->paginate(50)]);
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'template_key' => ['nullable', 'string', 'max:64', 'alpha_dash', 'unique:review_templates,template_key'],
        ]);
        $template = ReviewTemplate::query()->create([
            'template_key' => $data['template_key'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'status' => 'draft',
        ]);
        $this->audit($request->user(), 'review_template.created', 'review_template', (string) $template->id, null, $data);

        return response()->json(['data' => $template], 201);
    }

    public function createVersion(Request $request, ReviewTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1', 'unique:review_template_versions,version,NULL,id,review_template_id,'.$template->id],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_id' => ['required', 'integer', 'exists:review_questions,id', 'distinct'],
            'questions.*.step_order' => ['required', 'integer', 'min:1'],
            'questions.*.step_name' => ['required', 'string', 'max:120'],
            'questions.*.question_order' => ['required', 'integer', 'min:1'],
        ]);

        $version = DB::transaction(function () use ($request, $template, $data): ReviewTemplateVersion {
            $version = $template->versions()->create([
                'version' => $data['version'], 'status' => 'draft', 'published_at' => null,
            ]);
            foreach ($data['questions'] as $question) {
                $version->questionLinks()->create([
                    'review_question_id' => $question['question_id'],
                    'step_order' => $question['step_order'], 'step_name' => $question['step_name'],
                    'question_order' => $question['question_order'],
                ]);
            }
            $this->audit($request->user(), 'review_template_version.created', 'review_template_version', (string) $version->id, null, [
                'template_id' => $template->id, 'version' => $version->version,
                'question_ids' => array_column($data['questions'], 'question_id'),
            ]);
            return $version;
        });

        return response()->json(['data' => $version->load('questions')], 201);
    }

    public function publishVersion(Request $request, ReviewTemplate $template, ReviewTemplateVersion $version): JsonResponse
    {
        abort_unless($version->review_template_id === $template->id, 404);
        abort_if($version->status === 'published', 409, 'Published questionnaire versions are immutable. Create a new version to change the question set.');
        abort_if($version->questions()->count() === 0, 422, 'A questionnaire version must contain at least one question.');

        DB::transaction(function () use ($request, $template, $version): void {
            $version->update(['status' => 'published', 'published_at' => now()]);
            $template->update(['status' => 'published']);
            $this->audit($request->user(), 'review_template_version.published', 'review_template_version', (string) $version->id, null, [
                'template_id' => $template->id, 'version' => $version->version,
            ]);
        });

        return response()->json(['data' => $version->refresh()->load('questions')]);
    }

    private function validatedQuestion(Request $request, bool $creating): array
    {
        $rules = [
            'step_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'step_order' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1'],
            'question_order' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1'],
            'prompt' => [$creating ? 'required' : 'sometimes', 'string', 'max:5000'],
            'hint' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'type' => [$creating ? 'required' : 'sometimes', 'in:single_choice,multiple_choice,rating,long_text,confirmation'],
            'options' => ['sometimes', 'nullable', 'array'],
            'options.*' => ['required', 'string', 'max:500'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
        if ($creating) {
            $rules['question_key'] = ['required', 'string', 'max:64', 'alpha_dash', 'unique:review_questions,question_key'];
        }

        $data = $request->validate($rules);
        $type = $data['type'] ?? $request->route('question')?->type?->value;
        if (in_array($type, ['single_choice', 'multiple_choice'], true)
            && count($data['options'] ?? $request->route('question')?->options ?? []) < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'Choice questions require at least one option.']);
        }
        if ($type === 'rating') {
            $scale = $data['settings']['scale'] ?? $request->route('question')?->settings['scale'] ?? null;
            if (! is_array($scale) || ! isset($scale['min'], $scale['max']) || ! is_numeric($scale['min'])
                || ! is_numeric($scale['max']) || (float) $scale['max'] <= (float) $scale['min']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['settings.scale' => 'Rating questions require a scale with numeric min and max values.']);
            }
        }

        return $data;
    }

    private function audit(Admin $admin, string $action, string $type, string $id, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => $type,
            'entity_id' => $id, 'before_values' => $before, 'after_values' => $after, 'created_at' => now(),
        ]);
    }
}
