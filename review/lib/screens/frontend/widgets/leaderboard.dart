import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:review/services/media_url.dart';
import '../../../core/currency/money.dart';

class Leaderboard extends StatelessWidget {

  final List<Map<String, dynamic>> leaders;

  const Leaderboard({
    super.key, 
    required this.leaders, 
  });

  @override
  Widget build(BuildContext context) {

    if (leaders.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final hasPodium = leaders.length >= 3;
    final remaining = hasPodium ? leaders.skip(3).take(4).toList() : leaders;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionHeading(
          context,
          'Community',
          'Leaderboard',
          description: 'Meet the members completing the most tasks.',
          trailing: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: colors.tertiaryContainer,
              borderRadius: BorderRadius.circular(999),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.emoji_events_rounded, size: 15, color: colors.tertiary),
                const SizedBox(width: 5),
                Text(
                  'Top members',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.onTertiaryContainer,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Container(
            padding: const EdgeInsets.fromLTRB(12, 16, 12, 12),
            decoration: BoxDecoration(
              color: colors.surfaceContainerLow,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: colors.outlineVariant),
            ),
            child: Column(
              children: [
                if (hasPodium) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: _leaderPodiumItem(
                          context,
                          leaders[1],
                          rank: 2,
                          medal: const Color(0xFF9DA9B4),
                          blockHeight: 48,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _leaderPodiumItem(
                          context,
                          leaders[0],
                          rank: 1,
                          medal: const Color(0xFFE9B949),
                          blockHeight: 68,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _leaderPodiumItem(
                          context,
                          leaders[2],
                          rank: 3,
                          medal: const Color(0xFFCD8A52),
                          blockHeight: 40,
                        ),
                      ),
                    ],
                  ),
                  if (remaining.isNotEmpty) const SizedBox(height: 12),
                ],
                for (var index = 0; index < remaining.length; index++)
                  _leaderRow(
                    context,
                    remaining[index],
                    hasPodium ? index + 3 : index,
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),
      ],
    );

  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }
  String _image(Object? value) => MediaUrl.resolve(value?.toString()) ?? '';

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

  Widget _leaderPodiumItem(
    BuildContext context,
    Map<String, dynamic> leader,
    {required int rank,
    required Color medal,
    required double blockHeight,}
  ) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final user = _asMap(leader['user']);
    final avatar = _image(user['avatar']);
    final name = _text(user['full_name'], _text(user['username'], 'Member'));
    final firstName = name.split(RegExp(r'\s+')).first;
    final score = _leaderScore(leader);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (rank == 1)
          Icon(Icons.workspace_premium_rounded, color: medal, size: 20),
          
        CircleAvatar(
          radius: rank == 1 ? 25 : 21,
          backgroundColor: medal.withValues(alpha: .2),
          foregroundImage: avatar.isEmpty ? null : CachedNetworkImageProvider(avatar),
          child: avatar.isEmpty
              ? Text(
                  firstName.isEmpty ? '?' : firstName[0].toUpperCase(),
                  style: TextStyle(
                    color: colors.onSurface,
                    fontWeight: FontWeight.w900,
                  ),
                )
              : null,
        ),
        const SizedBox(height: 5),
        Text(
          firstName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.labelMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          '$score tasks',
          maxLines: 1,
          style: theme.textTheme.labelSmall?.copyWith(
            color: colors.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          height: blockHeight,
          width: double.infinity,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [medal.withValues(alpha: .35), medal.withValues(alpha: .12)],
            ),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(10)),
          ),
          alignment: Alignment.center,
          child: Text(
            '#$rank',
            style: theme.textTheme.titleLarge?.copyWith(
              color: colors.onSurface,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    );
  }

  Widget _leaderRow(
    BuildContext context,
    Map<String, dynamic> leader,
    int index,
  ) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final user = _asMap(leader['user']);
    final avatar = _image(user['avatar']);
    final name = _text(user['full_name'], _text(user['username'], 'Member'));
    final rank = int.tryParse(leader['rank']?.toString() ?? '') ?? index + 1;
    final reviews = int.tryParse(leader['total_review']?.toString() ?? '') ?? 0;
    final shares = int.tryParse(leader['total_share']?.toString() ?? '') ?? 0;
    final score = _leaderScore(leader);

    return Container(
      margin: const EdgeInsets.only(bottom: 7),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: .55)),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 30,
            child: Text(
              '#$rank',
              style: theme.textTheme.labelLarge?.copyWith(
                color: colors.primary,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          CircleAvatar(
            radius: 17,
            backgroundColor: colors.primaryContainer,
            foregroundImage: avatar.isEmpty ? null : CachedNetworkImageProvider(avatar),
            child: avatar.isEmpty
                ? Icon(Icons.person_rounded, size: 18, color: colors.onPrimaryContainer)
                : null,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  '$reviews reviews · $shares shares',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '${formatGroupedNumber(score, 0)} tasks',
            style: theme.textTheme.labelMedium?.copyWith(
              color: colors.secondary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  int _leaderScore(Map<String, dynamic> leader) {
    final explicit = int.tryParse(leader['total_score']?.toString() ?? '');
    if (explicit != null) return explicit;
    final reviews = int.tryParse(leader['total_review']?.toString() ?? '') ?? 0;
    final shares = int.tryParse(leader['total_share']?.toString() ?? '') ?? 0;
    return reviews + shares;
  }

  Map<String, dynamic> _asMap(Object? value) => value is Map
      ? value.map((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

}
