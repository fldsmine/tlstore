import 'package:flutter/material.dart';

import 'anim/anim_kit.dart';

class AppFeatures extends StatelessWidget {
  final List<String> features;

  const AppFeatures({super.key, required this.features});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SectionHeading(
            eyebrow: 'Why TLStore',
            title: 'A simple way to take part',
            description: 'Choose tasks, follow clear steps, and track the outcome.',
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(24),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: colors.outlineVariant),
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      colors.primaryContainer.withValues(alpha: .5),
                      colors.surfaceContainerLow,
                    ],
                  ),
                ),
                child: Stack(
                  children: [
                    Positioned.fill(
                      child: Sparkles(count: 14, color: colors.primary, seed: 5, maxSize: 6),
                    ),
                    Wrap(
                      spacing: 10,
                      runSpacing: 12,
                      children: [
                        for (var i = 0; i < features.length; i++)
                          Reveal(
                            delay: Duration(milliseconds: 70 * i),
                            scale: .6,
                            curve: Curves.easeOutBack,
                            offset: const Offset(0, 14),
                            child: Floating(
                              amplitude: 3,
                              phase: i * .17,
                              duration: Duration(milliseconds: 2800 + (i % 3) * 600),
                              child: _bubble(context, features[i], i),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bubble(BuildContext context, String feature, int index) {
    final colors = Theme.of(context).colorScheme;
    final accent = [colors.primary, colors.secondary, colors.tertiary][index % 3];
    return Container(
      padding: const EdgeInsets.fromLTRB(6, 6, 14, 6),
      decoration: BoxDecoration(
        color: colors.surface.withValues(alpha: .75),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: accent.withValues(alpha: .5)),
        boxShadow: [
          BoxShadow(color: accent.withValues(alpha: .18), blurRadius: 12, offset: const Offset(0, 4)),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(color: accent.withValues(alpha: .2), shape: BoxShape.circle),
            child: Icon(_featureIcon(feature), size: 17, color: accent),
          ),
          const SizedBox(width: 8),
          Text(
            feature,
            style: TextStyle(color: colors.onSurface, fontWeight: FontWeight.w700),
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
    if (feature.contains('location')) return Icons.location_on_rounded;
    if (feature.contains('human')) return Icons.verified_user_rounded;
    return Icons.check_circle_rounded;
  }
}
