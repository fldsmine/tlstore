import 'dart:async';
import 'dart:math' as math;
import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/auth_api.dart';

// ---------------------------------------------------------------------------
// Content — slides and questions are unchanged.
// ---------------------------------------------------------------------------

class _IntroSlide {
  const _IntroSlide({
    required this.badge,
    required this.title,
    required this.body,
    required this.icon,
    this.ctaLabel,
  });

  final String badge;
  final String title;
  final String body;
  final IconData icon;
  final String? ctaLabel;
}

const List<_IntroSlide> _slides = <_IntroSlide>[
  _IntroSlide(
    badge: 'Important',
    title: 'Before you start',
    body:
        'Many security measures are built into our surveys to ensure the quality '
        'of your profile. Please read this information carefully before beginning.',
    icon: Icons.warning_amber_rounded,
  ),
  _IntroSlide(
    badge: 'Quality pays',
    title: 'Honest answers, optimized earnings',
    body:
        'The quality of your answers is paramount. The more honest and sincere '
        'they are, the more quality surveys we offer you and the more attractive '
        'your earnings become.',
    icon: Icons.check_rounded,
  ),
  _IntroSlide(
    badge: 'To avoid',
    title: 'Poor answers have consequences',
    body:
        'Answering falsely, carelessly, or hastily can lower your earnings and '
        'the number of surveys offered to you. You may also be removed from a '
        'survey before completion.',
    icon: Icons.trending_down_rounded,
  ),
  _IntroSlide(
    badge: 'Ready to start?',
    title: 'Read, think, answer',
    body:
        'Take time to carefully read each question and answer it honestly and '
        'sincerely. Your seriousness is the key to an enriching and sustainably '
        'rewarding experience.',
    icon: Icons.edit_rounded,
    ctaLabel: 'Access paid surveys',
  ),
];

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

