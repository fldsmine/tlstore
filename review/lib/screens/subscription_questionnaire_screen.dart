import 'package:flutter/material.dart';

import '../services/auth_api.dart';

abstract final class _Palette {
  static const Color indigo = Color(0xFF4338CA);
  static const Color pink = Color(0xFFEC4899);
  static const Color green = Color(0xFF16A34A);
  static const Color amber = Color(0xFFF59E0B);

  static const LinearGradient hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[green, indigo, pink],
  );

  static const LinearGradient mint = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF0D9488), Color(0xFF22C55E)],
  );

  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF0284C7), Color(0xFF4338CA)],
  );

}

ThemeData _adminTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _Palette.indigo,
      brightness: base.brightness,
    ),
  );
}

class _IntroSlide {
  const _IntroSlide({
    required this.badge,
    required this.title,
    required this.body,
    required this.icon,
    required this.gradient,
    required this.iconColor,
    required this.badgeBackground,
    required this.badgeForeground,
    this.ctaLabel,
    this.ctaGradient,
    this.ctaForeground = Colors.white,
  });

  final String badge;
  final String title;
  final String body;
  final IconData icon;
  final LinearGradient gradient;
  final Color iconColor;
  final Color badgeBackground;
  final Color badgeForeground;
  final String? ctaLabel;
  final LinearGradient? ctaGradient;
  final Color ctaForeground;
}

