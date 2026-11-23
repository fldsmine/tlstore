import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:review/core/utils/helper.dart';
import 'package:review/widgets/proxy_prompt_dialog.dart';

import '../../../../core/currency/money.dart';
import '../../../../services/auth_api.dart';
import '../../../../widgets/auth_components.dart';
import '../../../../widgets/task_completion_page.dart';
import '../../../subscriptions/presentation/widgets/package_addon_purchase_sheet.dart';

class ReviewTaskScreen extends StatefulWidget {
  const ReviewTaskScreen({
    super.key,
    required this.api,
    required this.token,
    required this.task,
    required this.user,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> task;
  final Map<String, dynamic> user;

  @override
  State<ReviewTaskScreen> createState() => _ReviewTaskScreenState();
}

class _ReviewTaskScreenState extends State<ReviewTaskScreen>
    with WidgetsBindingObserver {
  final _pageController = PageController();
  DateTime _startedAt = DateTime.now();
  final Map<int, dynamic> _answers = {};
  final Map<int, TextEditingController> _textControllers = {};

  int _page = 0;
  bool _sending = false;
  bool _restoringProgress = true;
  Timer? _saveDebounce;

  Object get _draftUserId =>
      widget.user['id'] ?? widget.user['user_id'] ?? 'unknown-user';

  Object get _draftTaskId => widget.task['id'] ?? 'unknown-task';

  bool get _backLocked =>
      _questions.isNotEmpty && _answeredCount >= (_questions.length / 2).ceil();

  List<Map<String, dynamic>> get _questions {
    final template = widget.task['template_version'];
    final values = template is Map ? template['questions'] : null;
    return values is List
        ? values
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList()
        : const [];
  }

  Map<String, dynamic> get _campaign {
    final value = widget.task['campaign'];
    return value is Map ? Map<String, dynamic>.from(value) : const {};
  }

  Map<String, dynamic> get _app {
    final value = _campaign['app'];
    return value is Map ? Map<String, dynamic>.from(value) : const {};
  }

  List<_ReviewSection> get _sections {
    final result = <_ReviewSection>[];
    for (final question in _questions) {
      final name = question['step_name']?.toString().trim();
      final sectionName = name == null || name.isEmpty ? 'Review' : name;
      final order = (question['step_order'] as num?)?.toInt() ?? 0;
      final index = result.indexWhere((e) => e.name == sectionName);
      if (index == -1) {
        result.add(_ReviewSection(sectionName, order, [question]));
      } else {
        result[index].questions.add(question);
      }
    }
    result.sort((a, b) => a.order.compareTo(b.order));
    return result;
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    for (final question in _questions) {
      if (question['type'] != 'rating') continue;
      final id = _questionId(question);
      final settings = question['settings'];
      final scale = settings is Map ? settings['scale'] : null;
      final minimum = scale is Map ? scale['min'] : null;
      if (id != null) _answers[id] = minimum is num ? minimum.round() : 1;
    }
    _restoreProgress();
  }

  Future<void> _restoreProgress() async {
    final saved = await widget.api.readReviewProgress(
      userId: _draftUserId,
      taskId: _draftTaskId,
    );
    if (!mounted) return;

    if (saved != null) {
      final templateVersion = saved['template_version_id']?.toString();
      final currentTemplateVersion =
          widget.task['template_version_id']?.toString() ??
          _asMap(widget.task['template_version'])['id']?.toString();
      final answers = saved['answers'];
      if ((templateVersion == null ||
              currentTemplateVersion == null ||
              templateVersion == currentTemplateVersion) &&
          answers is Map) {
        final restoredAnswers = <int, dynamic>{};
        answers.forEach((key, value) {
          final id = int.tryParse(key.toString());
          if (id != null) restoredAnswers[id] = value;
        });
        _answers
          ..clear()
          ..addAll(restoredAnswers);
        final savedPage = saved['page'] is num
            ? (saved['page'] as num).toInt()
            : 0;
        _page = savedPage.clamp(0, _questions.length).toInt();
        _startedAt =
            DateTime.tryParse(saved['started_at']?.toString() ?? '') ??
            DateTime.now();
      }
    }

    setState(() => _restoringProgress = false);
    if (_page > 0) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _pageController.hasClients) {
          _pageController.jumpToPage(_page);
        }
      });
    }
  }

  void _scheduleProgressSave() {
    if (_restoringProgress || _sending) return;
    _saveDebounce?.cancel();
    _saveDebounce = Timer(const Duration(milliseconds: 350), _saveProgress);
  }

  Future<void> _saveProgress() async {
    if (_restoringProgress || _sending) return;
    final answers = <String, dynamic>{};
    _answers.forEach((id, value) => answers['$id'] = value);
    await widget.api.saveReviewProgress(
      userId: _draftUserId,
      taskId: _draftTaskId,
      progress: {
        'page': _page,
        'answers': answers,
        'started_at': _startedAt.toIso8601String(),
        'template_version_id':
            widget.task['template_version_id'] ??
            _asMap(widget.task['template_version'])['id'],
      },
    );
  }

  Future<void> _clearProgress() async {
    _saveDebounce?.cancel();
    _saveDebounce = null;
    await widget.api.clearReviewProgress(
      userId: _draftUserId,
      taskId: _draftTaskId,
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached) {
      _saveDebounce?.cancel();
      unawaited(_saveProgress());
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _saveDebounce?.cancel();
    if (!_restoringProgress && !_sending) _saveProgress();
    _pageController.dispose();
    for (final controller in _textControllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  bool _isAnswered(Map<String, dynamic> question) {
    final id = _questionId(question);
    if (id == null || !_answers.containsKey(id)) return false;
    final type = question['type']?.toString();
    final value = _answers[id];

    if (type == 'multiple_choice') return value is List && value.isNotEmpty;
    if (type == 'long_text') {
      final settings = question['settings'];
      final minimum = settings is Map && settings['min_words'] is num
          ? (settings['min_words'] as num).toInt()
          : 1;
      if (value is! String || value.trim().isEmpty) return false;
      return value.trim().split(RegExp(r'\s+')).length >= minimum;
    }
    if (type == 'confirmation') return value == true;
    return true;
  }

  int _wordCount(dynamic value) {
    if (value is! String || value.trim().isEmpty) return 0;
    return value.trim().split(RegExp(r'\s+')).length;
  }

  int get _answeredCount => _questions.where(_isAnswered).length;

  _ReviewSection? _sectionFor(Map<String, dynamic> question) {
    final id = _questionId(question);
    for (final section in _sections) {
      if (section.questions.any((item) => _questionId(item) == id)) {
        return section;
      }
    }
    return null;
  }

  void _next() {
    if (_page < _questions.length && !_isAnswered(_questions[_page])) {
      showAuthMessage(context, 'Complete this answer before continuing.');
      return;
    }
    if (_page < _questions.length) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
      );
    }
  }

  void _back() {
    if (_page == 0 || _backLocked) return;
    _pageController.previousPage(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _submit() async {
    if (_questions.any((question) => !_isAnswered(question))) {
      showAuthMessage(context, 'Answer every question before submitting.');
      final firstUnanswered = _questions.indexWhere((q) => !_isAnswered(q));
      if (firstUnanswered >= 0) {
        _pageController.animateToPage(
          firstUnanswered,
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOutCubic,
        );
      }
      return;
    }

    final continueSubmission = await _checkAddonBeforeSubmit();
    if (!continueSubmission || !mounted) return;

    setState(() => _sending = true);
    try {
      final answers = _questions.map((question) {
        final id = _questionId(question)!;
        return {'question_id': question['id'], 'value': _answers[id]};
      }).toList();

      await widget.api.post(
        'review-tasks/${widget.task['id']}/submit',
        widget.token,
        {
          'answers': answers,
          'duration_seconds': math.max(
            1,
            math.min(7200, DateTime.now().difference(_startedAt).inSeconds),
          ),
        },
      );

      await _clearProgress();
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => TaskCompletionPage(
            api: widget.api,
            token: widget.token,
            user: widget.user,
            taskLabel: 'App review',
          ),
        ),
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  String _money(Object? minorValue, String currency, [Object? units]) {
    return moneyConverted(
      widget.api,
      minorValue,
      currency.trim().isEmpty ? 'USD' : currency,
      units,
      currency,
      isMinor: true,
    );
  }

  Future<bool> _checkAddonBeforeSubmit() async {
    try {
      final response = await widget.api.reviewAddonCheck(
        widget.token,
        widget.task['id'],
      );

      final data = _asMap(response['data']);

      if (data['required'] != true || data['active'] == true) {
        return true;
      }

      if (!mounted) return false;

      final offer = _asMap(data['addon']);

      final currency = offer['currency']?.toString() ?? 'USD';

      final listed =
          (data['listed_price_minor'] as num?)?.toInt() ??
          (offer['price_minor'] as num?)?.toInt() ??
          0;

      final payable = (data['payable_price_minor'] as num?)?.toInt() ?? listed;

      final offerAvailable = data['offer_available'] == true;

      final addonName = offer['name']?.toString() ?? 'Work permit';
      final planName = data['plan_name']?.toString() ?? 'review';

      final units =
          data['currency_minor_units'] ?? offer['currency_minor_units'];

      final price = _money(payable, currency, units);

      final featureList = proxyFeatures(data);
      final featured = featureList.take(6).toList(growable: false);

      final choice = await ProxyAddonsDialog.show<String>(
        context: context,
        plan: ProxyPromptPlan(
          storeName: 'TrustLens Store',
          storeWord: 'proxy',
          titleLead: 'Why do i need a\n',
          titleHighlight: 'Proxy',
          titleTail: '?',
          noticeLead: 'An ',
          noticeHighlight: 'active $addonName',
          noticeBody:
              ' is required to submit $planName reviews on TrustLens Store. Work done without a proxy ',
          noticeWarning: 'may be rejected.',
          price: price,
          billingPeriod: getDuration(data),
          requiredLabel: 'Required',
          purchaseLabel: 'ACTIVATE PROXY',
          trustLabel: 'Secure checkout',
          footerBrand: 'TrustLens Store',
          features: featured,
          reviewAction: true,
          reviewActionLabel: 'Submit review anyway',
        ),

        // Keep the prompt route under explicit control. The shared prompt's
        // default behavior popped first and then the old callback popped the
        // review route, making both actions look like they simply closed.
        closeOnPurchase: false,
        onPurchase: () async {
          Navigator.of(context, rootNavigator: true).pop();
          final purchased = await showModalBottomSheet<bool>(
            context: context,
            isScrollControlled: true,
            useSafeArea: true,
            backgroundColor: Theme.of(context).colorScheme.surface,
            builder: (_) => PackageAddonPurchaseSheet(
              api: widget.api,
              token: widget.token,
              addonState: data,
            ),
          );
          if (!mounted || purchased != true) return;
          // Re-run the server eligibility check, then submit if payment activated
          // the add-on. This keeps the review submission on its normal path.
          await _submit();
        },
        onReview: () =>
            Navigator.of(context, rootNavigator: true).pop('submit'),
      );

      if (choice == 'submit') return true;

      if (!mounted) return false;

      return choice == 'buy';
    } on AuthException catch (error) {
      if (mounted) {
        showAuthMessage(context, error.message);
      }
      return false;
    } catch (error, stackTrace) {
      debugPrint('Addon check error: $error');
      debugPrintStack(stackTrace: stackTrace);

      if (mounted) {
        showAuthMessage(
          context,
          'Could not check this review add-on. Please try again.',
        );
      }

      return false;
    }
  }

  TextEditingController _controllerFor(Map<String, dynamic> question) {
    final id = _questionId(question);
    if (id == null) return TextEditingController();
    return _textControllers.putIfAbsent(
      id,
      () => TextEditingController(
        text: _answers[id] is String ? _answers[id] as String : '',
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final total = _questions.length;

    if (_restoringProgress) {
      return Scaffold(
        appBar: AppBar(title: Text('App review')),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (total == 0) {
      return Scaffold(
        appBar: AppBar(title: const Text('App review')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.assignment_late_outlined,
                  size: 48,
                  color: scheme.onSurfaceVariant,
                ),
                const SizedBox(height: 14),
                Text(
                  'Questions are unavailable for this task.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                Text(
                  'Please go back and try opening the task again.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final isSummary = _page == total;
    final currentQuestion = isSummary ? null : _questions[_page];
    final section = currentQuestion == null
        ? null
        : _sectionFor(currentQuestion);
    final stepProgress = isSummary ? 1.0 : (_page + 1) / total;
    final appName = _app['name']?.toString() ?? 'App review';

    return Scaffold(
      appBar: AppBar(
        title: const Text('App review'),
        centerTitle: false,
        leading: _backLocked
            ? const SizedBox.shrink()
            : IconButton(
                onPressed: _page == 0
                    ? () => Navigator.maybePop(context)
                    : _back,
                icon: Icon(
                  _page == 0 ? Icons.close_rounded : Icons.arrow_back_rounded,
                ),
                tooltip: _page == 0 ? 'Close review' : 'Previous question',
              ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Center(
              child: _ModeChip(
                icon: Icons.verified_user_outlined,
                label: 'Reviewer mode',
              ),
            ),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (!isSummary)
              _AppOverviewCard(api: widget.api, app: _app, task: widget.task),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
              child: _ProgressHeader(
                current: isSummary ? total : _page + 1,
                total: total,
                answered: _answeredCount,
                progress: stepProgress,
                sectionName: isSummary
                    ? 'Review summary'
                    : section?.name ?? 'Review',
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: total + 1,
                onPageChanged: (value) {
                  setState(() => _page = value);
                  _scheduleProgressSave();
                },
                itemBuilder: (context, index) {
                  if (index == total) {
                    return _TrustSummaryPage(
                      appName: appName,
                      questions: _questions,
                      answers: _answers,
                    );
                  }
                  final question = _questions[index];
                  return _QuestionSlide(
                    number: index + 1,
                    total: total,
                    question: question,
                    isAttentionCheck: question['is_attention_check'] == true,
                    answer: _answers[_questionId(question)],
                    textController: _controllerFor(question),
                    onChanged: (value) {
                      setState(() {
                        final id = _questionId(question);
                        if (id != null) _answers[id] = value;
                      });
                      _scheduleProgressSave();
                    },
                  );
                },
              ),
            ),
            _FooterControls(
              isSummary: isSummary,
              isSending: _sending,
              hasPrevious: _page > 0 && !_backLocked,
              onBack: _back,
              onContinue: isSummary ? _submit : _next,
            ),
          ],
        ),
      ),
    );
  }
}

Map<String, dynamic> _asMap(Object? value) => value is Map
    ? value.map((key, value) => MapEntry(key.toString(), value))
    : <String, dynamic>{};

int? _questionId(Map<String, dynamic> question) =>
    (question['id'] as num?)?.toInt();

class _ReviewSection {
  _ReviewSection(this.name, this.order, this.questions);
  final String name;
  final int order;
  final List<Map<String, dynamic>> questions;
}

class _ModeChip extends StatelessWidget {
  const _ModeChip({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: scheme.secondaryContainer,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: scheme.onSecondaryContainer),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: scheme.onSecondaryContainer,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _AppOverviewCard extends StatelessWidget {
  const _AppOverviewCard({
    required this.api,
    required this.app,
    required this.task,
  });

  final AuthApi api;
  final Map<String, dynamic> app;
  final Map<String, dynamic> task;

  String _money(dynamic minor, dynamic currency) {
    if (minor is! num) return 'Reward available';
    final code = currency?.toString() ?? 'NGN';
    return moneyConverted(
      api,
      minor,
      code,
      task['reward_currency_minor_units'],
      api.preferredCurrency,
      isMinor: true,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final name = app['name']?.toString() ?? 'App review';
    final developer = app['developer']?.toString();
    final store = app['store']?.toString();
    final category = app['category']?.toString();
    final iconUrl = app['app_icon']?.toString();
    final campaign = task['campaign'];
    final rewardMinor = task['reserved_reward_minor'];
    final currency =
        task['reward_currency'] ??
        (campaign is Map ? campaign['currency'] : app['currency']);

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 2),
      child: Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          color: scheme.surfaceContainerLow,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: scheme.outlineVariant.withOpacity(.65)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(15),
              child: SizedBox(
                width: 58,
                height: 58,
                child: iconUrl != null && iconUrl.isNotEmpty
                    ? Image.network(
                        iconUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) =>
                            _AppIconFallback(name: name),
                      )
                    : _AppIconFallback(name: name),
              ),
            ),
            const SizedBox(width: 13),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (developer != null && developer.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      developer,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                  const SizedBox(height: 9),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      if (store != null && store.isNotEmpty)
                        _MetaTag(icon: Icons.storefront_outlined, label: store),
                      if (category != null && category.isNotEmpty)
                        _MetaTag(
                          icon: Icons.category_outlined,
                          label: category,
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Icon(Icons.payments_outlined, size: 17, color: scheme.primary),
                const SizedBox(height: 4),
                Text(
                  _money(rewardMinor, currency),
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: scheme.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  'Reward',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AppIconFallback extends StatelessWidget {
  const _AppIconFallback({required this.name});
  final String name;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final initial = name.trim().isEmpty ? 'A' : name.trim()[0].toUpperCase();
    return ColoredBox(
      color: scheme.primaryContainer,
      child: Center(
        child: Text(
          initial,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
            color: scheme.onPrimaryContainer,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _MetaTag extends StatelessWidget {
  const _MetaTag({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: scheme.onSurfaceVariant),
          const SizedBox(width: 4),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall
                ?.copyWith(color: scheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

class _ProgressHeader extends StatelessWidget {
  const _ProgressHeader({
    required this.current,
    required this.total,
    required this.answered,
    required this.progress,
    required this.sectionName,
  });

  final int current;
  final int total;
  final int answered;
  final double progress;
  final String sectionName;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                sectionName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              '$current / $total',
              style: theme.textTheme.labelMedium?.copyWith(
                color: scheme.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 9),
        ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: LinearProgressIndicator(
            value: progress.clamp(0.0, 1.0).toDouble(),
            minHeight: 7,
            backgroundColor: scheme.surfaceContainerHighest,
            color: scheme.primary,
          ),
        ),
        const SizedBox(height: 7),
        Row(
          children: [
            Icon(Icons.check_circle_outline, size: 13, color: scheme.primary),
            const SizedBox(width: 5),
            Text(
              '$answered of $total answered',
              style: theme.textTheme.labelSmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            const Spacer(),
            Text(
              '${(progress * 100).round()}% complete',
              style: theme.textTheme.labelSmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _QuestionSlide extends StatelessWidget {
  const _QuestionSlide({
    required this.number,
    required this.total,
    required this.question,
    required this.isAttentionCheck,
    required this.answer,
    required this.textController,
    required this.onChanged,
  });

  final int number;
  final int total;
  final Map<String, dynamic> question;
  final bool isAttentionCheck;
  final dynamic answer;
  final TextEditingController textController;
  final ValueChanged<dynamic> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final prompt = question['prompt']?.toString();
    final hint = question['hint']?.toString();
    final type = question['type']?.toString();
    final settings = question['settings'] is Map
        ? question['settings'] as Map
        : const {};
    final options = question['options'] is List
        ? (question['options'] as List).map((e) => e.toString()).toList()
        : const <String>[];
    final scale = settings['scale'] is Map
        ? settings['scale'] as Map
        : const {};
    final minimum = (scale['min'] as num?)?.toDouble() ?? 1;
    final maximum = (scale['max'] as num?)?.toDouble() ?? 5;
    final minWords = (settings['min_words'] as num?)?.toInt();
    final words = _countWords(answer);

    return ListView(
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
      children: [
        Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                'QUESTION ${number.toString().padLeft(2, '0')}',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: scheme.onPrimaryContainer,
                  fontWeight: FontWeight.w800,
                  letterSpacing: .5,
                ),
              ),
            ),
            const Spacer(),
            if (isAttentionCheck) ...[
              Icon(Icons.fact_check_outlined, size: 15, color: scheme.tertiary),
              const SizedBox(width: 5),
              Text(
                'Attention check',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: scheme.tertiary,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(width: 9),
            ],
            Text(
              type == 'multiple_choice'
                  ? 'Select all that apply'
                  : type == 'long_text'
                  ? 'Written response'
                  : type == 'rating'
                  ? 'Rate your experience'
                  : 'Choose one answer',
              style: theme.textTheme.labelSmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
        const SizedBox(height: 15),
        if (prompt != null && prompt.trim().isNotEmpty)
          Text(
            prompt,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
              height: 1.22,
            ),
          ),
        if (hint != null && hint.isNotEmpty) ...[
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.lightbulb_outline, size: 17, color: scheme.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  hint,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
        ],
        if (isAttentionCheck) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: scheme.tertiaryContainer.withValues(alpha: .55),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.info_outline_rounded,
                  size: 18,
                  color: scheme.tertiary,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    'Read the prompt carefully and follow its response instruction.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onTertiaryContainer,
                      height: 1.35,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 22),
        if (type == 'single_choice')
          ...options.map(
            (option) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _AnswerChoice(
                label: option,
                selected: answer == option,
                multiple: false,
                onTap: () => onChanged(option),
              ),
            ),
          ),
        if (type == 'multiple_choice')
          ...options.asMap().entries.map((entry) {
            final optionIndex = entry.key;
            final option = entry.value;
            final selected = answer is List && answer.contains(option);
            final exclusiveIndex = settings['exclusive_option_index'];
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _AnswerChoice(
                label: option,
                selected: selected,
                multiple: true,
                onTap: () {
                  final values = answer is List
                      ? List<String>.from(answer as List)
                      : <String>[];
                  final exclusive =
                      exclusiveIndex is num && optionIndex == exclusiveIndex;
                  if (selected) {
                    values.remove(option);
                  } else if (exclusive) {
                    values
                      ..clear()
                      ..add(option);
                  } else {
                    if (exclusiveIndex is num &&
                        exclusiveIndex.toInt() >= 0 &&
                        exclusiveIndex.toInt() < options.length) {
                      values.remove(options[exclusiveIndex.toInt()]);
                    }
                    values.add(option);
                  }
                  onChanged(values);
                },
              ),
            );
          }),
        if (type == 'rating')
          _RatingInput(
            answer: answer,
            minimum: minimum,
            maximum: maximum,
            lowLabel: scale['low_label']?.toString() ?? 'Low',
            highLabel: scale['high_label']?.toString() ?? 'High',
            onChanged: onChanged,
          ),
        if (type == 'long_text') ...[
          TextField(
            controller: textController,
            minLines: 5,
            maxLines: 9,
            maxLength: 3000,
            textCapitalization: TextCapitalization.sentences,
            keyboardType: TextInputType.multiline,
            style: theme.textTheme.bodyLarge,
            onChanged: onChanged,
            decoration: InputDecoration(
              hintText: 'Write your answer in your own words…',
              alignLabelWithHint: true,
              filled: true,
              fillColor: scheme.surfaceContainerLow,
              counterStyle: theme.textTheme.labelSmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
              prefixIcon: const Padding(
                padding: EdgeInsets.only(left: 13, top: 14),
                child: Icon(Icons.edit_note_outlined),
              ),
              prefixIconConstraints: const BoxConstraints(minWidth: 42),
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(
                words >= (minWords ?? 1)
                    ? Icons.check_circle
                    : Icons.info_outline,
                size: 15,
                color: words >= (minWords ?? 1)
                    ? scheme.primary
                    : scheme.onSurfaceVariant,
              ),
              const SizedBox(width: 6),
              Text(
                minWords == null
                    ? '$words words'
                    : '$words / $minWords words minimum',
                style: theme.textTheme.labelMedium?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
              const Spacer(),
              if (words >= (minWords ?? 1))
                Text(
                  'Minimum reached',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
        ],
        if (type == 'confirmation')
          _AnswerChoice(
            label:
                settings['confirmation_label']?.toString() ??
                'I confirm this answer is accurate and my own.',
            selected: answer == true,
            multiple: true,
            onTap: () => onChanged(answer != true),
          ),
      ],
    );
  }
}

int _countWords(dynamic value) {
  if (value is! String || value.trim().isEmpty) return 0;
  return value.trim().split(RegExp(r'\s+')).length;
}

bool _reviewAnswersMatch(dynamic actual, dynamic expected) {
  if (expected is Map && expected['mode'] == 'contains') {
    final String phrase = expected['phrase']?.toString() ?? '';
    if (phrase.isEmpty || actual is! String) return false;
    final bool caseSensitive = expected['case_sensitive'] == true;
    return caseSensitive
        ? actual.contains(phrase)
        : actual.toLowerCase().contains(phrase.toLowerCase());
  }
  if (actual is List && expected is List) {
    final actualValues = actual.map((value) => value.toString()).toList()
      ..sort();
    final expectedValues = expected.map((value) => value.toString()).toList()
      ..sort();
    if (actualValues.length != expectedValues.length) return false;
    for (var index = 0; index < actualValues.length; index++) {
      if (actualValues[index] != expectedValues[index]) return false;
    }
    return true;
  }
  if (actual is num && expected is num) return actual == expected;
  return actual == expected;
}

class _AnswerChoice extends StatelessWidget {
  const _AnswerChoice({
    required this.label,
    required this.selected,
    required this.multiple,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final bool multiple;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Material(
      color: selected ? scheme.secondaryContainer : scheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(
              color: selected ? scheme.primary : scheme.outlineVariant,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Row(
            children: [
              Icon(
                selected
                    ? (multiple
                          ? Icons.check_circle_rounded
                          : Icons.radio_button_checked_rounded)
                    : (multiple
                          ? Icons.circle_outlined
                          : Icons.radio_button_off_rounded),
                color: selected ? scheme.primary : scheme.onSurfaceVariant,
                size: 21,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                    height: 1.35,
                    color: selected
                        ? scheme.onSecondaryContainer
                        : scheme.onSurface,
                  ),
                ),
              ),
              if (selected) ...[
                const SizedBox(width: 8),
                Icon(Icons.check_rounded, size: 17, color: scheme.primary),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _RatingInput extends StatelessWidget {
  const _RatingInput({
    required this.answer,
    required this.minimum,
    required this.maximum,
    required this.lowLabel,
    required this.highLabel,
    required this.onChanged,
  });

  final dynamic answer;
  final double minimum;
  final double maximum;
  final String lowLabel;
  final String highLabel;
  final ValueChanged<dynamic> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final value = ((answer as num?)?.toDouble() ?? minimum)
        .clamp(minimum, maximum)
        .toDouble();
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 15),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Column(
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: scheme.primaryContainer,
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: Text(
              value.round().toString(),
              style: theme.textTheme.headlineMedium?.copyWith(
                color: scheme.onPrimaryContainer,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Your rating',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          Slider(
            value: value,
            min: minimum,
            max: maximum,
            divisions: math.max(1, (maximum - minimum).round()),
            label: value.round().toString(),
            onChanged: (next) => onChanged(next.round()),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  lowLabel,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  highLabel,
                  textAlign: TextAlign.end,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TrustSummaryPage extends StatelessWidget {
  const _TrustSummaryPage({
    required this.appName,
    required this.questions,
    required this.answers,
  });

  final String appName;
  final List<Map<String, dynamic>> questions;
  final Map<int, dynamic> answers;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    var claimsBroken = 0;
    var warningSignals = 0;
    var attentionChecks = 0;
    var attentionPassed = 0;
    String? developerAssessment;
    String? verdict;

    for (final question in questions) {
      final id = _questionId(question);
      if (id == null) continue;
      final value = answers[id];
      final key =
          question['question_key']?.toString() ??
          question['key']?.toString() ??
          '';
      final settings = question['settings'] is Map
          ? question['settings'] as Map
          : const {};
      final options = question['options'] is List
          ? (question['options'] as List).map((e) => e.toString()).toList()
          : const <String>[];

      if (question['is_attention_check'] == true) {
        attentionChecks++;
        if (_reviewAnswersMatch(value, question['attention_check_answer'])) {
          attentionPassed++;
        }
      }
      if (key == 'dev') developerAssessment = value?.toString();
      if (key == 'verdict') verdict = value?.toString();

      final riskIndexes = settings['risk_option_indexes'];
      if (riskIndexes is! List || options.isEmpty) continue;
      final riskyAnswers = value is List ? value : [value];
      final count = riskyAnswers.where((answer) {
        final index = options.indexOf(answer?.toString() ?? '');
        return index >= 0 && riskIndexes.contains(index);
      }).length;
      if (key == 'match') {
        claimsBroken += count;
      } else {
        warningSignals += count;
      }
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: scheme.primaryContainer,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.fact_check_outlined,
                size: 28,
                color: scheme.onPrimaryContainer,
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Ready to submit?',
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: scheme.onPrimaryContainer,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      'Review your answers and submit your experience for verification.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: scheme.onPrimaryContainer,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        Text(
          'Your review of $appName',
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'This is a summary of the signals in your answers. The final app trust score is confirmed after human review.',
          style: theme.textTheme.bodyMedium?.copyWith(
            color: scheme.onSurfaceVariant,
            height: 1.45,
          ),
        ),
        const SizedBox(height: 18),
        Container(
          padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
          decoration: BoxDecoration(
            color: scheme.surfaceContainerLow,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: scheme.outlineVariant),
          ),
          child: Column(
            children: [
              _SummaryMetric(
                label: 'App trust score',
                value: 'Pending review',
                icon: Icons.hourglass_top_rounded,
                emphasize: true,
              ),
              _SummaryMetric(
                label: 'Store claims that did not match',
                value: '$claimsBroken',
                icon: Icons.compare_arrows_rounded,
              ),
              _SummaryMetric(
                label: 'Warning signals selected',
                value: '$warningSignals',
                icon: Icons.warning_amber_rounded,
                warning: warningSignals > 0,
              ),
              _SummaryMetric(
                label: 'Attention checks',
                value: attentionChecks == 0
                    ? 'Not required'
                    : '$attentionPassed / $attentionChecks passed',
                icon: Icons.center_focus_strong,
                warning: attentionChecks > attentionPassed,
              ),
              _SummaryMetric(
                label: 'Developer verification',
                value: developerAssessment ?? 'Not answered',
                icon: Icons.business_outlined,
              ),
              if (verdict != null)
                _SummaryMetric(
                  label: 'Your verdict',
                  value: verdict,
                  icon: Icons.rate_review_outlined,
                  last: true,
                ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(15),
          decoration: BoxDecoration(
            color: scheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(15),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.shield_outlined, color: scheme.primary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Your answers will be submitted for admin approval. The trust score is finalized after the review is verified.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                    height: 1.45,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SummaryMetric extends StatelessWidget {
  const _SummaryMetric({
    required this.label,
    required this.value,
    required this.icon,
    this.warning = false,
    this.emphasize = false,
    this.last = false,
  });

  final String label;
  final String value;
  final IconData icon;
  final bool warning;
  final bool emphasize;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final valueColor = warning ? scheme.error : scheme.primary;
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 13),
      decoration: BoxDecoration(
        border: last
            ? null
            : Border(bottom: BorderSide(color: scheme.outlineVariant)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 18, color: scheme.onSurfaceVariant),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: theme.textTheme.labelLarge?.copyWith(
                color: warning || emphasize ? valueColor : scheme.onSurface,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FooterControls extends StatelessWidget {
  const _FooterControls({
    required this.isSummary,
    required this.isSending,
    required this.hasPrevious,
    required this.onBack,
    required this.onContinue,
  });

  final bool isSummary;
  final bool isSending;
  final bool hasPrevious;
  final VoidCallback onBack;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
      decoration: BoxDecoration(
        color: scheme.surface,
        border: Border(top: BorderSide(color: scheme.outlineVariant)),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            if (hasPrevious) ...[
              OutlinedButton(
                onPressed: isSending ? null : onBack,
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(54, 52),
                  padding: const EdgeInsets.symmetric(horizontal: 15),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Icon(Icons.arrow_back_rounded),
              ),
              const SizedBox(width: 10),
            ],
            Expanded(
              child: FilledButton.icon(
                onPressed: isSending ? null : onContinue,
                icon: isSending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(
                        isSummary
                            ? Icons.send_rounded
                            : Icons.arrow_forward_rounded,
                      ),
                label: Text(
                  isSending
                      ? 'Submitting…'
                      : isSummary
                      ? 'Submit review'
                      : 'Continue',
                ),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  textStyle: theme.textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