const List<_QualificationQuestion> _questions = <_QualificationQuestion>[
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

/// Short display labels for the answer recap (never shown as a question).
const Map<String, String> _recapLabels = <String, String>{
  'device': 'Device',
  'weekly_income': 'Weekly goal',
  'daily_time': 'Daily time',
  'tested_before': 'Tested before',
  'ready_today': 'Ready today',
  'motivation': 'Motivation',
};

// ---------------------------------------------------------------------------
// Questionnaire
// ---------------------------------------------------------------------------

/// The qualification questionnaire UI.
///
/// It renders as a rounded dialog card with its own gradient header instead of
/// a `Scaffold`/`AppBar` page, so it can float over whatever screen asked for
/// it. Prefer [showQuestionnaireDialog], which also blurs the parent page and
/// reports whether the answers were submitted.
class QuestionnaireScreen extends StatefulWidget {
  const QuestionnaireScreen({
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
  State<QuestionnaireScreen> createState() => _QuestionnaireScreenState();
}

class _QuestionnaireScreenState extends State<QuestionnaireScreen> {
  final Map<String, dynamic> _answers = <String, dynamic>{};

  int _introStep = 0;
  int _step = 0;

  /// 1 = moving forward (slides in from the right), -1 = going back.
  int _direction = 1;

  bool _saving = false;
  bool _advancing = false;

  /// True once the answers are saved: the dialog turns into the success page and
  /// closes itself.
  bool _saved = false;
  bool _finished = false;
  Timer? _closeTimer;
  String? _error;

  @override
  void dispose() {
    _closeTimer?.cancel();
    super.dispose();
  }

  bool get _isIntro => _introStep < _slides.length;
  bool get _isComplete => !_isIntro && _step >= _questions.length;
  int get _totalSteps => _slides.length + _questions.length;

  double get _progress {
    if (_isIntro) return _introStep / _totalSteps;
    if (_isComplete) return 1;
    return (_slides.length + _step) / _totalSteps;
  }

  String get _stageKey {
    if (_saving) return 'saving';
    if (_isIntro) return 'intro-$_introStep';
    if (_isComplete) return 'complete';
    return 'question-$_step';
  }

  String get _subtitle {
    if (_saving) return 'Saving your answers';
    if (_isIntro) {
      return 'Before you begin · ${_introStep + 1} of ${_slides.length}';
    }
    if (_isComplete) return 'Ready to submit';
    return 'Question ${_step + 1} of ${_questions.length}';
  }

  void _nextSlide() {
    if (_saving) return;
    HapticFeedback.selectionClick();
    setState(() {
      _direction = 1;
      _error = null;
      _introStep = math.min(_introStep + 1, _slides.length);
    });
  }

  void _skipIntro() {
    if (_saving) return;
    HapticFeedback.selectionClick();
    setState(() {
      _direction = 1;
      _error = null;
      _introStep = _slides.length;
    });
  }

  void _goBack() {
    if (_saving) return;
    if (_isIntro && _introStep == 0) {
      Navigator.of(context).pop(false);
      return;
    }
    HapticFeedback.lightImpact();
    setState(() {
      _direction = -1;
      _error = null;
      if (_isIntro) {
        _introStep = math.max(0, _introStep - 1);
      } else if (_isComplete) {
        _step = _questions.length - 1;
      } else if (_step > 0) {
        _step--;
      } else {
        _introStep = _slides.length - 1;
      }
    });
  }

  Future<void> _choose(_QualificationQuestion question, String value) async {
    if (_advancing || _saving) return;
    setState(() {
      _advancing = true;
      _answers[question.key] = value;
      _error = null;
    });

    await Future<void>.delayed(const Duration(milliseconds: 190));
    if (!mounted) return;

    setState(() {
      _direction = 1;
      _step++;
      _advancing = false;
    });
  }

  void _edit(int index) {
    if (_saving) return;
    HapticFeedback.selectionClick();
    setState(() {
      _direction = -1;
      _step = index;
      _error = null;
    });
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

      if (!mounted) return;
      HapticFeedback.mediumImpact();
      // Show the congratulation page, then close the dialog by itself and hand
      // `true` back to the page that asked for the answers.
      setState(() {
        _saving = false;
        _saved = true;
      });
      _closeTimer?.cancel();
      _closeTimer = Timer(const Duration(milliseconds: 4600), _finish);
    } on AuthException catch (error) {
      if (mounted) {
        HapticFeedback.heavyImpact();
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error =
              'Something went wrong while saving your answers. Please try again.';
        });
      }
    } finally {
      if (mounted && !_saved) setState(() => _saving = false);
    }
  }

  /// Closes the dialog with `true` — from the auto-close timer or a tap on the
  /// success page.
  void _finish() {
    if (_finished) return;
    _finished = true;
    _closeTimer?.cancel();
    _closeTimer = null;
    if (mounted) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final media = MediaQuery.of(context);
    final availableHeight =
        media.size.height - media.padding.top - media.padding.bottom - 48;

    final Widget content = _saved
        ? _SuccessStep(
            key: const ValueKey<String>('success'),
            onDone: _finish,
          )
        : Column(
            key: const ValueKey<String>('form'),
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              _DialogHeader(
                title: 'App tester qualification',
                subtitle: _subtitle,
                progress: _progress,
                canGoBack: !(_isIntro && _introStep == 0) && !_saving,
                onBack: _goBack,
                onClose: _saving
                    ? null
                    : () => Navigator.of(context).pop(false),
              ),
              Flexible(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: <Color>[
                        colors.primaryContainer.withValues(alpha: 0.28),
                        colors.surface,
                      ],
                      stops: const <double>[0.0, 0.4],
                    ),
                  ),
                  child: _stage(),
                ),
              ),
            ],
          );

    return PopScope(
      // Never let a barrier tap throw away answers that are mid-flight, and let
      // the success page finish and report `true`.
      canPop: !_saving && !_saved,
      child: Dialog(
        backgroundColor: Colors.transparent,
        elevation: 0,
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxWidth: 512,
            maxHeight: math.max(availableHeight, 280.0),
          ),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: colors.surface,
              borderRadius: BorderRadius.circular(28),
              border: Border.all(
                color: colors.outlineVariant.withValues(alpha: 0.7),
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: colors.shadow.withValues(alpha: 0.26),
                  blurRadius: 46,
                  offset: const Offset(0, 22),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(28),
              child: AnimatedSize(
                duration: const Duration(milliseconds: 240),
                curve: Curves.easeOutCubic,
                alignment: Alignment.topCenter,
                clipBehavior: Clip.none,
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 260),
                  switchInCurve: Curves.easeOutCubic,
                  switchOutCurve: Curves.easeInCubic,
                  child: content,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _stage() {
    final Widget view;
    if (_saving) {
      view = const _SavingStep();
    } else if (_isIntro) {
      view = _IntroStep(
        slide: _slides[_introStep],
        index: _introStep,
        total: _slides.length,
        onNext: _nextSlide,
        onBack: _goBack,
        onSkip: _skipIntro,
      );
    } else if (_isComplete) {
      view = _CompletionStep(
        user: widget.user,
        answers: _answers,
        error: _error,
        saving: _saving,
        onSubmit: _submit,
        onEdit: _edit,
      );
    } else {
      final question = _questions[_step];
      view = _QuestionStep(
        question: question,
        step: _step,
        total: _questions.length,
        selectedValue: _answers[question.key]?.toString(),
        advancing: _advancing,
        error: _error,
        onChoose: (value) => _choose(question, value),
      );
    }

    return AnimatedSize(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
      alignment: Alignment.topCenter,
      // Let button shadows breathe; the card clips at its own radius.
      clipBehavior: Clip.none,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 280),
        switchInCurve: Curves.easeOutCubic,
        switchOutCurve: Curves.easeInCubic,
        layoutBuilder: (currentChild, previousChildren) => Stack(
          alignment: Alignment.topCenter,
          children: <Widget>[
            ...previousChildren,
            if (currentChild != null) currentChild,
          ],
        ),
        transitionBuilder: _transition,
        child: KeyedSubtree(
          key: ValueKey<String>(_stageKey),
          child: view,
        ),
      ),
    );
  }

  Widget _transition(Widget child, Animation<double> animation) {
    final incoming = child.key == ValueKey<String>(_stageKey);
    final sign = (incoming ? _direction : -_direction).toDouble();
    final curved = CurvedAnimation(
      parent: animation,
      curve: Curves.easeOutCubic,
      reverseCurve: Curves.easeInCubic,
    );

    return FadeTransition(
      opacity: curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: Offset(0.06 * sign, 0),
          end: Offset.zero,
        ).animate(curved),
        child: child,
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Dialog presentation
// ---------------------------------------------------------------------------

/// Shows the qualification questionnaire as a dialog over the current page.
///
/// The page underneath is dimmed and blurred, and the questionnaire card floats
/// on top of it, so users answer the slides and questions without leaving the
/// screen that asked for them.
///
/// Returns `true` when the answers were submitted, `false` when the user closed
/// the dialog, and `null` when it was dismissed by tapping outside.
///
/// ```dart
/// final answered = await showQuestionnaireDialog(
///   context,
///   api: widget.api,
///   token: widget.token,
///   groupKey: groupKey,
///   user: widget.user,
/// );
/// if (answered == true) { ... }
/// ```
Future<bool?> showQuestionnaireDialog(
  BuildContext context, {
  required AuthApi api,
  required String token,
  required String groupKey,
  Map<String, dynamic> user = const <String, dynamic>{},
}) {
  return showGeneralDialog<bool>(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'App tester qualification',
    barrierColor: Colors.black.withValues(alpha: 0.36),
    transitionDuration: const Duration(milliseconds: 280),
    pageBuilder: (dialogContext, animation, secondaryAnimation) {
      return _QuestionnaireDialogHost(
        api: api,
        token: token,
        groupKey: groupKey,
        user: user,
      );
    },
    transitionBuilder: (context, animation, secondaryAnimation, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: Curves.easeOutCubic,
        reverseCurve: Curves.easeInCubic,
      );
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(
          scale: Tween<double>(begin: 0.95, end: 1).animate(curved),
          child: child,
        ),
      );
    },
  );
}

