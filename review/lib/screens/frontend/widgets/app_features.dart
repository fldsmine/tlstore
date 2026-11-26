import 'package:flutter/material.dart';

class AppFeatures extends StatelessWidget {

  final List<String> features;

  const AppFeatures({
    super.key, 
    required this.features, 
  });

  @override
  Widget build(BuildContext context) {

    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeading(
            context,
            'Why TLStore',
            'A simple way to take part',
            description:
                'Choose tasks, follow clear steps, and track the outcome.',
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Wrap(
              spacing: 9,
              runSpacing: 9,
              children: features.map((feature) {
                return Chip(
                  avatar: Icon(
                    _featureIcon(feature),
                    size: 17,
                    color: colors.primary,
                  ),
                  label: Text(feature),
                  backgroundColor: colors.primaryContainer.withValues(
                    alpha: .55,
                  ),
                  side: BorderSide.none,
                  labelStyle: TextStyle(
                    color: colors.onSurface,
                    fontWeight: FontWeight.w700,
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );

  }


  IconData _featureIcon(String value) {
    final feature = value.toLowerCase();
    if (feature.contains('review')) return Icons.rate_review_rounded;
    if (feature.contains('share') || feature.contains('sponsor')) {
      return Icons.campaign_rounded;
    }
    if (feature.contains('referral')) return Icons.people_alt_rounded;
    if (feature.contains('reward') || feature.contains('earn')) {
      return Icons.account_balance_wallet_rounded;
    }
    if (feature.contains('support')) return Icons.support_agent_rounded;
    return Icons.check_circle_rounded;
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
