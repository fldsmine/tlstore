import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class SubscriptionQuestionnaireScreen extends StatefulWidget {
  const SubscriptionQuestionnaireScreen({
    super.key,
    required this.api,
    required this.token,
    required this.groupKey,
    required this.user,
  });

  final AuthApi api;
  final String token;
  final String groupKey;
  final Map<String, dynamic> user;

  @override
  State<SubscriptionQuestionnaireScreen> createState() =>
      _SubscriptionQuestionnaireScreenState();
}

class _SubscriptionQuestionnaireScreenState
    extends State<SubscriptionQuestionnaireScreen> {
  static const _questions = <_QualificationQuestion>[
    _QualificationQuestion(
      key: 'device',
      title: 'Which device do you use?',
      subtitle: 'Choose the device you normally use to test mobile apps.',
      options: [
        _QuestionOption('📱', 'iPhone', 'iphone'),
        _QuestionOption('🤖', 'Android', 'android'),
        _QuestionOption('📲', 'Windows', 'windows'),
        _QuestionOption('➡️', 'Other', 'other'),
      ],
    ),
    _QualificationQuestion(
      key: 'weekly_income',
      title: 'How much extra income would make a difference for you each week?',
      subtitle: 'Pick the range that best matches your goal.',
      options: [
        _QuestionOption('💵', r'$100–$200', '100_200'),
        _QuestionOption('💰', r'$200–$500', '200_500'),
        _QuestionOption('🤑', r'$500+', '500_plus'),
      ],
    ),
    _QualificationQuestion(
      key: 'daily_time',
      title: 'How much time can you spend testing apps each day?',
      subtitle: 'There is no right answer. Choose what fits your schedule.',
      options: [
        _QuestionOption('⏱️', '5–10 minutes', '5_10'),
        _QuestionOption('⌛', '10–30 minutes', '10_30'),
        _QuestionOption('🕒', '30+ minutes', '30_plus'),
      ],
    ),
    _QualificationQuestion(
      key: 'tested_before',
      title: 'Have you ever tested a mobile app for money before?',
      subtitle: 'Let us know about your previous app-testing experience.',
      options: [
        _QuestionOption('✅', 'Yes', 'yes'),
        _QuestionOption('❌', 'No', 'no'),
      ],
    ),
    _QualificationQuestion(
      key: 'ready_today',
      title: 'If selected, are you ready to start testing apps today?',
      subtitle: 'This helps us understand when you are available to begin.',
      options: [
        _QuestionOption('🚀', "Yes, I'm ready", 'yes'),
        _QuestionOption('🤔', 'Maybe later', 'maybe_later'),
      ],
    ),
    _QualificationQuestion(
      key: 'motivation',
      title: 'Why are you interested in getting paid to test apps?',
      subtitle: 'Choose the main reason that motivates you.',
      options: [
        _QuestionOption('💸', 'Extra income', 'extra_income'),
        _QuestionOption('📋', 'Pay bills', 'pay_bills'),
        _QuestionOption('🎯', 'Easy side hustle', 'side_hustle'),
      ],
    ),
  ];

  final Map<String, dynamic> _answers = {};
  int _step = 0;
  bool _saving = false;
  bool _advancing = false;
  String? _error;

  bool get _isComplete => _step >= _questions.length;

  Future<void> _choose(String questionKey, String value) async {
    if (_advancing || _saving) return;

    setState(() {
      _advancing = true;
      _answers[questionKey] = value;
      _error = null;
    });

    await Future<void>.delayed(const Duration(milliseconds: 180));
    if (!mounted) return;

    setState(() {
      _step++;
      _advancing = false;
    });
  }

  void _goBack() {
    if (_saving) return;

    if (_isComplete) {
      setState(() {
        _step = _questions.length - 1;
        _error = null;
      });
      return;
    }

    if (_step > 0) {
      setState(() {
        _step--;
        _error = null;
      });
    } else {
      Navigator.of(context).pop(false);
    }
  }

  Future<void> _submit() async {
    if (_saving) return;

    if (_answers.length != _questions.length) {
      setState(() => _error = 'Please answer each question to continue.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await widget.api.submitSubscriptionQuestionnaire(
        token: widget.token,
        groupKey: widget.groupKey,
        answers: _answers,
      );

      if (mounted) Navigator.of(context).pop(true);
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Something went wrong while saving your answers. Please try again.';
        });
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Scaffold(
      backgroundColor: colors.surface,
      appBar: AppBar(
        backgroundColor: colors.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          tooltip: 'Back',
          onPressed: _saving ? null : _goBack,
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'App tester qualification',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              _isComplete ? 'Ready to submit' : 'A few quick questions',
              style: theme.textTheme.labelSmall?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 18),
            child: Center(
              child: _HeaderPill(
                icon: Icons.lock_outline_rounded,
                label: 'Private',
                colors: colors,
              ),
            ),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          child: _saving
              ? _SavingAnswersView(
                  key: const ValueKey('saving'),
                  colors: colors,
                )
              : _isComplete
                  ? _CompletionView(
                      key: const ValueKey('complete'),
                      user: widget.user,
                      error: _error,
                      onSubmit: _submit,
                      saving: _saving,
                    )
                  : _QuestionView(
                      key: ValueKey('question-$_step'),
                      question: _questions[_step],
                      step: _step,
                      total: _questions.length,
                      selectedValue:
                          _answers[_questions[_step].key]?.toString(),
                      colors: colors,
                      advancing: _advancing,
                      error: _error,
                      onChoose: (value) =>
                          _choose(_questions[_step].key, value),
                    ),
        ),
      ),
    );
  }
}