/// Blurs the parent page behind the dialog and hosts the questionnaire card.
class _QuestionnaireDialogHost extends StatelessWidget {
  const _QuestionnaireDialogHost({
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
  Widget build(BuildContext context) {
    return Stack(
      children: <Widget>[
        Positioned.fill(
          child: IgnorePointer(
            child: BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
              child: const ColoredBox(color: Colors.transparent),
            ),
          ),
        ),
        SafeArea(
          child: QuestionnaireScreen(
            api: api,
            token: token,
            groupKey: groupKey,
            user: user,
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Header
// ---------------------------------------------------------------------------

class _DialogHeader extends StatelessWidget {
  const _DialogHeader({
    required this.title,
    required this.subtitle,
    required this.progress,
    required this.canGoBack,
    required this.onBack,
    required this.onClose,
  });

  final String title;
  final String subtitle;
  final double progress;
  final bool canGoBack;
  final VoidCallback onBack;
  final VoidCallback? onClose;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final dimmedAction = colors.onPrimary.withValues(alpha: 0.35);

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[colors.primary, colors.secondary],
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 8, 6, 10),
            child: Row(
              children: <Widget>[
                IconButton(
                  tooltip: 'Back',
                  onPressed: canGoBack ? onBack : null,
                  icon: const Icon(Icons.arrow_back_rounded, size: 20),
                  color: colors.onPrimary,
                  disabledColor: dimmedAction,
                  visualDensity: VisualDensity.compact,
                ),
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.2,
                          color: colors.onPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      AnimatedSwitcher(
                        duration: const Duration(milliseconds: 200),
                        child: Text(
                          subtitle,
                          key: ValueKey<String>(subtitle),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: colors.onPrimary.withValues(alpha: 0.82),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                _Pill(
                  icon: Icons.lock_outline_rounded,
                  label: 'Private',
                  color: colors.onPrimary,
                ),
                IconButton(
                  tooltip: 'Close',
                  onPressed: onClose,
                  icon: const Icon(Icons.close_rounded, size: 20),
                  color: colors.onPrimary,
                  disabledColor: dimmedAction,
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
          ),
          _HeaderProgress(value: progress),
        ],
      ),
    );
  }
}

class _HeaderProgress extends StatelessWidget {
  const _HeaderProgress({required this.value});

  final double value;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(
        begin: 0,
        end: value < 0
            ? 0.0
            : value > 1
            ? 1.0
            : value,
      ),
      duration: const Duration(milliseconds: 380),
      curve: Curves.easeOutCubic,
      builder: (context, animated, _) => SizedBox(
        height: 3,
        child: Stack(
          fit: StackFit.expand,
          children: <Widget>[
            ColoredBox(color: colors.onPrimary.withValues(alpha: 0.18)),
            Align(
              alignment: Alignment.centerLeft,
              child: FractionallySizedBox(
                widthFactor: animated,
                child: ColoredBox(
                  color: colors.onPrimary.withValues(alpha: 0.92),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 12, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w700,
              fontSize: 10.5,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Intro
// ---------------------------------------------------------------------------

class _IntroStep extends StatelessWidget {
  const _IntroStep({
    required this.slide,
    required this.index,
    required this.total,
    required this.onNext,
    required this.onBack,
    required this.onSkip,
  });

  final _IntroSlide slide;
  final int index;
  final int total;
  final VoidCallback onNext;
  final VoidCallback onBack;
  final VoidCallback onSkip;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return GestureDetector(
      // Swiping browses the intro slides; the buttons still work.
      onHorizontalDragEnd: (details) {
        final velocity = details.primaryVelocity ?? 0;
        if (velocity < -160) {
          onNext();
        } else if (velocity > 160 && index > 0) {
          onBack();
        }
      },
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(22, 16, 22, 26),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                _StepDots(index: index, total: total),
                const Spacer(),
                TextButton(
                  onPressed: onSkip,
                  style: TextButton.styleFrom(
                    foregroundColor: colors.onSurfaceVariant,
                    visualDensity: VisualDensity.compact,
                  ),
                  child: const Text(
                    'Skip intro',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12.5,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            Center(child: _SlideMedallion(slide: slide, step: index)),
            const SizedBox(height: 20),
            _FadeUp(
              delay: const Duration(milliseconds: 80),
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: colors.primary.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(30),
                    border: Border.all(
                      color: colors.primary.withValues(alpha: 0.18),
                    ),
                  ),
                  child: Text(
                    slide.badge.toUpperCase(),
                    style: TextStyle(
                      fontSize: 9.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.9,
                      color: colors.primary,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            _FadeUp(
              delay: const Duration(milliseconds: 140),
              child: Text(
                slide.title,
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.5,
                  height: 1.18,
                ),
              ),
            ),
            const SizedBox(height: 10),
            _FadeUp(
              delay: const Duration(milliseconds: 200),
              child: Text(
                slide.body,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                  height: 1.62,
                ),
              ),
            ),
            const SizedBox(height: 22),
            const _Hairline(),
            const SizedBox(height: 16),
            Row(
              children: <Widget>[
                if (index > 0)
                  TextButton.icon(
                    onPressed: onBack,
                    icon: const Icon(Icons.arrow_back_rounded, size: 16),
                    label: const Text('Back'),
                    style: TextButton.styleFrom(
                      foregroundColor: colors.onSurfaceVariant,
                      visualDensity: VisualDensity.compact,
                    ),
                  ),
                const SizedBox(width: 10),
                Expanded(
                  child: _PrimaryButton(
                    label: slide.ctaLabel ?? 'Continue',
                    icon: Icons.arrow_forward_rounded,
                    onPressed: onNext,
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

class _SlideMedallion extends StatefulWidget {
  const _SlideMedallion({required this.slide, required this.step});

  final _IntroSlide slide;
  final int step;

  @override
  State<_SlideMedallion> createState() => _SlideMedallionState();
}

/// Animated medallion from the original design: two orbit rings turning in
/// opposite directions around the slide icon, with a gentle float.
class _SlideMedallionState extends State<_SlideMedallion>
    with TickerProviderStateMixin {
  late final AnimationController _entry = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 520),
  )..forward();

  late final AnimationController _orbit = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 9),
  )..repeat();

  late final AnimationController _float = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 3200),
  )..repeat(reverse: true);

  @override
  void didUpdateWidget(covariant _SlideMedallion oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.step != widget.step) {
      _entry.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _entry.dispose();
    _orbit.dispose();
    _float.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final accents = <Color>[
      colors.primary,
      colors.secondary,
      colors.tertiary,
      colors.error,
    ];
    final accent = accents[widget.step % accents.length];

    return FadeTransition(
      opacity: _entry,
      child: AnimatedBuilder(
        animation: _float,
        builder: (context, child) => Transform.translate(
          offset: Offset(0, (_float.value - 0.5) * 8),
          child: child,
        ),
        child: ScaleTransition(
          scale: Tween<double>(begin: 0.9, end: 1).animate(
            CurvedAnimation(parent: _entry, curve: Curves.easeOutBack),
          ),
          child: SizedBox(
            width: 172,
            height: 150,
            child: Stack(
              alignment: Alignment.center,
              clipBehavior: Clip.none,
              children: <Widget>[
                Container(
                  width: 118,
                  height: 118,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: accent.withValues(alpha: 0.08),
                    border: Border.all(
                      color: colors.outlineVariant.withValues(alpha: 0.55),
                      width: 1.4,
                    ),
                  ),
                ),
                AnimatedBuilder(
                  animation: _orbit,
                  child: _OrbitRing(
                    width: 148,
                    height: 72,
                    color: accent.withValues(alpha: 0.75),
                    dotColor: accent,
                    dotAtTop: true,
                  ),
                  builder: (context, child) => Transform.rotate(
                    angle: _orbit.value * 2 * math.pi,
                    child: child,
                  ),
                ),
                AnimatedBuilder(
                  animation: _orbit,
                  child: _OrbitRing(
                    width: 162,
                    height: 86,
                    color: colors.secondary.withValues(alpha: 0.5),
                    dotColor: colors.secondary,
                    dotAtTop: false,
                  ),
                  builder: (context, child) => Transform.rotate(
                    angle: -_orbit.value * 2 * math.pi,
                    child: child,
                  ),
                ),
                Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: <Color>[
                        colors.primaryContainer,
                        accent.withValues(alpha: 0.82),
                      ],
                    ),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: accent.withValues(alpha: 0.28),
                        blurRadius: 20,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Icon(
                    widget.slide.icon,
                    size: 42,
                    color: colors.onPrimaryContainer,
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

/// Capsule ring with a travelling dot, the building block of the medallion.
class _OrbitRing extends StatelessWidget {
  const _OrbitRing({
    required this.width,
    required this.height,
    required this.color,
    required this.dotColor,
    required this.dotAtTop,
  });

  final double width;
  final double height;
  final Color color;
  final Color dotColor;
  final bool dotAtTop;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(height / 2),
        border: Border.all(color: color, width: 1.8),
      ),
      child: Align(
        alignment: dotAtTop ? Alignment.topCenter : Alignment.bottomCenter,
        child: Container(
          width: 9,
          height: 9,
          decoration: BoxDecoration(
            color: dotColor,
            shape: BoxShape.circle,
            boxShadow: <BoxShadow>[
              BoxShadow(color: dotColor.withValues(alpha: 0.4), blurRadius: 8),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Question
// ---------------------------------------------------------------------------

class _QuestionStep extends StatelessWidget {
  const _QuestionStep({
    required this.question,
    required this.step,
    required this.total,
    required this.selectedValue,
    required this.advancing,
    required this.onChoose,
    this.error,
  });

  final _QualificationQuestion question;
  final int step;
  final int total;
  final String? selectedValue;
  final bool advancing;
  final ValueChanged<String> onChoose;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final accents = <Color>[
      colors.primary,
      colors.secondary,
      colors.tertiary,
      colors.primary,
    ];

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(22, 16, 22, 26),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Text(
                'QUESTION ${step + 1}',
                style: theme.textTheme.labelMedium?.copyWith(
                  color: colors.primary,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.1,
                ),
              ),
              const Spacer(),
              Icon(
                Icons.verified_user_outlined,
                size: 14,
                color: colors.onSurfaceVariant,
              ),
              const SizedBox(width: 5),
              Text(
                'Private & secure',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _SegmentedBar(step: step, total: total),
          const SizedBox(height: 24),
          Text(
            question.title,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w900,
              height: 1.22,
              letterSpacing: -0.45,
            ),
          ),
          if (question.subtitle.isNotEmpty) ...<Widget>[
            const SizedBox(height: 8),
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
            children: <Widget>[
              Text(
                'CHOOSE ONE',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.9,
                ),
              ),
              const Spacer(),
              Icon(
                Icons.touch_app_outlined,
                size: 14,
                color: colors.onSurfaceVariant,
              ),
              const SizedBox(width: 4),
              Text(
                'Tap to choose',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var index = 0; index < question.options.length; index++) ...[
            _FadeUp(
              delay: Duration(milliseconds: 45 * index),
              child: _AnswerTile(
                option: question.options[index],
                accent: accents[index % accents.length],
                selected: selectedValue == question.options[index].value,
                enabled: !advancing,
                onTap: () => onChoose(question.options[index].value),
              ),
            ),
            const SizedBox(height: 10),
          ],
          if (advancing) ...<Widget>[
            const SizedBox(height: 2),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: colors.primary,
                  ),
                ),
                const SizedBox(width: 9),
                Text(
                  'Moving to the next question…',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ],
          if (error != null) ...<Widget>[
            const SizedBox(height: 14),
            _InlineError(message: error!),
          ],
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(
                Icons.tips_and_updates_outlined,
                size: 15,
                color: colors.onSurfaceVariant,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  'Your answers help us match you with relevant testing '
                  'opportunities.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSurfaceVariant,
                    height: 1.45,
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

class _SegmentedBar extends StatelessWidget {
  const _SegmentedBar({required this.step, required this.total});

  final int step;
  final int total;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        for (var index = 0; index < total; index++) ...<Widget>[
          if (index > 0) const SizedBox(width: 5),
          Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 260),
              curve: Curves.easeOutCubic,
              height: 4,
              decoration: BoxDecoration(
                color: index <= step
                    ? colors.primary
                    : colors.outlineVariant.withValues(alpha: 0.75),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _StepDots extends StatelessWidget {
  const _StepDots({required this.index, required this.total});

  final int index;
  final int total;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        for (var dot = 0; dot < total; dot++) ...<Widget>[
          if (dot > 0) const SizedBox(width: 6),
          AnimatedContainer(
            duration: const Duration(milliseconds: 240),
            curve: Curves.easeOutCubic,
            height: 6,
            width: dot == index ? 20 : 6,
            decoration: BoxDecoration(
              color: dot <= index
                  ? colors.primary
                  : colors.outlineVariant.withValues(alpha: 0.8),
              borderRadius: BorderRadius.circular(3),
            ),
          ),
        ],
      ],
    );
  }
}

/// Fades and lifts its child into place once, after an optional [delay].
class _FadeUp extends StatefulWidget {
  const _FadeUp({required this.child, this.delay = Duration.zero});

  final Widget child;
  final Duration delay;

  @override
  State<_FadeUp> createState() => _FadeUpState();
}

class _FadeUpState extends State<_FadeUp> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 320),
  );

  late final Animation<double> _curved = CurvedAnimation(
    parent: _controller,
    curve: Curves.easeOutCubic,
  );

  @override
  void initState() {
    super.initState();
    if (widget.delay == Duration.zero) {
      _controller.forward();
    } else {
      Future<void>.delayed(widget.delay, () {
        if (mounted) _controller.forward();
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.08),
          end: Offset.zero,
        ).animate(_curved),
        child: widget.child,
      ),
    );
  }
}

class _AnswerTile extends StatelessWidget {
  const _AnswerTile({
    required this.option,
    required this.accent,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final _QuestionOption option;
  final Color accent;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final radius = BorderRadius.circular(18);
    final restingBorder = colors.outlineVariant.withValues(alpha: 0.8);

    return Semantics(
      button: true,
      selected: selected,
      label: option.label,
      child: TweenAnimationBuilder<double>(
        tween: Tween<double>(begin: 0, end: selected ? 1 : 0),
        duration: const Duration(milliseconds: 190),
        curve: Curves.easeOut,
        builder: (context, t, _) {
          return AnimatedContainer(
            duration: const Duration(milliseconds: 190),
            curve: Curves.easeOut,
            decoration: BoxDecoration(
              borderRadius: radius,
              boxShadow: t <= 0
                  ? const <BoxShadow>[]
                  : <BoxShadow>[
                      BoxShadow(
                        color: accent.withValues(alpha: 0.16 * t),
                        blurRadius: 16 * t,
                        offset: Offset(0, 7 * t),
                      ),
                    ],
            ),
            // The fill sits on the Material so the ink ripple stays visible.
            child: Material(
              color: Color.lerp(
                colors.surface,
                accent.withValues(alpha: 0.09),
                t,
              )!,
              borderRadius: radius,
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: enabled
                    ? () {
                        HapticFeedback.selectionClick();
                        onTap();
                      }
                    : null,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 11,
                  ),
                  decoration: BoxDecoration(
                    borderRadius: radius,
                    border: Border.all(
                      color: Color.lerp(restingBorder, accent, t)!,
                      width: 1 + (0.5 * t),
                    ),
                  ),
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 44,
                        height: 44,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: <Color>[
                              accent.withValues(alpha: 0.20),
                              accent.withValues(alpha: 0.06),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Text(
                          option.emoji,
                          style: const TextStyle(fontSize: 20),
                        ),
                      ),
                      const SizedBox(width: 13),
                      Expanded(
                        child: Text(
                          option.label,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: colors.onSurface,
                          ),
                        ),
                      ),
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 190),
                        width: 22,
                        height: 22,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: selected ? accent : Colors.transparent,
                          border: Border.all(
                            color: selected
                                ? accent
                                : colors.outlineVariant.withValues(alpha: 0.9),
                            width: 1.6,
                          ),
                        ),
                        child: selected
                            ? Icon(
                                Icons.check_rounded,
                                size: 14,
                                color: colors.onPrimary,
                              )
                            : null,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Completion
// ---------------------------------------------------------------------------

class _CompletionStep extends StatelessWidget {
  const _CompletionStep({
    required this.user,
    required this.answers,
    required this.saving,
    required this.onSubmit,
    required this.onEdit,
    this.error,
  });

  final Map<String, dynamic> user;
  final Map<String, dynamic> answers;
  final bool saving;
  final VoidCallback onSubmit;
  final ValueChanged<int> onEdit;
  final String? error;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final name = '${user['full_name'] ?? user['name'] ?? ''}'.trim();
    final email = '${user['email'] ?? ''}'.trim();

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(22, 18, 22, 26),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
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
                '${_questions.length}/${_questions.length}',
                style: theme.textTheme.labelMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Center(child: _CompletionMedallion()),
          const SizedBox(height: 18),
          Text(
            "You're all set!",
            textAlign: TextAlign.center,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Review your answers below, then submit them to unlock your '
            'qualification.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 22),
          Text(
            'YOUR ANSWERS',
            style: theme.textTheme.labelSmall?.copyWith(
              color: colors.onSurfaceVariant,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.9,
            ),
          ),
          const SizedBox(height: 10),
          Container(
            decoration: BoxDecoration(
              color: colors.surfaceContainerLow,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: colors.outlineVariant.withValues(alpha: 0.8),
              ),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: <Widget>[
                for (var index = 0; index < _questions.length; index++)
                  if (_optionFor(_questions[index]) != null) ...<Widget>[
                    if (index > 0)
                      Divider(
                        height: 1,
                        indent: 14,
                        endIndent: 14,
                        color: colors.outlineVariant.withValues(alpha: 0.6),
                      ),
                    _RecapRow(
                      emoji: _optionFor(_questions[index])!.emoji,
                      label:
                          _recapLabels[_questions[index].key] ??
                          _questions[index].key,
                      value: _optionFor(_questions[index])!.label,
                      onTap: saving ? null : () => onEdit(index),
                    ),
                  ],
              ],
            ),
          ),
          if (name.isNotEmpty || email.isNotEmpty) ...<Widget>[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: colors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: colors.outlineVariant.withValues(alpha: 0.8),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Icon(
                        Icons.account_circle_outlined,
                        size: 19,
                        color: colors.primary,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'Account details',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _DetailRow(
                    icon: Icons.person_outline_rounded,
                    label: 'First name',
                    value: _firstName(name),
                  ),
                  if (email.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 12),
                    Divider(
                      height: 1,
                      color: colors.outlineVariant.withValues(alpha: 0.6),
                    ),
                    const SizedBox(height: 12),
                    _DetailRow(
                      icon: Icons.mail_outline_rounded,
                      label: 'Email address',
                      value: email,
                    ),
                  ],
                ],
              ),
            ),
          ],
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: colors.secondaryContainer.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Icon(
                  Icons.shield_outlined,
                  color: colors.onSecondaryContainer,
                  size: 18,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Your responses are saved to your account and used for '
                    'qualification only.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: colors.onSecondaryContainer,
                      height: 1.45,
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (error != null) ...<Widget>[
            const SizedBox(height: 14),
            _InlineError(message: error!),
          ],
          const SizedBox(height: 20),
          _PrimaryButton(
            label: 'Save answers & continue',
            icon: Icons.check_circle_outline_rounded,
            height: 54,
            pulseIcon: true,
            loading: saving,
            onPressed: saving ? null : onSubmit,
          ),
          const SizedBox(height: 10),
          Center(
            child: Text(
              'Tap any answer above to change it.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }

  _QuestionOption? _optionFor(_QualificationQuestion question) {
    final value = answers[question.key]?.toString();
    if (value == null) return null;
    for (final option in question.options) {
      if (option.value == value) return option;
    }
    return null;
  }

  String _firstName(String fullName) {
    if (fullName.isEmpty) return '—';
    return fullName.split(RegExp(r'\s+')).first;
  }
}

class _CompletionMedallion extends StatefulWidget {
  const _CompletionMedallion();

  @override
  State<_CompletionMedallion> createState() => _CompletionMedallionState();
}

class _CompletionMedallionState extends State<_CompletionMedallion>
    with TickerProviderStateMixin {
  late final AnimationController _entry = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 620),
  )..forward();

  late final AnimationController _float = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 3000),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _entry.dispose();
    _float.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final curved = CurvedAnimation(
      parent: _entry,
      curve: Curves.easeOutBack,
    );
    final haloScale = Tween<double>(begin: 1, end: 1.08).animate(
      CurvedAnimation(parent: _float, curve: Curves.easeInOut),
    );

    return SizedBox(
      width: 132,
      height: 132,
      child: AnimatedBuilder(
        animation: _float,
        builder: (context, child) => Transform.translate(
          offset: Offset(0, (_float.value - 0.5) * -6),
          child: child,
        ),
        child: Stack(
          alignment: Alignment.center,
          children: <Widget>[
            ScaleTransition(
              scale: Tween<double>(begin: 0.6, end: 1).animate(curved),
              child: ScaleTransition(
                scale: haloScale,
                child: Container(
                  width: 132,
                  height: 132,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: <Color>[
                        colors.primary.withValues(alpha: 0.20),
                        colors.primary.withValues(alpha: 0.02),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            ScaleTransition(
              scale: Tween<double>(begin: 0.7, end: 1).animate(curved),
              child: _Medallion(icon: Icons.celebration_rounded, size: 96),
            ),
          ],
        ),
      ),
    );
  }
}

class _RecapRow extends StatelessWidget {
  const _RecapRow({
    required this.emoji,
    required this.label,
    required this.value,
    this.onTap,
  });

  final String emoji;
  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            children: <Widget>[
              Text(emoji, style: const TextStyle(fontSize: 16)),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      label.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 9.5,
                        letterSpacing: 0.6,
                        fontWeight: FontWeight.w800,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: colors.onSurface,
                      ),
                    ),
                  ],
                ),
              ),
              if (onTap != null)
                Padding(
                  padding: const EdgeInsets.only(left: 8),
                  child: Icon(
                    Icons.edit_outlined,
                    size: 15,
                    color: colors.onSurfaceVariant,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Row(
      children: <Widget>[
        Icon(icon, size: 16, color: colors.onSurfaceVariant),
        const SizedBox(width: 9),
        Text(
          label,
          style: theme.textTheme.labelSmall?.copyWith(
            color: colors.onSurfaceVariant,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: colors.onSurface,
            ),
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Saving
// ---------------------------------------------------------------------------

class _SavingStep extends StatefulWidget {
  const _SavingStep();

  @override
  State<_SavingStep> createState() => _SavingStepState();
}

class _SavingStepState extends State<_SavingStep>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 46),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          ScaleTransition(
            scale: Tween<double>(begin: 0.96, end: 1.04).animate(
              CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
            ),
            child: Stack(
              alignment: Alignment.center,
              children: <Widget>[
                _Medallion(icon: Icons.lock_outline_rounded, size: 84),
                SizedBox(
                  width: 106,
                  height: 106,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: colors.primary.withValues(alpha: 0.55),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 26),
          Text(
            'Saving your answers',
            textAlign: TextAlign.center,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Please wait while we securely save your responses.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Success page
// ---------------------------------------------------------------------------

/// Congratulation page shown once the answers are saved.
///
/// It fills the dialog, rains ribbons, and closes itself — the parent pops with
/// `true` after a short delay. Tapping it closes immediately.
class _SuccessStep extends StatefulWidget {
  const _SuccessStep({super.key, required this.onDone});

  final VoidCallback onDone;

  @override
  State<_SuccessStep> createState() => _SuccessStepState();
}

class _SuccessStepState extends State<_SuccessStep>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pop = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 780),
  )..forward();

  @override
  void dispose() {
    _pop.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final curved = CurvedAnimation(parent: _pop, curve: Curves.easeOutBack);

    return GestureDetector(
      onTap: widget.onDone,
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        height: 420,
        child: DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[colors.primary, colors.secondary],
            ),
          ),
          child: Stack(
            children: <Widget>[
              const Positioned.fill(child: _RibbonRain()),
              Positioned.fill(
                child: Center(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 26,
                      vertical: 24,
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Stack(
                          alignment: Alignment.center,
                          children: <Widget>[
                            _SuccessBurst(
                              animation: _pop,
                              color: colors.onPrimary,
                            ),
                            ScaleTransition(
                              scale: Tween<double>(
                                begin: 0.6,
                                end: 1,
                              ).animate(curved),
                              child: Container(
                                width: 104,
                                height: 104,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: colors.onPrimary.withValues(
                                    alpha: 0.18,
                                  ),
                                  border: Border.all(
                                    color: colors.onPrimary.withValues(
                                      alpha: 0.45,
                                    ),
                                    width: 2,
                                  ),
                                  boxShadow: <BoxShadow>[
                                    BoxShadow(
                                      color: colors.onPrimary.withValues(
                                        alpha: 0.24,
                                      ),
                                      blurRadius: 30,
                                    ),
                                  ],
                                ),
                                child: Icon(
                                  Icons.check_rounded,
                                  size: 56,
                                  color: colors.onPrimary,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 22),
                        _FadeUp(
                          delay: const Duration(milliseconds: 120),
                          child: Text(
                            'Congratulations!',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.headlineMedium?.copyWith(
                              fontWeight: FontWeight.w900,
                              letterSpacing: -0.6,
                              color: colors.onPrimary,
                            ),
                          ),
                        ),
                        const SizedBox(height: 10),
                        _FadeUp(
                          delay: const Duration(milliseconds: 200),
                          child: Text(
                            'Your answers are saved. We can now match you '
                            'with paid testing work.',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.bodyMedium?.copyWith(
                              height: 1.55,
                              color: colors.onPrimary.withValues(alpha: 0.9),
                            ),
                          ),
                        ),
                        const SizedBox(height: 26),
                        _AutoCloseBar(color: colors.onPrimary),
                        const SizedBox(height: 10),
                        Text(
                          'Returning to your page…',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: colors.onPrimary.withValues(alpha: 0.75),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Two rings that expand and fade out once, right behind the check mark.
class _SuccessBurst extends StatelessWidget {
  const _SuccessBurst({required this.animation, required this.color});

  final Animation<double> animation;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, _) {
        return SizedBox(
          width: 104,
          height: 104,
          child: Stack(
            alignment: Alignment.center,
            children: <Widget>[
              for (final offset in <double>[0.0, 0.25])
                _ring(_progress(offset)),
            ],
          ),
        );
      },
    );
  }

  double _progress(double offset) {
    final span = 1 - offset;
    final value = span <= 0 ? 1.0 : (animation.value - offset) / span;
    return math.min(1.0, math.max(0.0, value));
  }

  Widget _ring(double t) {
    return Transform.scale(
      scale: 1 + (1.1 * t),
      child: Container(
        width: 104,
        height: 104,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: color.withValues(alpha: 0.55 * (1 - t)),
            width: 2,
          ),
        ),
      ),
    );
  }
}

/// Thin progress line that fills over the auto-close delay.
class _AutoCloseBar extends StatelessWidget {
  const _AutoCloseBar({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 168,
      height: 4,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(2),
        child: Stack(
          fit: StackFit.expand,
          children: <Widget>[
            ColoredBox(color: color.withValues(alpha: 0.22)),
            TweenAnimationBuilder<double>(
              tween: Tween<double>(begin: 0, end: 1),
              duration: const Duration(milliseconds: 2600),
              curve: Curves.linear,
              builder: (context, value, _) => Align(
                alignment: Alignment.centerLeft,
                child: FractionallySizedBox(
                  widthFactor: value,
                  child: ColoredBox(color: color.withValues(alpha: 0.92)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Ribbons drifting down behind the congratulation copy.
class _RibbonRain extends StatefulWidget {
  const _RibbonRain();

  @override
  State<_RibbonRain> createState() => _RibbonRainState();
}

class _RibbonRainState extends State<_RibbonRain>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2600),
  )..repeat();

  late final List<_RibbonPiece> _pieces = _buildPieces();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RepaintBoundary(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) => CustomPaint(
          painter: _RibbonPainter(
            progress: _controller.value,
            pieces: _pieces,
          ),
          child: const SizedBox.expand(),
        ),
      ),
    );
  }

  static List<_RibbonPiece> _buildPieces() {
    const palette = <Color>[
      Color(0xFFFFFFFF),
      Color(0xFFFDE68A),
      Color(0xFFA7F3D0),
      Color(0xFFBFDBFE),
      Color(0xFFFBCFE8),
      Color(0xFFFDE047),
    ];
    final random = math.Random(20260115);
    return List<_RibbonPiece>.generate(28, (index) {
      return _RibbonPiece(
        x: random.nextDouble(),
        phase: random.nextDouble(),
        width: 4 + random.nextDouble() * 5,
        height: 9 + random.nextDouble() * 13,
        sway: 8 + random.nextDouble() * 26,
        spin: (random.nextDouble() - 0.5) * 2.2,
        tilt: random.nextDouble() * math.pi,
        color: palette[index % palette.length],
      );
    });
  }
}

class _RibbonPiece {
  const _RibbonPiece({
    required this.x,
    required this.phase,
    required this.width,
    required this.height,
    required this.sway,
    required this.spin,
    required this.tilt,
    required this.color,
  });

  final double x;
  final double phase;
  final double width;
  final double height;
  final double sway;
  final double spin;
  final double tilt;
  final Color color;
}

class _RibbonPainter extends CustomPainter {
  _RibbonPainter({required this.progress, required this.pieces});

  final double progress;
  final List<_RibbonPiece> pieces;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint();
    for (final piece in pieces) {
      final fall = (progress + piece.phase) % 1.0;
      final y = -26 + fall * (size.height + 52);
      final swayPhase = fall * math.pi * 2 + piece.phase * math.pi * 2;
      final x = piece.x * size.width + math.sin(swayPhase) * piece.sway;
      final angle = piece.tilt + fall * piece.spin * math.pi;
      final alpha = math.min(
        math.min(1.0, fall / 0.08),
        math.min(1.0, (1 - fall) / 0.12),
      );
      if (alpha <= 0.01) continue;

      paint.color = piece.color.withValues(alpha: 0.92 * alpha);
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(angle);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromCenter(
            center: Offset.zero,
            width: piece.width,
            height: piece.height,
          ),
          Radius.circular(piece.width / 2),
        ),
        paint,
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant _RibbonPainter oldDelegate) =>
      oldDelegate.progress != progress || oldDelegate.pieces != pieces;
}

// ---------------------------------------------------------------------------
// Shared pieces
// ---------------------------------------------------------------------------

class _Medallion extends StatelessWidget {
  const _Medallion({required this.icon, required this.size});

  final IconData icon;
  final double size;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.3),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[colors.primary, colors.secondary],
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: colors.primary.withValues(alpha: 0.30),
            blurRadius: size * 0.32,
            offset: Offset(0, size * 0.12),
          ),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: <Widget>[
          Container(
            width: size * 0.78,
            height: size * 0.78,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(size * 0.24),
              border: Border.all(
                color: colors.onPrimary.withValues(alpha: 0.22),
                width: 1.2,
              ),
            ),
          ),
          Icon(icon, size: size * 0.4, color: colors.onPrimary),
        ],
      ),
    );
  }
}

class _PrimaryButton extends StatelessWidget {
  const _PrimaryButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.height = 48,
    this.loading = false,
    this.pulseIcon = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final double height;
  final bool loading;

  /// Gently pulses the leading icon to draw the eye to the call to action.
  final bool pulseIcon;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final enabled = onPressed != null && !loading;
    final radius = BorderRadius.circular(16);
    // Local binding so the null check promotes (public fields do not promote).
    final IconData? leadingIcon = icon;

    return Opacity(
      opacity: enabled || loading ? 1 : 0.55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[colors.primary, colors.secondary],
          ),
          borderRadius: radius,
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: colors.primary.withValues(alpha: 0.28),
              blurRadius: 16,
              offset: const Offset(0, 7),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: radius,
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: enabled ? onPressed : null,
            child: SizedBox(
              height: height,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    if (loading)
                      SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: colors.onPrimary,
                        ),
                      )
                    else if (leadingIcon != null)
                      pulseIcon
                          ? _PulsingIcon(
                              icon: leadingIcon,
                              color: colors.onPrimary,
                            )
                          : Icon(
                              leadingIcon,
                              size: 18,
                              color: colors.onPrimary,
                            ),
                    if (loading || leadingIcon != null)
                      const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.1,
                          color: colors.onPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Small looping pulse for a button's leading icon.
class _PulsingIcon extends StatefulWidget {
  const _PulsingIcon({required this.icon, required this.color});

  final IconData icon;
  final Color color;

  @override
  State<_PulsingIcon> createState() => _PulsingIconState();
}

class _PulsingIconState extends State<_PulsingIcon>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1300),
  )..repeat(reverse: true);

  late final Animation<double> _scale = Tween<double>(begin: 1, end: 1.22)
      .animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scale,
      child: Icon(widget.icon, size: 18, color: widget.color),
    );
  }
}

class _Hairline extends StatelessWidget {
  const _Hairline();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      height: 1,
      color: colors.outlineVariant.withValues(alpha: 0.7),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.errorContainer.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.error.withValues(alpha: 0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(
            Icons.error_outline_rounded,
            color: colors.onErrorContainer,
            size: 18,
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
