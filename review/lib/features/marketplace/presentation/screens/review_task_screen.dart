import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../services/auth_api.dart';
import '../../../../widgets/auth_components.dart';

class ReviewTaskScreen extends StatefulWidget {
  const ReviewTaskScreen({
    super.key,
    required this.api,
    required this.token,
    required this.task,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> task;

  @override
  State<ReviewTaskScreen> createState() => _ReviewTaskScreenState();
}

class _ReviewTaskScreenState extends State<ReviewTaskScreen> {
  static const _pageColor = Color(0xFF111B26);
  static const _surface = Color(0xFF192632);
  static const _line = Color(0xFF2D3B48);
  static const _cyan = Color(0xFF59D1D0);
  static const _muted = Color(0xFFAAB7C3);

  final _pageController = PageController();
  final DateTime _startedAt = DateTime.now();
  final Map<int, dynamic> _answers = {};
  final Map<int, TextEditingController> _textControllers = {};
  int _page = 0;
  bool _sending = false;

  List<Map<String, dynamic>> get _questions {
    final template = widget.task['template_version'];
    final values = template is Map<String, dynamic>
        ? template['questions']
        : null;
    return values is List
        ? values.whereType<Map<String, dynamic>>().toList()
        : const [];
  }

  Map<String, dynamic> get _app {
    final campaign = widget.task['campaign'];
    final app = campaign is Map ? campaign['app'] : null;
    return app is Map<String, dynamic> ? app : const {};
  }

  @override
  void initState() {
    super.initState();
    for (final question in _questions) {
      if (question['type'] != 'rating') continue;
      final id = _questionId(question);
      final settings = question['settings'];
      final scale = settings is Map ? settings['scale'] : null;
      final minimum = scale is Map ? scale['min'] : null;
      if (id != null) _answers[id] = minimum is num ? minimum.round() : 1;
    }
  }

  @override
  void dispose() {
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
    return true;
  }

  void _next() {
    if (_page < _questions.length && !_isAnswered(_questions[_page])) {
      showAuthMessage(context, 'Complete this answer before continuing.');
      return;
    }
    if (_page < _questions.length) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutCubic,
      );
    }
  }

  void _back() {
    if (_page == 0) return;
    _pageController.previousPage(
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _submit() async {
    if (_questions.any((question) => !_isAnswered(question))) {
      showAuthMessage(context, 'Answer every question before submitting.');
      return;
    }
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
            DateTime.now().difference(_startedAt).inSeconds,
          ),
        },
      );
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).popUntil((route) => route.isFirst);
      messenger.showSnackBar(
        const SnackBar(content: Text('Review submitted for human approval.')),
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = _questions.length;
    if (total == 0) {
      return const Scaffold(
        backgroundColor: _pageColor,
        body: Center(
          child: Text(
            'Questions are unavailable for this task.',
            style: TextStyle(color: Colors.white),
          ),
        ),
      );
    }

    final isSummary = _page == total;
    final shownStep = isSummary ? total : _page + 1;
    return Scaffold(
      backgroundColor: _pageColor,
      appBar: AppBar(
        backgroundColor: _pageColor,
        foregroundColor: Colors.white,
        title: const _ReviewerModePill(),
        centerTitle: true,
        leading: IconButton(
          onPressed: _page == 0 ? () => Navigator.maybePop(context) : _back,
          icon: Icon(
            _page == 0 ? Icons.close_rounded : Icons.arrow_back_rounded,
          ),
          tooltip: _page == 0 ? 'Close review' : 'Previous question',
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(22, 4, 22, 16),
              child: _ProgressHeader(
                appName: _app['name']?.toString() ?? 'App review',
                current: shownStep,
                total: total,
                progress: isSummary ? 1 : shownStep / total,
                isSummary: isSummary,
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: total + 1,
                onPageChanged: (value) => setState(() => _page = value),
                itemBuilder: (context, index) => index == total
                    ? _TrustSummaryPage(
                        appName: _app['name']?.toString() ?? 'This app',
                        questions: _questions,
                        answers: _answers,
                      )
                    : _QuestionSlide(
                        number: index + 1,
                        total: total,
                        question: _questions[index],
                        answer: _answers[_questionId(_questions[index])],
                        textController: _controllerFor(_questions[index]),
                        onChanged: (value) => setState(() {
                          final id = _questionId(_questions[index]);
                          if (id != null) _answers[id] = value;
                        }),
                      ),
              ),
            ),
            _FooterControls(
              isSummary: isSummary,
              isSending: _sending,
              hasPrevious: _page > 0,
              onBack: _back,
              onContinue: isSummary ? _submit : _next,
            ),
          ],
        ),
      ),
    );
  }

  TextEditingController _controllerFor(Map<String, dynamic> question) {
    final id = _questionId(question);
    if (id == null) return TextEditingController();
    return _textControllers.putIfAbsent(id, TextEditingController.new);
  }
}

