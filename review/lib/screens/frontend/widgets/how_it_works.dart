import 'package:flutter/material.dart';

import 'anim/anim_kit.dart';
import 'anim/illustrations.dart';

class HowItWorks extends StatelessWidget {
  final List<Map<String, dynamic>> steps;

  const HowItWorks({super.key, required this.steps});

  @override
  Widget build(BuildContext context) {
    if (steps.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeading(
          eyebrow: 'Getting started',
          title: 'How it works',
          description: 'Four clear steps from joining to earning rewards.',
          trailing: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: colors.tertiary.withValues(alpha: .16),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Floating(
                  amplitude: 2,
                  duration: const Duration(seconds: 2),
                  child: Icon(Icons.bolt_rounded, size: 15, color: colors.tertiary),
                ),
                const SizedBox(width: 5),
                Text(
                  '${steps.length} steps',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.tertiary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
          child: Column(
            children: [
              for (var i = 0; i < steps.length; i++) _step(context, i),
            ],
          ),
        ),
      ],
    );
  }


  Widget _step(BuildContext context, int index) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final step = steps[index];
    final color = _namedColor(context, step['color']?.toString());
    final isLast = index == steps.length - 1;
    final showLine = _flag(step['hasline']) && !isLast;
    final delay = Duration(milliseconds: 120 * index);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 46,
          child: Column(
            children: [
              Reveal(
                delay: delay,
                scale: .4,
                offset: Offset.zero,
                curve: Curves.easeOutBack,
                child: _node(theme, step, index, color),
              ),
              if (showLine)
                SizedBox(
                  height: 90,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: _line(colors, color, delay),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: isLast ? 0 : 18),
            child: Reveal(
              delay: delay,
              offset: const Offset(28, 0),
              child: _card(context, step, index, color),
            ),
          ),
        ),
      ],
    );
  }

  Widget _node(ThemeData theme, Map<String, dynamic> step, int index, Color color) {
    return SizedBox(
      width: 46,
      height: 46,
      child: Loop(
        duration: const Duration(seconds: 3),
        builder: (context, t, _) => Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: 46 * (1 + .35 * t),
              height: 46 * (1 + .35 * t),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: color.withValues(alpha: .5 * (1 - t))),
              ),
            ),
            Container(
              width: 46,
              height: 46,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: color.withValues(alpha: .14),
                shape: BoxShape.circle,
                border: Border.all(color: color.withValues(alpha: .6), width: 1.5),
              ),
              child: Text(
                _text(step['number'], '${index + 1}'),
                style: theme.textTheme.titleMedium?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _line(ColorScheme colors, Color color, Duration delay) {
    return InView(
      delay: delay + const Duration(milliseconds: 250),
      builder: (context, visible) => Stack(
        alignment: Alignment.topCenter,
        children: [
          Container(
            width: 2,
            decoration: BoxDecoration(
              color: colors.outlineVariant.withValues(alpha: .5),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: visible ? 1 : 0),
            duration: reduceMotion(context)
                ? Duration.zero
                : const Duration(milliseconds: 800),
            curve: Curves.easeInOut,
            builder: (context, v, _) => Align(
              alignment: Alignment.topCenter,
              child: FractionallySizedBox(
                heightFactor: v,
                child: Container(
                  width: 3,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(3),
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [color, color.withValues(alpha: .25)],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _card(BuildContext context, Map<String, dynamic> step, int index, Color color) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: .35)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      _namedIcon(step['icon']?.toString(), Icons.task_alt_rounded),
                      size: 19,
                      color: color,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _text(step['title'], 'Next step'),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  _text(step['description'], ''),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSurfaceVariant,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          StepArt(index: index, color: color, size: 64),
        ],
      ),
    );
  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  Color _namedColor(BuildContext context, String? name) {
    final colors = Theme.of(context).colorScheme;
    return switch (name?.toLowerCase().trim() ?? '') {
      'primary' => colors.primary,
      'secondary' => colors.secondary,
      'tertiary' => colors.tertiary,
      'error' => colors.error,
      'success' => colors.tertiary,
      _ => colors.primary,
    };
  }

  IconData _namedIcon(String? name, IconData fallback) =>
      switch (name?.toLowerCase().trim() ?? '') {
        'person_add_alt_1_rounded' || 'person_add' => Icons.person_add_alt_1_rounded,
        'workspace_premium_rounded' ||
        'workspace_premium' => Icons.workspace_premium_rounded,
        'task_alt_rounded' || 'task_alt' => Icons.task_alt_rounded,
        'account_balance_wallet_rounded' ||
        'account_balance_wallet' => Icons.account_balance_wallet_rounded,
        'campaign_rounded' || 'campaign' => Icons.campaign_rounded,
        'favorite_rounded' || 'favorite' => Icons.favorite_rounded,
        'help_outline_rounded' || 'help' => Icons.help_outline_rounded,
        _ => fallback,
      };

  bool _flag(Object? value) =>
      value is bool ? value : value.toString().toLowerCase() == 'true';
}