class _QuestionView extends StatelessWidget {
  const _QuestionView({
    super.key,
    required this.question,
    required this.step,
    required this.total,
    required this.colors,
    required this.onChoose,
    required this.advancing,
    this.selectedValue,
    this.error,
  });

  final _QualificationQuestion question;
  final int step;
  final int total;
  final ColorScheme colors;
  final ValueChanged<String> onChoose;
  final bool advancing;
  final String? selectedValue;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final progress = (step + 1) / total;

    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: constraints.maxHeight - 40,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      'QUESTION ${step + 1}',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: colors.primary,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.1,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${step + 1} of $total',
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: colors.onSurfaceVariant,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 7,
                    color: colors.primary,
                    backgroundColor: colors.surfaceContainerHighest,
                  ),
                ),
                const SizedBox(height: 26),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: colors.primaryContainer.withValues(alpha: 0.42),
                    borderRadius: BorderRadius.circular(22),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: colors.surface.withValues(alpha: 0.8),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(
                          Icons.tips_and_updates_outlined,
                          color: colors.primary,
                        ),
                      ),
                      const SizedBox(width: 13),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Help us get to know you',
                              style: theme.textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Your answers help us match you with relevant testing opportunities.',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: colors.onSurfaceVariant,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 30),
                Text(
                  question.title,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                    height: 1.2,
                    letterSpacing: -0.45,
                  ),
                ),
                if (question.subtitle.isNotEmpty) ...[
                  const SizedBox(height: 9),
                  Text(
                    question.subtitle,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      height: 1.5,
                    ),
                  ),
                ],
                const SizedBox(height: 22),
                Row(
                  children: [
                    Text(
                      'SELECT ONE OPTION',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colors.onSurfaceVariant,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.9,
                      ),
                    ),
                    const Spacer(),
                    Icon(
                      Icons.touch_app_outlined,
                      size: 15,
                      color: colors.onSurfaceVariant,
                    ),
                    const SizedBox(width: 5),
                    Text(
                      'Tap to choose',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                for (final option in question.options) ...[
                  _AnswerTile(
                    option: option,
                    colors: colors,
                    selected: selectedValue == option.value,
                    enabled: !advancing,
                    onTap: () => onChoose(option.value),
                  ),
                  const SizedBox(height: 11),
                ],
                if (advancing) ...[
                  const SizedBox(height: 4),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      SizedBox(
                        width: 15,
                        height: 15,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: colors.primary,
                        ),
                      ),
                      const SizedBox(width: 9),
                      Text(
                        'Moving to next question…',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ],
                if (error != null) ...[
                  const SizedBox(height: 14),
                  _InlineError(message: error!, colors: colors),
                ],
                const SizedBox(height: 18),
                Center(
                  child: Text(
                    'You can go back and change your answers.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _AnswerTile extends StatelessWidget {
  const _AnswerTile({
    required this.option,
    required this.colors,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final _QuestionOption option;
  final ColorScheme colors;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 160),
      decoration: BoxDecoration(
        color: selected ? colors.primaryContainer : colors.surface,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(
          color: selected ? colors.primary : colors.outlineVariant,
          width: selected ? 1.5 : 1,
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: enabled ? onTap : null,
          borderRadius: BorderRadius.circular(17),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: selected
                        ? colors.surface.withValues(alpha: 0.8)
                        : colors.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(
                    option.emoji,
                    style: const TextStyle(fontSize: 24),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    option.label,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: colors.onSurface,
                    ),
                  ),
                ),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  width: 25,
                  height: 25,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: selected ? colors.primary : Colors.transparent,
                    border: Border.all(
                      color: selected ? colors.primary : colors.outline,
                      width: selected ? 0 : 1.5,
                    ),
                  ),
                  child: selected
                      ? Icon(
                          Icons.check_rounded,
                          size: 17,
                          color: colors.onPrimary,
                        )
                      : Icon(
                          Icons.chevron_right_rounded,
                          size: 17,
                          color: colors.onSurfaceVariant,
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CompletionView extends StatelessWidget {
  const _CompletionView({
    super.key,
    required this.user,
    required this.onSubmit,
    required this.saving,
    this.error,
  });

  final Map<String, dynamic> user;
  final String? error;
  final VoidCallback onSubmit;
  final bool saving;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 28),
      children: [
        Row(
          children: [
            Text(
              'ALL DONE',
              style: theme.textTheme.labelMedium?.copyWith(
                color: colors.primary,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.1,
              ),
            ),
            const Spacer(),
            Text(
              '6 of 6',
              style: theme.textTheme.labelLarge?.copyWith(
                color: colors.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: LinearProgressIndicator(
            value: 1,
            minHeight: 7,
            color: colors.primary,
            backgroundColor: colors.surfaceContainerHighest,
          ),
        ),
        const SizedBox(height: 30),
        Container(
          width: 82,
          height: 82,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: colors.primaryContainer,
          ),
          child: Icon(
            Icons.task_alt_rounded,
            size: 43,
            color: colors.onPrimaryContainer,
          ),
        ),
        const SizedBox(height: 22),
        Text(
          "You're all set!",
          textAlign: TextAlign.center,
          style: theme.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w900,
            letterSpacing: -0.6,
          ),
        ),
        const SizedBox(height: 9),
        Text(
          'You have completed the qualification questions. Confirm your account details and submit your answers to continue.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: colors.onSurfaceVariant,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 28),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: colors.surfaceContainerLow,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: colors.outlineVariant),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.account_circle_outlined,
                    color: colors.primary,
                    size: 21,
                  ),
                  const SizedBox(width: 9),
                  Text(
                    'Account details',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 19),
              _AccountDetail(
                label: 'First name',
                value: _firstName(user['full_name']),
                icon: Icons.person_outline_rounded,
              ),
              const SizedBox(height: 17),
              Divider(color: colors.outlineVariant, height: 1),
              const SizedBox(height: 17),
              _AccountDetail(
                label: 'Email address',
                value: user['email']?.toString().trim().isNotEmpty == true
                    ? user['email'].toString()
                    : '—',
                icon: Icons.email_outlined,
              ),
            ],
          ),
        ),
        const SizedBox(height: 15),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: colors.secondaryContainer.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.shield_outlined,
                color: colors.onSecondaryContainer,
                size: 20,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Your responses will be saved to your account and used for qualification.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSecondaryContainer,
                    height: 1.45,
                  ),
                ),
              ),
            ],
          ),
        ),
        if (error != null) ...[
          const SizedBox(height: 16),
          _InlineError(message: error!, colors: colors),
        ],
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: saving ? null : onSubmit,
          icon: const Icon(Icons.check_circle_outline_rounded),
          label: const Text('Save answers & continue'),
          style: FilledButton.styleFrom(
            minimumSize: const Size.fromHeight(56),
            textStyle: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Center(
          child: Text(
            'You can still go back to review your answers.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
            ),
          ),
        ),
      ],
    );
  }

  String _firstName(Object? fullName) {
    final name = fullName?.toString().trim() ?? '';
    return name.isEmpty ? '—' : name.split(RegExp(r'\s+')).first;
  }
}