int? _questionId(Map<String, dynamic> question) =>
    (question['id'] as num?)?.toInt();

class _ReviewerModePill extends StatelessWidget {
  const _ReviewerModePill();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
    decoration: BoxDecoration(
      color: const Color(0xFFE7EEF3),
      borderRadius: BorderRadius.circular(25),
    ),
    child: const Text(
      'Reviewer app',
      style: TextStyle(color: Color(0xFF17232F), fontSize: 13),
    ),
  );
}

class _ProgressHeader extends StatelessWidget {
  const _ProgressHeader({
    required this.appName,
    required this.current,
    required this.total,
    required this.progress,
    required this.isSummary,
  });

  final String appName;
  final int current;
  final int total;
  final double progress;
  final bool isSummary;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Row(
        children: [
          Expanded(
            child: Text(
              appName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFFAAB7C3),
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ),
          Text(
            isSummary ? 'Summary' : '$current of $total',
            style: const TextStyle(
              color: _ReviewTaskScreenState._muted,
              fontSize: 12,
            ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: LinearProgressIndicator(
          value: progress,
          minHeight: 6,
          backgroundColor: const Color(0xFF263542),
          color: _ReviewTaskScreenState._cyan,
        ),
      ),
    ],
  );
}

class _QuestionSlide extends StatelessWidget {
  const _QuestionSlide({
    required this.number,
    required this.total,
    required this.question,
    required this.answer,
    required this.textController,
    required this.onChanged,
  });

  final int number;
  final int total;
  final Map<String, dynamic> question;
  final dynamic answer;
  final TextEditingController textController;
  final ValueChanged<dynamic> onChanged;

  static const _text = Color(0xFFE8EEF3);
  static const _muted = Color(0xFFAAB7C3);
  static const _cyan = Color(0xFF59D1D0);
  static const _line = Color(0xFF2D3B48);
  static const _surface = Color(0xFF192632);

