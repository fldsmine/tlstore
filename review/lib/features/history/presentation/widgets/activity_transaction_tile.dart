import 'package:flutter/material.dart';

class ActivityTransactionTile extends StatelessWidget {
  const ActivityTransactionTile({
    super.key,
    required this.transaction,
    required this.displayAmount,
    this.compact = false,
    this.preferDescriptionTitle = false,
  });

  final Map<String, dynamic> transaction;
  final String displayAmount;
  final bool compact;
  final bool preferDescriptionTitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final type =
        '${transaction['type'] ?? ''} ${transaction['wallet'] ?? ''} ${transaction['description'] ?? ''}'
            .toLowerCase();
    final direction = transaction['direction']?.toString().toLowerCase();
    final credit = direction == 'credit';
    final description = transaction['description']?.toString().trim() ?? '';
    final title = preferDescriptionTitle && description.isNotEmpty
        ? description
        : _title(type, direction);
    final icon = _icon(type, direction);
    final accent = credit
        ? colors.primary
        : (direction == 'debit' ? colors.error : colors.secondary);
    final status = _humanize(transaction['status']?.toString() ?? 'unknown');
    final date = _formatDate(transaction['created_at'] ?? transaction['date']);
    final wallet =
        transaction['wallet']?.toString().replaceAll('_', ' ').trim() ?? '';

    return Padding(
      padding: EdgeInsets.all(compact ? 11 : 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: compact ? 40 : 44,
            height: compact ? 40 : 44,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: Icon(icon, size: 21, color: accent),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (!compact && description.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: colors.onSurfaceVariant,
                      height: 1.3,
                    ),
                  ),
                ],
                const SizedBox(height: 5),
                Wrap(
                  spacing: 7,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(
                      date,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    _StatusBadge(status: status),
                    if (wallet.isNotEmpty)
                      Text(
                        _humanize(wallet),
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '${credit
                    ? '+'
                    : direction == 'debit'
                    ? '−'
                    : ''}$displayAmount',
                textAlign: TextAlign.end,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: accent,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (transaction['reference']?.toString().trim().isNotEmpty ==
                  true) ...[
                const SizedBox(height: 4),
                Text(
                  'Ref ${transaction['reference']}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.onSurfaceVariant,
                    fontSize: 9,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  static String _title(String type, String? direction) {
    if (type.contains('withdraw')) {
      return 'Withdrawal';
    }
    if (type.contains('referral')) {
      return 'Referral reward';
    }
    if (type.contains('review')) {
      return 'App review reward';
    }
    if (type.contains('social_follow')) {
      return 'Social follow reward';
    }
    if (type.contains('share') || type.contains('ads_share')) {
      return 'Ad share reward';
    }
    if (type.contains('bonus') ||
        type.contains('reward') ||
        type.contains('rank_earning')) {
      return 'Bonus reward';
    }
    if (type.contains('top_up') ||
        type.contains('topup') ||
        type.contains('deposit')) {
      return 'Wallet top-up';
    }
    if (type.contains('subscription') ||
        type.contains('payment') ||
        type.contains('purchase')) {
      return 'Payment';
    }
    if (type.contains('transfer')) return 'Wallet transfer';
    return direction == 'credit'
        ? 'Account credit'
        : direction == 'debit'
        ? 'Account debit'
        : 'Account activity';
  }

  static IconData _icon(String type, String? direction) {
    if (type.contains('withdraw')) {
      return Icons.south_west_rounded;
    }
    if (type.contains('referral')) {
      return Icons.people_alt_rounded;
    }
    if (type.contains('review')) {
      return Icons.rate_review_rounded;
    }
    if (type.contains('social_follow')) {
      return Icons.person_add_alt_1_rounded;
    }
    if (type.contains('share') || type.contains('ads_share')) {
      return Icons.campaign_rounded;
    }
    if (type.contains('bonus') ||
        type.contains('reward') ||
        type.contains('rank_earning')) {
      return Icons.card_giftcard_rounded;
    }
    if (type.contains('top_up') ||
        type.contains('topup') ||
        type.contains('deposit')) {
      return Icons.account_balance_wallet_rounded;
    }
    if (type.contains('subscription') ||
        type.contains('payment') ||
        type.contains('purchase')) {
      return Icons.payment_rounded;
    }
    if (type.contains('transfer')) return Icons.swap_horiz_rounded;
    return direction == 'credit'
        ? Icons.south_west_rounded
        : Icons.receipt_long_rounded;
  }

  static String _humanize(String value) => value
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');

  static String _formatDate(dynamic raw) {
    final date = DateTime.tryParse(raw?.toString() ?? '')?.toLocal();
    if (date == null) return 'Date unavailable';
    final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
    final minute = date.minute.toString().padLeft(2, '0');
    final period = date.hour >= 12 ? 'PM' : 'AM';
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year} · $hour:$minute $period';
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final normalized = status.toLowerCase();
    final color =
        {
          'posted',
          'completed',
          'success',
          'successful',
          'approved',
        }.contains(normalized)
        ? colors.primary
        : {'pending', 'processing', 'queued'}.contains(normalized)
        ? colors.tertiary
        : {'failed', 'rejected', 'cancelled', 'canceled'}.contains(normalized)
        ? colors.error
        : colors.onSurfaceVariant;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .11),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        status,
        style: Theme.of(context).textTheme.labelSmall
            ?.copyWith(color: color, fontWeight: FontWeight.w800, fontSize: 9),
      ),
    );
  }
}