const List<_IntroSlide> _slides = <_IntroSlide>[
  _IntroSlide(
    badge: 'Important',
    title: 'Before you start',
    body:
        'Many security measures are built into our surveys to ensure the quality '
        'of your profile. Please read this information carefully before beginning.',
    icon: Icons.warning_amber_rounded,
    gradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: <Color>[Color(0xFFFCD34D), Color(0xFFF59E0B)],
    ),
    iconColor: Color(0xFF92400E),
    badgeBackground: Color(0xFFFEF3C7),
    badgeForeground: Color(0xFFB45309),
  ),
  _IntroSlide(
    badge: 'Quality pays',
    title: 'Honest answers, optimized earnings',
    body:
        'The quality of your answers is paramount. The more honest and sincere '
        'they are, the more quality surveys we offer you and the more attractive '
        'your earnings become.',
    icon: Icons.check_rounded,
    gradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: <Color>[Color(0xFF6EE7B7), Color(0xFF34D399)],
    ),
    iconColor: Color(0xFF065F46),
    badgeBackground: Color(0xFFD1FAE5),
    badgeForeground: Color(0xFF047857),
  ),
  _IntroSlide(
    badge: 'To avoid',
    title: 'Poor answers have consequences',
    body:
        'Answering falsely, carelessly, or hastily can lower your earnings and '
        'the number of surveys offered to you. You may also be removed from a '
        'survey before completion.',
    icon: Icons.trending_down_rounded,
    gradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: <Color>[Color(0xFFFECDD3), Color(0xFFFDA4AF)],
    ),
    iconColor: Color(0xFF9F1239),
    badgeBackground: Color(0xFFFFE4E6),
    badgeForeground: Color(0xFFBE123C),
  ),
  _IntroSlide(
    badge: 'Ready to start?',
    title: 'Read, think, answer',
    body:
        'Take time to carefully read each question and answer it honestly and '
        'sincerely. Your seriousness is the key to an enriching and sustainably '
        'rewarding experience.',
    icon: Icons.edit_rounded,
    gradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: <Color>[Color(0xFFC4B5FD), Color(0xFFA78BFA)],
    ),
    iconColor: Color(0xFF5B21B6),
    badgeBackground: Color(0xFFEDE9FE),
    badgeForeground: Color(0xFF6D28D9),
    ctaLabel: 'Access paid surveys',
    ctaGradient: LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: <Color>[Color(0xFFFBBF24), Color(0xFFF59E0B)],
    ),
    ctaForeground: Color(0xFF451A03),
  ),
];

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
  int _introStep = 0;
  int _step = 0;
  bool _saving = false;
  bool _advancing = false;
  String? _error;

  bool get _isIntro => _introStep < _slides.length;
  bool get _isComplete => !_isIntro && _step >= _questions.length;

  void _nextSlide() {
    if (_saving) return;

    if (_introStep < _slides.length - 1) {
      setState(() => _introStep++);
    } else {
      setState(() => _introStep = _slides.length);
    }
  }

  void _skipIntro() {
    if (_saving) return;

    setState(() => _introStep = _slides.length);
  }

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

    if (_isIntro) {
      if (_introStep > 0) {
        setState(() => _introStep--);
      } else {
        Navigator.of(context).pop(false);
      }
      return;
    }

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
      setState(() => _introStep = _slides.length - 1);
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

    return Theme(
      data: _adminTheme(context),
      child: Scaffold(
        backgroundColor: colors.primary,
        appBar: AppBar(
          backgroundColor: colors.primary,
          surfaceTintColor: Colors.white,
          elevation: 0,
          scrolledUnderElevation: 0,
          foregroundColor: Colors.white,
          flexibleSpace: Container(
            decoration: const BoxDecoration(gradient: _Palette.ocean),
          ),
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
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                ),
              ),
              Text(
                _isIntro
                    ? 'Before you begin'
                    : _isComplete
                        ? 'Ready to submit'
                        : 'A few quick questions',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.82),
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
                ),
              ),
            ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: <Color>[
                  colors.primaryContainer.withValues(alpha: 0.5),
                  colors.surface,
                ],
                stops: const <double>[0.0, 0.5],
              ),
            ),
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 240),
              child: _saving
                  ? _SavingAnswersView(
                      key: const ValueKey('saving'),
                      colors: colors,
                    )
                  : _isIntro
                      ? _IntroView(
                          key: ValueKey('intro-$_introStep'),
                          slide: _slides[_introStep],
                          step: _introStep,
                          total: _slides.length,
                          onNext: _nextSlide,
                          onBack: _goBack,
                          onSkip: _skipIntro,
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
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Intro slides
// ---------------------------------------------------------------------------

class _IntroView extends StatelessWidget {
  const _IntroView({
    super.key,
    required this.slide,
    required this.step,
    required this.total,
    required this.onNext,
    required this.onBack,
    required this.onSkip,
  });

  final _IntroSlide slide;
  final int step;
  final int total;
  final VoidCallback onNext;
  final VoidCallback onBack;
  final VoidCallback onSkip;

  static const LinearGradient _nextGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF4C1D95), Color(0xFF6D28D9)],
  );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 28),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight - 46),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: GestureDetector(
                  onHorizontalDragEnd: (details) {
                    final double? velocity = details.primaryVelocity;
                    if (velocity == null) return;
                    if (velocity < 0) {
                      onNext();
                    } else if (velocity > 0) {
                      onBack();
                    }
                  },
                  child: Container(
                    decoration: BoxDecoration(
                      color: colors.surface,
                      borderRadius: BorderRadius.circular(24),
                      border: Border.all(color: colors.outlineVariant),
                      boxShadow: <BoxShadow>[
                        BoxShadow(
                          color: colors.shadow.withValues(alpha: 0.10),
                          blurRadius: 24,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            height: 5,
                            width: double.infinity,
                            decoration: BoxDecoration(gradient: slide.gradient),
                          ),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                            child: Column(
                              children: [
                                Row(
                                  children: [
                                    TextButton(
                                      onPressed: onSkip,
                                      child: Text(
                                        'Skip',
                                        style: theme.textTheme.labelLarge?.copyWith(
                                          color: colors.onSurfaceVariant,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                    const Spacer(),
                                    _SegmentedProgress(
                                      step: step,
                                      total: total,
                                      activeColor: _Palette.amber,
                                      doneColor: colors.primary,
                                      trackColor: colors.surfaceContainerHighest,
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 26),
                                _SlideMedallion(slide: slide),
                                const SizedBox(height: 22),
                                Text(
                                  slide.title,
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.headlineSmall?.copyWith(
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -0.4,
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  slide.body,
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: colors.onSurfaceVariant,
                                    height: 1.55,
                                  ),
                                ),
                                const SizedBox(height: 26),
                                _DashedDivider(color: colors.outlineVariant),
                                const SizedBox(height: 18),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    if (step > 0)
                                      TextButton(
                                        onPressed: onBack,
                                        child: Text(
                                          'Back',
                                          style: theme.textTheme.labelLarge?.copyWith(
                                            color: colors.onSurfaceVariant,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      )
                                    else
                                      const SizedBox.shrink(),
                                    Flexible(
                                      child: _GradientButton(
                                        label: slide.ctaLabel ?? 'Next',
                                        icon: Icons.arrow_forward_rounded,
                                        gradient: slide.ctaGradient ?? _nextGradient,
                                        foreground: slide.ctaForeground,
                                        onPressed: onNext,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _SlideMedallion extends StatelessWidget {
  const _SlideMedallion({required this.slide});

  final _IntroSlide slide;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 158,
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.center,
        children: [
          Container(
            width: 134,
            height: 134,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: slide.badgeBackground, width: 2),
            ),
          ),
          Container(
            width: 108,
            height: 108,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: slide.gradient,
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: slide.gradient.colors.last.withValues(alpha: 0.35),
                  blurRadius: 18,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
          ),
          Icon(slide.icon, size: 46, color: slide.iconColor),
          Positioned(
            bottom: 2,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 7),
              decoration: BoxDecoration(
                color: slide.badgeBackground,
                borderRadius: BorderRadius.circular(20),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: slide.badgeForeground.withValues(alpha: 0.18),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: slide.badgeForeground,
                    ),
                  ),
                  const SizedBox(width: 7),
                  Text(
                    slide.badge,
                    style: TextStyle(
                      color: slide.badgeForeground,
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SegmentedProgress extends StatelessWidget {
  const _SegmentedProgress({
    required this.step,
    required this.total,
    required this.activeColor,
    required this.doneColor,
    required this.trackColor,
    this.expand = false,
    this.segmentWidth = 22,
  });

  final int step;
  final int total;
  final Color activeColor;
  final Color doneColor;
  final Color trackColor;
  final bool expand;
  final double segmentWidth;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
      children: [
        for (int i = 0; i < total; i++) ...[
          if (expand)
            Expanded(
              child: Container(
                height: 6,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(3),
                  color: i < step ? doneColor : (i == step ? activeColor : trackColor),
                ),
              ),
            )
          else
            Container(
              width: segmentWidth,
              height: 5,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(3),
                color: i < step ? doneColor : (i == step ? activeColor : trackColor),
              ),
            ),
          if (i < total - 1) const SizedBox(width: 5),
        ],
      ],
    );
  }
}

class _DashedDivider extends StatelessWidget {
  const _DashedDivider({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final int count = (constraints.maxWidth / 11).floor();
        return Row(
          children: [
            for (int i = 0; i < count; i++) ...[
              Container(width: 6, height: 1.5, color: color),
              const SizedBox(width: 5),
            ],
          ],
        );
      },
    );
  }
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({
    required this.label,
    required this.icon,
    required this.gradient,
    required this.foreground,
    required this.onPressed,
    this.height = 52,
  });

  final String label;
  final IconData icon;
  final LinearGradient gradient;
  final Color foreground;
  final VoidCallback? onPressed;
  final double height;

  @override
  Widget build(BuildContext context) {
    final bool enabled = onPressed != null;

    return Opacity(
      opacity: enabled ? 1 : 0.55,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            height: height,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            decoration: BoxDecoration(
              gradient: gradient,
              borderRadius: BorderRadius.circular(16),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: gradient.colors.last.withValues(alpha: 0.35),
                  blurRadius: 16,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: foreground,
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                ),
                const SizedBox(width: 9),
                Icon(icon, size: 19, color: foreground),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Questions
// ---------------------------------------------------------------------------

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

  static const List<Color> _optionAccents = <Color>[
    Color(0xFF4338CA),
    Color(0xFF0D9488),
    Color(0xFFF59E0B),
    Color(0xFFEC4899),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

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
                _SegmentedProgress(
                  step: step,
                  total: total,
                  activeColor: colors.primary,
                  doneColor: colors.primary,
                  trackColor: colors.surfaceContainerHighest,
                  expand: true,
                ),
                const SizedBox(height: 26),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: <Color>[
                        colors.primaryContainer.withValues(alpha: 0.55),
                        colors.secondaryContainer.withValues(alpha: 0.35),
                      ],
                    ),
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
                for (int i = 0; i < question.options.length; i++) ...[
                  _AnswerTile(
                    option: question.options[i],
                    colors: colors,
                    accent: _optionAccents[i % _optionAccents.length],
                    selected: selectedValue == question.options[i].value,
                    enabled: !advancing,
                    onTap: () => onChoose(question.options[i].value),
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
    required this.accent,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final _QuestionOption option;
  final ColorScheme colors;
  final Color accent;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 160),
      decoration: BoxDecoration(
        color: selected ? accent.withValues(alpha: 0.08) : colors.surface,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(
          color: selected ? accent : colors.outlineVariant,
          width: selected ? 1.6 : 1,
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
                    color: accent.withValues(alpha: selected ? 0.16 : 0.10),
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
                    color: selected ? accent : Colors.transparent,
                    border: Border.all(
                      color: selected ? accent : colors.outline,
                      width: selected ? 0 : 1.5,
                    ),
                  ),
                  child: selected
                      ? const Icon(
                          Icons.check_rounded,
                          size: 17,
                          color: Colors.white,
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

// ---------------------------------------------------------------------------
// Completion & saving
// ---------------------------------------------------------------------------

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
        _SegmentedProgress(
          step: 6,
          total: 6,
          activeColor: colors.primary,
          doneColor: colors.primary,
          trackColor: colors.surfaceContainerHighest,
          expand: true,
        ),
        const SizedBox(height: 30),
        const Center(child: _CompletionMedallion()),
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
        SizedBox(
          width: double.infinity,
          child: _GradientButton(
            label: 'Save answers & continue',
            icon: Icons.check_circle_outline_rounded,
            gradient: _Palette.hero,
            foreground: Colors.white,
            onPressed: saving ? null : onSubmit,
            height: 56,
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

class _CompletionMedallion extends StatelessWidget {
  const _CompletionMedallion();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 120,
      height: 120,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFFD1FAE5), width: 2),
            ),
          ),
          Container(
            width: 92,
            height: 92,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              gradient: _Palette.mint,
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: Color(0x3322C55E),
                  blurRadius: 18,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: const Icon(
              Icons.task_alt_rounded,
              size: 46,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
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
            Container(
              width: 84,
              height: 84,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                gradient: _Palette.hero,
              ),
              child: const SizedBox(
                width: 34,
                height: 34,
                child: CircularProgressIndicator(
                  strokeWidth: 3,
                  color: Colors.white,
                ),
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
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.white),
          const SizedBox(width: 5),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
              fontSize: 11,
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
