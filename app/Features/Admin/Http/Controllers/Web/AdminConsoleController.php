<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Application\Actions\Campaigns\DecideCampaignOrder;
use App\Application\Actions\Moderation\DecideAdShare;
use App\Application\Actions\Moderation\DecideManualDeposit;
use App\Application\Actions\Moderation\DecideReviewSubmission;
use App\Application\Actions\Moderation\SettleWithdrawal;
use App\Models\AdShareEvent;
use App\Models\Admin;
use App\Models\AppReport;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\ManualDepositSubmission;
use App\Models\ReviewSubmission;
use App\Models\ReviewTemplate;
use App\Models\ReviewTemplateVersion;
use App\Models\ReviewTemplateVersionQuestion;
use App\Models\User;
use App\Models\UserCampaignOrder;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class AdminConsoleController
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match an active administrator account.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $admin = Auth::guard('admin')->user();
        if (! $admin || $admin->status !== 'active') {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'This administrator account is inactive.']);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function templateVersions(int $template): View
    {
        $this->authorizeModule('review-templates');
        $questionnaire = ReviewTemplate::query()->with(['versions.questions'])->findOrFail($template);

        return view('admin.review_templates.versions', [
            'title' => 'Questionnaire versions',
            'questionnaire' => $questionnaire,
            'questions' => \App\Models\ReviewQuestion::query()->orderBy('step_order')->orderBy('question_order')->get(),
        ]);
    }

    public function createTemplateVersion(Request $request, int $template): RedirectResponse
    {
        $this->authorizeModule('review-templates');
        $questionnaire = ReviewTemplate::query()->findOrFail($template);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1', Rule::unique('review_template_versions', 'version')->where('review_template_id', $template)],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'integer', 'distinct', 'exists:review_questions,id'],
        ]);

        DB::transaction(function () use ($questionnaire, $data): void {
            $version = $questionnaire->versions()->create(['version' => $data['version'], 'status' => 'draft']);
            $questions = \App\Models\ReviewQuestion::query()->whereIn('id', $data['question_ids'])
                ->orderBy('step_order')->orderBy('question_order')->get();
            foreach ($questions as $question) {
                $version->questionLinks()->create([
                    'review_question_id' => $question->id,
                    'step_order' => $question->step_order,
                    'step_name' => $question->step_name,
                    'question_order' => $question->question_order,
                ]);
            }
            $this->audit('review_template_version.created', $version, null, [
                'template_id' => $questionnaire->id,
                'version' => $version->version,
                'question_ids' => $questions->modelKeys(),
            ]);
        });

        return back()->with('success', 'Draft questionnaire version created with the selected questions.');
    }

    public function publishTemplateVersion(int $template, int $version): RedirectResponse
    {
        $this->authorizeModule('review-templates');
        $questionnaire = ReviewTemplate::query()->findOrFail($template);
        $versionRecord = $questionnaire->versions()->withCount('questions')->findOrFail($version);
        abort_if($versionRecord->status === 'published', 409, 'Published questionnaire versions are immutable. Create a new version to change questions.');
        abort_if($versionRecord->questions_count < 1, 422, 'Add questions before publishing this version.');

        DB::transaction(function () use ($questionnaire, $versionRecord): void {
            $versionRecord->update(['status' => 'published', 'published_at' => now()]);
            $questionnaire->update(['status' => 'published']);
            $this->audit('review_template_version.published', $versionRecord, ['status' => 'draft'], [
                'status' => 'published', 'template_id' => $questionnaire->id,
            ]);
        });

        return back()->with('success', 'Questionnaire version published.');
    }

    public function moderationProof(string $module, string $record)
    {
        $this->authorizeModule($module);
        $path = match ($module) {
            'deposit-moderation' => ManualDepositSubmission::query()->findOrFail($record)->proof_storage_path,
            'share-moderation' => AdShareEvent::query()->findOrFail($record)->evidence['screenshot_path'] ?? null,
            default => abort(404),
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    public function dashboard(): View
    {
        $metrics = [
            ['label' => 'Users', 'value' => User::query()->count(), 'icon' => 'people'],
            ['label' => 'Active subscriptions', 'value' => DB::table('user_subscriptions')->where('status', 'active')->count(), 'icon' => 'subscriptions'],
            ['label' => 'Review approvals', 'value' => ReviewSubmission::query()->where('status', 'pending_review')->count(), 'icon' => 'reviews'],
            ['label' => 'Ad shares', 'value' => AdShareEvent::query()->where('status', 'pending_validation')->count(), 'icon' => 'campaign'],
            ['label' => 'Manual deposits', 'value' => ManualDepositSubmission::query()->where('status', 'submitted')->count(), 'icon' => 'deposits'],
            ['label' => 'Withdrawals', 'value' => WithdrawalRequest::query()->where('status', 'pending')->count(), 'icon' => 'withdrawals'],
            ['label' => 'User campaign orders', 'value' => UserCampaignOrder::query()->where('status', 'pending')->count(), 'icon' => 'orders'],
            ['label' => 'Open app reports', 'value' => AppReport::query()->whereIn('status', ['pending', 'open', 'new'])->count(), 'icon' => 'reports'],
        ];
        $recentAudit = AuditLog::query()->with('adminActor:id,full_name,email')->latest()->limit(12)->get();

        return view('admin.dashboard', [
            'title' => 'Overview',
            'admin' => Auth::guard('admin')->user(),
            'metrics' => $metrics,
            'recentAudit' => $recentAudit,
            'pendingModules' => [
                'review-moderation', 'share-moderation', 'deposit-moderation',
                'withdrawal-moderation', 'campaign-orders', 'app-reports',
            ],
        ]);
    }

    public function index(Request $request, string $module): View
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        $modelClass = $definition['model'];
        /** @var Model $prototype */
        $prototype = new $modelClass;
        $query = $modelClass::query();
        if (! empty($definition['with'])) {
            $query->with($definition['with']);
        }
        if (! empty($definition['with_count'])) {
            $query->withCount($definition['with_count']);
        }
        if ($prototype instanceof User) {
            $query->withTrashed();
            if (! $request->boolean('include_deleted')) {
                $query->whereNull('deleted_at');
            }
        }

        $searchable = $definition['search'] ?? [];
        if ($request->filled('q') && $searchable !== []) {
            $needle = '%'.trim((string) $request->query('q')).'%';
            $query->where(function ($builder) use ($searchable, $needle): void {
                foreach ($searchable as $index => $column) {
                    $index === 0
                        ? $builder->where($column, 'like', $needle)
                        : $builder->orWhere($column, 'like', $needle);
                }
            });
        }
        if ($request->filled('status') && in_array('status', $definition['columns'], true)) {
            $query->where('status', $request->query('status'));
        }
        $records = $query->orderByDesc($prototype->getKeyName())->paginate(30)->withQueryString();

        return view('admin.modules.index', [
            'title' => $definition['label'],
            'module' => $module,
            'definition' => $definition,
            'records' => $records,
        ]);
    }

    public function create(string $module): View
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        abort_unless($definition['can_create'], 403);
        abort_if($module === 'users' && ! in_array(Auth::guard('admin')->user()?->role, ['super_admin', 'admin'], true), 403);
        abort_if($definition['fields'] === [], 403);

        return view('admin.modules.form', [
            'title' => 'Create '.$definition['label'],
            'module' => $module,
            'definition' => $definition,
            'record' => null,
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        abort_unless($definition['can_create'], 403);
        abort_if($definition['fields'] === [], 403);

        $values = $this->validatedValues($request, $definition);
        $modelClass = $definition['model'];
        if ($module === 'template-question-links') {
            abort_unless(ReviewTemplateVersion::query()->whereKey($values['review_template_version_id'] ?? null)->where('status', 'draft')->exists(), 422, 'Questions can only be added to a draft questionnaire version.');
        }
        $record = DB::transaction(function () use ($modelClass, $values, $module, $request): Model {
            $record = new $modelClass;
            $record->fill($values)->save();
            $this->saveAppScreenshots($module, $record, $request);
            $this->audit('admin.record_created', $record, null, $record->only(array_keys($values)));

            return $record;
        });

        return redirect()->route('admin.modules.index', $module)->with('success', $definition['label'].' record created.');
    }

    public function edit(string $module, string $record): View
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        abort_unless($definition['can_create'], 403);
        abort_if($module === 'users' && ! in_array(Auth::guard('admin')->user()?->role, ['super_admin', 'admin'], true), 403);
        abort_if($definition['fields'] === [], 403);
        $recordModel = $definition['model']::query()->findOrFail($record);
        if ($recordModel instanceof User) {
            $recordModel->loadMissing('country');
        }
        if ($module === 'apps') {
            $recordModel->loadMissing('screenshots');
        }

        return view('admin.modules.form', [
            'title' => 'Edit '.$definition['label'],
            'module' => $module,
            'definition' => $definition,
            'record' => $recordModel,
        ]);
    }

    public function update(Request $request, string $module, string $record): RedirectResponse
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        abort_if($module === 'users' && ! in_array(Auth::guard('admin')->user()?->role, ['super_admin', 'admin'], true), 403);
        abort_if($definition['fields'] === [], 403);
        $modelClass = $definition['model'];
        $recordModel = $modelClass::query()->findOrFail($record);
        if ($recordModel instanceof ReviewQuestion && $recordModel->templateVersionLinks()->whereHas('templateVersion', fn ($query) => $query->where('status', 'published'))->exists()) {
            abort(409, 'Questions assigned to a published questionnaire are immutable. Create a new question for the next version.');
        }
        if ($recordModel instanceof ReviewTemplateVersionQuestion && $recordModel->templateVersion()->where('status', 'published')->exists()) {
            abort(409, 'Published questionnaire assignments are immutable. Create a new version.');
        }
        $values = $this->validatedValues($request, $definition, $recordModel);

        DB::transaction(function () use ($request, $module, $recordModel, $values): void {
            $before = $recordModel->only(array_keys($values));
            $recordModel->fill($values)->save();
            if ($recordModel instanceof Admin && array_key_exists('password', $values)) {
                $recordModel->tokens()->delete();
            }
            $this->saveAppScreenshots($module, $recordModel, $request);
            $this->audit('admin.record_updated', $recordModel, $before, $recordModel->only(array_keys($values)));
        });

        return redirect()->route('admin.modules.index', $module)->with('success', $definition['label'].' record updated.');
    }

    public function destroy(Request $request, string $module, string $record): RedirectResponse
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        abort_unless($definition['deletable'], 403);
        $modelClass = $definition['model'];
        $instance = $modelClass::query()->findOrFail($record);
        if ($instance instanceof ReviewQuestion && $instance->templateVersionLinks()->exists()) {
            abort(409, 'A question assigned to a questionnaire version cannot be deleted.');
        }
        if ($instance instanceof ReviewTemplateVersionQuestion && $instance->templateVersion()->where('status', 'published')->exists()) {
            abort(409, 'Published questionnaire assignments are immutable.');
        }
        $reason = trim((string) $request->input('reason', 'Removed in admin console'));
        DB::transaction(function () use ($instance, $reason): void {
            $before = $instance->getAttributes();
            $instance->delete();
            $this->audit('admin.record_deleted', $instance, $before, ['reason' => $reason]);
        });

        return back()->with('success', $definition['label'].' record removed.');
    }

    public function moduleAction(Request $request, string $module, string $record, string $action): RedirectResponse
    {
        $definition = $this->module($module);
        $this->authorizeModule($module);
        $data = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected', 'paid'])],
            'reason_code' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
        ]);
        $admin = Auth::guard('admin')->user();

        try {
            match ($module) {
                'review-moderation' => app(DecideReviewSubmission::class)->handle(
                    $admin,
                    ReviewSubmission::query()->findOrFail($record),
                    $data['decision'],
                    $data['reason_code'] ?? null,
                    $data['reason'] ?? null,
                ),
                'share-moderation' => app(DecideAdShare::class)->handle(
                    $admin,
                    AdShareEvent::query()->findOrFail($record),
                    $data['decision'],
                    $data['reason_code'] ?? null,
                    $data['reason'] ?? null,
                ),
                'deposit-moderation' => app(DecideManualDeposit::class)->handle(
                    $admin,
                    ManualDepositSubmission::query()->findOrFail($record),
                    $data['decision'],
                    $data['reason'] ?? null,
                ),
                'withdrawal-moderation' => app(SettleWithdrawal::class)->handle(
                    $admin,
                    WithdrawalRequest::query()->findOrFail($record),
                    $data['decision'],
                    $data['provider_reference'] ?? null,
                    $data['reason'] ?? null,
                ),
                'campaign-orders' => app(DecideCampaignOrder::class)->handle(
                    $admin,
                    UserCampaignOrder::query()->findOrFail($record),
                    $data['decision'],
                    $data['reason'] ?? null,
                ),
                'app-reports' => $this->decideAppReport($admin, $record, $data),
                default => abort(404),
            };
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['decision' => $exception instanceof ValidationException
                ? $exception->getMessage()
                : 'The moderation action could not be completed.']);
        }

        return back()->with('success', 'The moderation decision was recorded.');
    }

    public function userDetail(int $user): View
    {
        $this->authorizeModule('users');
        $account = User::withTrashed()->with(['country', 'wallets', 'payoutProfile.option', 'socialAccounts'])
            ->findOrFail($user);

        return view('admin.users.show', [
            'title' => 'User account',
            'account' => $account,
            'subscriptions' => $account->subscriptions()->with('plan.group')->latest()->limit(20)->get(),
            'transactions' => $account->transactions()->latest()->limit(25)->get(),
            'withdrawals' => $account->withdrawalRequests()->with('option')->latest()->limit(15)->get(),
            'referrals' => $account->sentReferrals()->with('referredUser:id,username,email,status')->latest()->limit(20)->get(),
            'tasks' => $account->reviewTasks()->with('campaign.app')->latest()->limit(10)->get(),
        ]);
    }

    public function userAction(Request $request, int $user, string $action): RedirectResponse
    {
        $this->authorizeModule('users');
        $account = User::withTrashed()->findOrFail($user);
        $admin = Auth::guard('admin')->user();

        if ($action === 'wallet-adjustment') {
            abort_unless(in_array($admin->role, ['super_admin', 'admin', 'finance'], true), 403);
            $data = $request->validate([
                'wallet_type' => ['required', Rule::in(['review', 'review_group_b', 'review_group_c', 'share', 'referral', 'topup_fund', 'bonus'])],
                'direction' => ['required', Rule::in(['credit', 'debit'])],
                'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
                'reason' => ['required', 'string', 'min:4', 'max:500'],
            ]);
            if ($account->trashed()) {
                throw ValidationException::withMessages(['user' => 'A deleted account cannot be adjusted.']);
            }
            $currency = strtoupper((string) ($account->wallet_currency ?: $account->preferred_currency ?: 'USD'));
            DB::transaction(function () use ($account, $admin, $data, $currency): void {
                $wallet = Wallet::query()->firstOrCreate([
                    'user_id' => $account->id,
                    'wallet_type' => $data['wallet_type'],
                    'currency' => $currency,
                ], ['status' => 'active']);
                $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
                $amount = (int) $data['amount_minor'];
                if ($data['direction'] === 'debit' && (int) $wallet->balance_minor < $amount) {
                    throw ValidationException::withMessages(['amount_minor' => 'This wallet does not have enough available funds.']);
                }
                $entry = LedgerEntry::query()->create([
                    'wallet_id' => $wallet->id,
                    'entry_type' => 'admin_adjustment',
                    'direction' => $data['direction'],
                    'amount_minor' => $amount,
                    'status' => 'posted',
                    'source_type' => 'user',
                    'source_id' => $account->id,
                    'idempotency_key' => 'admin-wallet:'.hash('sha256', $admin->id.'|'.$account->id.'|'.Str::uuid()),
                    'description' => $data['reason'],
                    'metadata' => ['admin_id' => $admin->id, 'reason' => $data['reason']],
                    'available_at' => now(),
                    'posted_at' => now(),
                ]);
                $this->audit('user.wallet_adjusted', $account, null, [
                    'wallet_id' => $wallet->id,
                    'wallet_type' => $data['wallet_type'],
                    'direction' => $data['direction'],
                    'amount_minor' => $amount,
                    'currency' => $currency,
                    'ledger_entry_id' => $entry->id,
                    'reason' => $data['reason'],
                ]);
            });

            return back()->with('success', "Wallet updated in the user’s {$currency} currency.");
        }

        if ($action === 'ban' || $action === 'unban') {
            abort_unless(in_array($admin->role, ['super_admin', 'admin', 'moderator'], true), 403);
            $data = $request->validate(['reason' => [$action === 'ban' ? 'required' : 'nullable', 'string', 'min:4', 'max:2000']]);
            $before = ['is_banned' => $account->is_banned, 'status' => $account->status?->value, 'ban_reason' => $account->ban_reason];
            $account->forceFill($action === 'ban'
                ? ['is_banned' => true, 'status' => 'banned', 'ban_reason' => $data['reason']]
                : ['is_banned' => false, 'status' => 'active', 'ban_reason' => null])->save();
            $account->tokens()->delete();
            $this->audit('user.'.$action, $account, $before, ['reason' => $data['reason'] ?? null]);

            return back()->with('success', $action === 'ban' ? 'User banned and API sessions revoked.' : 'User unbanned and API sessions revoked.');
        }

        if ($action === 'reset-password') {
            abort_unless(in_array($admin->role, ['super_admin', 'admin'], true), 403);
            abort_if($account->trashed(), 422);
            $temporaryPassword = Str::password(20);
            $account->forceFill(['password' => Hash::make($temporaryPassword)])->save();
            $account->tokens()->delete();
            $this->audit('user.password_reset_by_admin', $account, null, ['tokens_revoked' => true]);

            return back()->with('temporary_password', $temporaryPassword)
                ->with('success', 'Temporary password created. Copy it now; it is shown once.');
        }

        if ($action === 'delete' || $action === 'restore') {
            abort_unless(in_array($admin->role, ['super_admin', 'admin'], true), 403);
            if ($action === 'delete' && ! $account->trashed()) {
                $account->tokens()->delete();
                $account->delete();
                $this->audit('user.deleted_by_admin', $account, null, ['soft_deleted_at' => $account->deleted_at?->toISOString()]);
            } elseif ($action === 'restore' && $account->trashed()) {
                $account->restore();
                $account->forceFill(['status' => 'active', 'is_banned' => false, 'ban_reason' => null])->save();
                $this->audit('user.restored_by_admin', $account, null, ['status' => 'active']);
            }

            return redirect()->route('admin.users.show', $account->id)->with('success', 'User account updated.');
        }

        abort(404);
    }

    private function decideAppReport(Admin $admin, int $id, array $data): AppReport
    {
        $report = AppReport::query()->findOrFail($id);
        $before = $report->only(['status', 'admin_notes', 'reviewed_by_admin_id']);
        $report->fill([
            'status' => $data['decision'],
            'admin_notes' => $data['reason'] ?? null,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ])->save();
        $this->audit('app_report.'.$data['decision'], $report, $before, $report->only(['status', 'admin_notes', 'reviewed_by_admin_id']));

        return $report;
    }

    private function module(string $key): array
    {
        $module = config('admin.modules.'.$key);
        abort_unless(is_array($module), 404);

        return $module;
    }

    private function authorizeModule(string $module): void
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        $role = (string) ($admin?->role ?? '');
        if ($role === 'super_admin') {
            return;
        }
        if ($role === 'admin') {
            abort_unless(! in_array($module, ['admins', 'system-settings'], true), 403);

            return;
        }
        $allowed = match ($role) {
            'content_admin' => ['apps', 'review-campaigns', 'ads', 'subscription-groups', 'subscription-plans', 'package-addons', 'review-questions', 'review-templates', 'announcements', 'faqs', 'testimonials', 'legal-documents', 'countries', 'regions', 'region-countries', 'social-handles', 'app-versions'],
            'moderator' => ['review-moderation', 'share-moderation', 'social-follow-activity', 'app-reports', 'campaign-orders', 'users'],
            'finance' => ['deposit-moderation', 'withdrawal-moderation', 'payment-options', 'withdrawal-options', 'banks', 'users'],
            default => [],
        };
        abort_unless(in_array($module, $allowed, true), 403);
    }

    private function validatedValues(Request $request, array $definition, ?Model $record = null): array
    {
        $rules = [];
        $modelClass = $definition['model'];
        $table = $record?->getTable() ?? (new $modelClass)->getTable();
        foreach ($definition['fields'] as $field) {
            if (in_array($field['type'], ['images', 'file'], true)) {
                $rules[$field['name']] = $field['type'] === 'images'
                    ? ['sometimes', 'array', 'max:12']
                    : ['sometimes', 'nullable', 'image', 'max:10240'];
                if ($field['type'] === 'images') {
                    $rules[$field['name'].'.*'] = ['image', 'max:10240'];
                }
                continue;
            }
            if ($field['type'] === 'password') {
                $rules[$field['name']] = $record ? ['sometimes', 'nullable', 'string', 'min:14', 'max:255'] : ['required', 'string', 'min:14', 'max:255'];
                continue;
            }
            if ($field['name'] === 'review_template_version_id' && in_array($definition['model'], [\App\Models\AppReviewCampaign::class], true)) {
                $rules[$field['name']] = ['nullable', 'integer', Rule::exists('review_template_versions', 'id')->where('status', 'published')];
                continue;
            }
            $required = $field['required'] ? 'required' : 'nullable';
            $rules[$field['name']] = match ($field['type']) {
                'email' => [$required, 'email:rfc', 'max:191'],
                'url' => [$required, 'url', 'max:2048'],
                'integer' => [$required, 'integer'],
                'decimal' => [$required, 'numeric'],
                'boolean' => ['sometimes', 'boolean'],
                'date' => [$required, 'date'],
                'datetime' => [$required, 'nullable', 'date'],
                'json' => [$required, 'nullable', 'string'],
                'select' => [$required, 'nullable', Rule::in(array_keys($field['options']))],
                'textarea' => [$required, 'string'],
                default => [$required, 'string', 'max:10000'],
            };
            if ($field['type'] === 'email' && $field['name'] === 'email') {
                $unique = Rule::unique($table, 'email');
                if ($record) {
                    $unique->ignore($record->getKey());
                }
                $rules[$field['name']][] = $unique;
            }
        }

        $validated = $request->validate($rules);
        foreach ($definition['fields'] as $field) {
            $name = $field['name'];
            if ($field['type'] === 'boolean') {
                $validated[$name] = $request->boolean($name);
            } elseif ($field['type'] === 'password' && blank($validated[$name] ?? null)) {
                unset($validated[$name]);
            } elseif ($field['type'] === 'json' && array_key_exists($name, $validated)) {
                $value = trim((string) $validated[$name]);
                if ($value === '') {
                    $validated[$name] = null;
                    continue;
                }
                try {
                    $validated[$name] = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw ValidationException::withMessages([$name => 'Enter valid JSON.']);
                }
            } elseif ($field['type'] === 'file' && $request->hasFile($name)) {
                $validated[$name] = $request->file($name)->store('admin/'.$definition['label'], 'public');
            }
        }

        return collect($validated)->only(collect($definition['fields'])->pluck('name')->all())->all();
    }

    private function saveAppScreenshots(string $module, Model $record, Request $request): void
    {
        if ($module !== 'apps' || ! $request->hasFile('screenshots')) {
            return;
        }
        $position = (int) $record->screenshots()->max('position');
        foreach ($request->file('screenshots', []) as $screenshot) {
            $position++;
            $record->screenshots()->create([
                'position' => $position,
                'source_type' => 'uploaded',
                'storage_disk' => 'public',
                'storage_path' => $screenshot->store('admin/app-screenshots', 'public'),
                'alt_text' => $record->name.' store screenshot '.$position,
            ]);
        }
    }

    private function audit(string $action, Model $entity, ?array $before, ?array $after): void
    {
        $redact = static function (?array $values): ?array {
            if ($values === null) {
                return $values;
            }

            $walk = static function (mixed $value) use (&$walk): mixed {
                if (! is_array($value)) {
                    return $value;
                }
                foreach ($value as $key => $item) {
                    if (preg_match('/secret|token|password|private.?key|api.?key|credential/i', (string) $key)) {
                        $value[$key] = '[redacted]';
                    } else {
                        $value[$key] = $walk($item);
                    }
                }

                return $value;
            };

            return $walk($values);
        };

        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => (string) $entity->getKey(),
            'before_values' => $redact($before),
            'after_values' => $redact($after),
            'created_at' => now(),
        ]);
    }
}
