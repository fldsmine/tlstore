import 'package:flutter/material.dart';

import 'activity_transaction_details_sheet.dart';

class ActivityTransactionTile extends StatelessWidget {
  const ActivityTransactionTile({
    super.key,
    required this.transaction,
    required this.displayAmount,
    this.compact = false,
    this.preferDescriptionTitle = false,
    this.onTap,
  });

  final Map<String, dynamic> transaction;
  final String displayAmount;
  final bool compact;
  final bool preferDescriptionTitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final type =
        '${transaction['type'] ?? ''} ${transaction['wallet'] ?? ''} ${transaction['description'] ?? ''}'
            .toLowerCase();
    final direction = transaction['direction']?.toString().toLowerCase();
    final status = transaction['status']?.toString().toLowerCase();
    final isPending = status == 'pending';
    final isRejected = status == 'rejected';
    final credit = direction == 'credit';
    final description = transaction['description']?.toString().trim() ?? '';
    final title = preferDescriptionTitle && description.isNotEmpty
        ? description
        : _title(type, direction, description);
    final category = _category(type, direction);
    final accent = credit ? colors.primary : colors.error;
    final icon = _icon(type, direction);
    final openDetails =
        onTap ??
        () => showActivityTransactionDetailsSheet(
          context: context,
          transaction: transaction,
          displayAmount: displayAmount,
        );

    return Material(
      color: isPending
      ? colors.tertiaryContainer.withValues(alpha: .15)
      : isRejected
          ? colors.errorContainer.withValues(alpha: .15)
          : colors.surface,
      shape: RoundedRectangleBorder(
		  borderRadius: BorderRadius.circular(12),
		  side: BorderSide(
			color: isPending
				? colors.error.withValues(alpha: .2)
				: isRejected
					? colors.error.withValues(alpha: .3)
					: colors.outlineVariant.withValues(alpha: .9),
			width: (isPending || isRejected) ? 1.5 : 1,
		  ),
		),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: openDetails,
        child: Padding(
          padding: EdgeInsets.symmetric(
            horizontal: compact ? 12 : 14,
            vertical: compact ? 10 : 12,
          ),
          child: Row(
            children: [
              Container(
                width: compact ? 40 : 48,
                height: compact ? 40 : 48,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: colors.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 22, color: accent),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: colors.onSurface,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '$category · ${_relativeDate(transaction['created_at'] ?? transaction['date'])}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Text(
                '${credit
                    ? '+'
                    : direction == 'debit'
                    ? '−'
                    : ''}$displayAmount',
                textAlign: TextAlign.end,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: isPending
				? colors.error.withValues(alpha: .4)
				: isRejected
					? colors.error.withValues(alpha: .6)
					: accent,
                  fontWeight: FontWeight.w900,
				  decoration: (isPending || isRejected)
					? TextDecoration.lineThrough
					: TextDecoration.none,
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _title(String type, String? direction, String description) {
    if (description.isNotEmpty) return description;
    if (type.contains('withdraw')) return 'Withdrawal';
    if (type.contains('referral')) return 'Referral bonus';
    if (type.contains('review')) return 'Product review';
    if (type.contains('social_follow')) return 'Social follow reward';
    if (type.contains('share') || type.contains('ads_share')) {
      return 'Ad share reward';
    }
    if (type.contains('rank_earning')) return 'Ranking reward';
    if (type.contains('bonus') || type.contains('reward'))
      return 'Bonus reward';
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
    return direction == 'credit' ? 'Account credit' : 'Account debit';
  }

  static String _category(String type, String? direction) {
    if (type.contains('review')) return 'Reviews';
    if (type.contains('share')) return 'Ad shares';
    if (type.contains('referral')) return 'Referrals';
    if (type.contains('social_follow')) return 'Tasks';
    if (type.contains('withdraw')) return 'Withdrawals';
    if (type.contains('top_up') ||
        type.contains('topup') ||
        type.contains('deposit')) {
      return 'Top ups';
    }
    if (type.contains('subscription') ||
        type.contains('payment') ||
        type.contains('purchase')) {
      return 'Payments';
    }
    if (type.contains('rank') || type.contains('bonus')) return 'Rewards';
    if (type.contains('transfer')) return 'Transfers';
    return direction == 'credit' ? 'Credits' : 'Transactions';
  }

  static IconData _icon(String type, String? direction) {
    if (type.contains('withdraw')) return Icons.south_west_rounded;
    if (type.contains('referral')) return Icons.handshake_rounded;
    if (type.contains('review')) return Icons.star_rounded;
    if (type.contains('social_follow')) return Icons.check_box_rounded;
    if (type.contains('share')) return Icons.campaign_rounded;
    if (type.contains('rank') ||
        type.contains('bonus') ||
        type.contains('reward')) {
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

  static String _relativeDate(dynamic raw) {
    final date = DateTime.tryParse(raw?.toString() ?? '')?.toLocal();
    if (date == null) return 'Date unavailable';
    final difference = DateTime.now().difference(date);
    if (difference.inMinutes < 1) return 'Just now';
    if (difference.inHours < 1)
      return '${difference.inMinutes} ${difference.inMinutes == 1 ? 'minute' : 'minutes'} ago';
    if (difference.inDays < 1)
      return '${difference.inHours} ${difference.inHours == 1 ? 'hour' : 'hours'} ago';
    if (difference.inDays == 1) return 'Yesterday';
    if (difference.inDays < 7) return '${difference.inDays} days ago';
    if (difference.inDays < 14) return '1 week ago';
    if (difference.inDays < 30)
      return '${(difference.inDays / 7).floor()} weeks ago';
    if (difference.inDays < 60) return '1 month ago';
    if (difference.inDays < 365)
      return '${(difference.inDays / 30).floor()} months ago';
    return '${(difference.inDays / 365).floor()} years ago';
  }
}
