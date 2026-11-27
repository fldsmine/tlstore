import 'package:flutter/material.dart';

import 'anim/illustrations.dart';

class FaqSection extends StatelessWidget {
  final List<Map<String, dynamic>> faqs;

  const FaqSection({
    super.key,
    required this.faqs,
  });

  @override
  Widget build(BuildContext context) {
    if (faqs.isEmpty) return const SizedBox.shrink();

    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    // Group FAQs by category.
    final Map<String, List<Map<String, dynamic>>> groupedFaqs = {};

    for (final faq in faqs) {
      final category = _text(faq['category'], 'General');

      groupedFaqs.putIfAbsent(category, () => []);
      groupedFaqs[category]!.add(faq);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionHeading(
          context,
          'Helpful answers',
          'Frequently asked questions',
          description: 'Select a category to browse its questions.',
          trailing: const FaqArt(),
        ),

        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            children: [
              for (final entry in groupedFaqs.entries)
                Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: colors.outlineVariant,
                    ),
                  ),
                  child: Material(
                    color: colors.surfaceContainerLow,
                    borderRadius: BorderRadius.circular(18),
                    clipBehavior: Clip.antiAlias,
                    child: ExpansionTile(
                      key: PageStorageKey('faq-category-${entry.key}'),
                      initiallyExpanded: false,
                      tilePadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      childrenPadding: const EdgeInsets.fromLTRB(
                        12, 0, 12, 12,
                      ),
                      leading: Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: colors.primary.withValues(alpha: .15),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          _categoryIcon(entry.key),
                          color: colors.primary,
                        ),
                      ),
                      title: Text(
                        _label(entry.key),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      subtitle: Text(
                        '${entry.value.length} ${entry.value.length == 1 ? 'question' : 'questions'}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),

                      // Questions appear when category is expanded.
                      children: [
                        for (var index = 0;
                            index < entry.value.length;
                            index++)
                          Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: colors.outlineVariant,
                              ),
                            ),
                            child: Material(
                              color: colors.surface,
                              borderRadius: BorderRadius.circular(12),
                              clipBehavior: Clip.antiAlias,
                              child: ExpansionTile(
                                key: PageStorageKey(
                                  'faq-question-${entry.value[index]['id'] ?? index}',
                                ),
                                tilePadding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 4,
                                ),
                                childrenPadding:
                                    const EdgeInsets.fromLTRB(
                                  14, 0, 14, 14,
                                ),
                                leading: Icon(
                                  Icons.help_outline_rounded,
                                  color: colors.primary,
                                  size: 21,
                                ),
                                title: Text(
                                  _text(
                                    entry.value[index]['question'],
                                    'Frequently asked question',
                                  ),
                                  style: theme.textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w600,
                                    height: 1.35,
                                  ),
                                ),
                                children: [
                                  Align(
                                    alignment: Alignment.centerLeft,
                                    child: Text(
                                      _text(
                                        entry.value[index]['answer'],
                                        '',
                                      ),
                                      style: theme.textTheme.bodyMedium?.copyWith(
                                        color: colors.onSurfaceVariant,
                                        height: 1.55,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),

        const SizedBox(height: 16),
      ],
    );
  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  String _label(String value) {
    return value
        .split(RegExp(r'[_\-\s]+'))
        .where((part) => part.isNotEmpty)
        .map((part) =>
            '${part[0].toUpperCase()}${part.substring(1)}')
        .join(' ');
  }

  IconData _categoryIcon(String category) {
    switch (category.toLowerCase()) {
      case 'general':
        return Icons.info_outline_rounded;
      case 'earnings':
        return Icons.monetization_on_outlined;
      case 'accounts':
        return Icons.person_outline_rounded;
      case 'wallets':
        return Icons.account_balance_wallet_outlined;
      default:
        return Icons.help_outline_rounded;
    }
  }

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
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
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

}