class _AccountDetail extends StatelessWidget {
  const _AccountDetail({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: colors.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, size: 19, color: colors.onSurfaceVariant),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                value,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SavingAnswersView extends StatelessWidget {
  const _SavingAnswersView({super.key, required this.colors});

  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: 52,
              height: 52,
              child: CircularProgressIndicator(
                strokeWidth: 3,
                color: colors.primary,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'Saving your answers',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Please wait while we securely save your responses.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: colors.onSurfaceVariant,
                height: 1.45,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeaderPill extends StatelessWidget {
  const _HeaderPill({
    required this.icon,
    required this.label,
    required this.colors,
  });

  final IconData icon;
  final String label;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: colors.onSurfaceVariant),
          const SizedBox(width: 5),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: colors.onSurfaceVariant,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message, required this.colors});

  final String message;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: colors.errorContainer,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.error_outline_rounded,
            color: colors.onErrorContainer,
            size: 19,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: colors.onErrorContainer,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _QualificationQuestion {
  const _QualificationQuestion({
    required this.key,
    required this.title,
    required this.subtitle,
    required this.options,
  });

  final String key;
  final String title;
  final String subtitle;
  final List<_QuestionOption> options;
}

class _QuestionOption {
  const _QuestionOption(this.emoji, this.label, this.value);

  final String emoji;
  final String label;
  final String value;
}
