import 'package:flutter/material.dart';

class HowItWorks extends StatelessWidget {

  final List<Map<String, dynamic>> steps;

  const HowItWorks({
    super.key, 
    required this.steps, 
  });

  @override
  Widget build(BuildContext context) {
    if (steps.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionHeading(
          context,
          'Getting started',
          'How it works',
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
                Icon(Icons.bolt_rounded, size: 15, color: colors.tertiary),
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
        Padding(
          padding: const EdgeInsets.fromLTRB(22, 10, 0, 30),
          child: Container(
            height: 2,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(2),
              gradient: LinearGradient(
                colors: [
                  colors.outlineVariant.withValues(alpha: .3),
                  _namedColor(
                    context,
                    'error',
                  ),
                ],
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
          child: Column(
            children: [
              for (var index = 0; index < steps.length; index++) ...[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: _namedColor(
                          context,
                          steps[index]['color']?.toString(),
                        ).withValues(alpha: .1),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: _namedColor(
                            context,
                            steps[index]['color']?.toString(),
                          ).withValues(alpha: .45),
                        ),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        _text(steps[index]['number'], '${index + 1}'),
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: _namedColor(
                            context,
                            steps[index]['color']?.toString(),
                          ),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(
                                _namedIcon(
                                  steps[index]['icon']?.toString(),
                                  Icons.task_alt_rounded,
                                ),
                                size: 19,
                                color: _namedColor(
                                  context,
                                  steps[index]['color']?.toString(),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  _text(steps[index]['title'], 'Next step'),
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text(
                            _text(steps[index]['description'], ''),
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colors.onSurfaceVariant,
                              height: 1.45,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (_flag(steps[index]['hasline']) && index < steps.length - 1)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(22, 14, 0, 14),
                    child: Container(
                      height: 2,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        gradient: LinearGradient(
                          colors: [
                            _namedColor(
                              context,
                              steps[index]['color']?.toString(),
                            ),
                            colors.outlineVariant.withValues(alpha: .3),
                          ],
                        ),
                      ),
                    ),
                  ),
                if (!_flag(steps[index]['hasline']) && index < steps.length - 1)
                  const SizedBox(height: 16),
              ],
            ],
          ),
        ),
      ],
    );
  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  /// Heading used throughout the page: coloured bar + title + optional pill.
  Widget _sectionHeading(
    BuildContext context,
    String eyebrow,
    String title, {
    String? description,
    Widget? trailing,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 1),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  eyebrow.toUpperCase(),
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.primary,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  title,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.5,
                  ),
                ),
                if (description != null) ...[
                  const SizedBox(height: 5),
                  Text(
                    description,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      height: 1.4,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) trailing,
        ],
      ),
    );
  }

  /// Resolves the accent colour name supplied with each how-it-works step.
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

  /// Maps the icon name returned by the API to a Material icon.
  IconData _namedIcon(String? name, IconData fallback) =>
      switch (name?.toLowerCase().trim() ?? '') {
        'person_add_alt_1_rounded' ||
        'person_add' => Icons.person_add_alt_1_rounded,
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