  @override
  Widget build(BuildContext context) {
    final prompt = question['prompt']?.toString() ?? 'Question';
    final hint = question['hint']?.toString();
    final type = question['type']?.toString();
    final options = question['options'] is List
        ? (question['options'] as List)
              .map((value) => value.toString())
              .toList()
        : const <String>[];
    final settings = question['settings'] is Map
        ? question['settings'] as Map
        : const {};
    final scale = settings['scale'] is Map
        ? settings['scale'] as Map
        : const {};
    final minimum = (scale['min'] as num?)?.toDouble() ?? 1;
    final maximum = (scale['max'] as num?)?.toDouble() ?? 5;

    return ListView(
      padding: const EdgeInsets.fromLTRB(22, 8, 22, 24),
      children: [
        Text(
          prompt,
          style: const TextStyle(
            color: _text,
            fontSize: 23,
            height: 1.28,
            fontWeight: FontWeight.w800,
          ),
        ),
        if (hint != null && hint.isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(hint, style: const TextStyle(color: _muted, height: 1.45)),
        ],
        const SizedBox(height: 23),
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
          ...options.map((option) {
            final selected = answer is List && answer.contains(option);
            final exclusiveIndex = settings['exclusive_option_index'];
            final optionIndex = options.indexOf(option);
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
        if (type == 'rating') ...[
          _DarkPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Slider(
                  value: (answer as num?)?.toDouble() ?? minimum,
                  min: minimum,
                  max: maximum,
                  divisions: math.max(1, (maximum - minimum).round()),
                  activeColor: _cyan,
                  inactiveColor: _line,
                  label: (answer ?? minimum).toString(),
                  onChanged: (value) => onChanged(value.round()),
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      scale['low_label']?.toString() ?? 'Low',
                      style: const TextStyle(color: _muted, fontSize: 11),
                    ),
                    Text(
                      '${answer ?? minimum} / ${maximum.round()}',
                      style: const TextStyle(
                        color: _cyan,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      scale['high_label']?.toString() ?? 'High',
                      style: const TextStyle(color: _muted, fontSize: 11),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
        if (type == 'long_text')
          TextField(
            controller: textController,
            minLines: 5,
            maxLines: 9,
            style: const TextStyle(color: _text),
            onChanged: onChanged,
            decoration: InputDecoration(
              hintText: 'Write your answer in your own words…',
              hintStyle: const TextStyle(color: _muted),
              filled: true,
              fillColor: _surface,
              alignLabelWithHint: true,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: const BorderSide(color: _line),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: const BorderSide(color: _line),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(15),
                borderSide: const BorderSide(color: _cyan),
              ),
            ),
          ),
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
  Widget build(BuildContext context) => Material(
    color: selected ? const Color(0xFF123B3C) : _ReviewTaskScreenState._surface,
    borderRadius: BorderRadius.circular(16),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected
                ? _ReviewTaskScreenState._cyan
                : _ReviewTaskScreenState._line,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(
              selected
                  ? (multiple ? Icons.check_circle : Icons.radio_button_checked)
                  : (multiple ? Icons.circle_outlined : Icons.radio_button_off),
              color: selected
                  ? _ReviewTaskScreenState._cyan
                  : _ReviewTaskScreenState._muted,
              size: 19,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: Color(0xFFE8EEF3),
                  fontSize: 14,
                  height: 1.35,
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _DarkPanel extends StatelessWidget {
  const _DarkPanel({required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFF192632),
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: const Color(0xFF2D3B48)),
    ),
    child: child,
  );
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
    var claimsBroken = 0;
    var warningSignals = 0;
    var attentionPassed = false;
    String? developerAssessment;

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
          ? (question['options'] as List)
                .map((option) => option.toString())
                .toList()
          : const <String>[];

      if (key == 'attn') {
        attentionPassed =
            value?.toString().toLowerCase().contains('somewhat') ?? false;
      }
      if (key == 'dev') developerAssessment = value?.toString();

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
      padding: const EdgeInsets.fromLTRB(22, 8, 22, 24),
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
          decoration: BoxDecoration(
            color: const Color(0xFF123B3C),
            borderRadius: BorderRadius.circular(24),
          ),
          child: const Text(
            'TRUST SCORE SUMMARY',
            style: TextStyle(
              color: Color(0xFF59D1D0),
              fontWeight: FontWeight.w800,
              fontSize: 11,
              letterSpacing: .8,
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text(
          'Your review of $appName',
          style: const TextStyle(
            color: Color(0xFFE8EEF3),
            fontSize: 24,
            height: 1.25,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Check your trust signals before submitting. The final app trust score is confirmed after human review.',
          style: TextStyle(color: Color(0xFFAAB7C3), height: 1.45),
        ),
        const SizedBox(height: 23),
        Container(
          padding: const EdgeInsets.fromLTRB(16, 7, 16, 7),
          decoration: BoxDecoration(
            color: const Color(0xFF192632),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            children: [
              const _ScoreStatus(),
              _SummaryMetric(
                label: 'Store claims that did not match',
                value: '$claimsBroken',
                accent: const Color(0xFF59D1D0),
              ),
              _SummaryMetric(
                label: 'Warning signals selected',
                value: '$warningSignals',
                accent: warningSignals == 0
                    ? const Color(0xFF59D1D0)
                    : const Color(0xFFFF8397),
              ),
              _SummaryMetric(
                label: 'Attention check',
                value: attentionPassed ? 'Passed' : 'Review answer',
                accent: attentionPassed
                    ? const Color(0xFF59D1D0)
                    : const Color(0xFFFFC24B),
              ),
              _SummaryMetric(
                label: 'Developer verification',
                value: developerAssessment ?? 'Not answered',
                accent: const Color(0xFF59D1D0),
                last: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFF202F3C),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFF2D3B48)),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.fact_check_outlined, color: Color(0xFF59D1D0)),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Your answers are submitted for admin approval. The trust score is finalized after the review is verified.',
                  style: TextStyle(
                    color: Color(0xFFD5DEE6),
                    height: 1.4,
                    fontSize: 12,
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
    required this.accent,
    this.last = false,
  });

  final String label;
  final String value;
  final Color accent;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 13),
    decoration: BoxDecoration(
      border: last
          ? null
          : const Border(bottom: BorderSide(color: Color(0xFF2D3B48))),
    ),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(color: Color(0xFFE1E8ED), fontSize: 13),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              color: accent,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    ),
  );
}

class _ScoreStatus extends StatelessWidget {
  const _ScoreStatus();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 14),
    child: Row(
      children: [
        Expanded(
          child: Text(
            'App trust score',
            style: TextStyle(
              color: Color(0xFFE8EEF3),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        Text(
          'Pending review',
          style: TextStyle(
            color: Color(0xFFFFC24B),
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
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
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(22, 13, 22, 16),
    decoration: const BoxDecoration(
      border: Border(top: BorderSide(color: Color(0xFF263542))),
    ),
    child: SafeArea(
      top: false,
      child: Row(
        children: [
          if (hasPrevious) ...[
            IconButton(
              onPressed: onBack,
              color: Colors.white,
              icon: const Icon(Icons.arrow_back_rounded),
              tooltip: 'Previous',
            ),
            const SizedBox(width: 5),
          ],
          Expanded(
            child: FilledButton(
              onPressed: isSending ? null : onContinue,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFE7EEF3),
                foregroundColor: const Color(0xFF111B26),
                minimumSize: const Size.fromHeight(54),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
              child: isSending
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(isSummary ? 'Submit review' : 'Next'),
            ),
          ),
        ],
      ),
    ),
  );
}
