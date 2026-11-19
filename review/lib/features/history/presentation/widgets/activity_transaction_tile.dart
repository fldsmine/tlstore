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
    final isApproved = status == 'approved' ||
        status == 'completed' ||
        status == 'success';
    final isDark = theme.brightness == Brightness.dark;
    final credit = direction == 'credit';
    final description = transaction['description']?.toString().trim() ?? '';
    final title = preferDescriptionTitle && description.isNotEmpty
        ? description
        : _title(type, direction, description);
    final category = _category(type, direction);
    final accent = isPending
        ? (isDark ? const Color(0xFFF1C566) : const Color(0xFF8A5A00))
        : isRejected
            ? (isDark ? const Color(0xFFFF9A94) : const Color(0xFFB3261E))
            : isApproved
                ? (isDark ? const Color(0xFF6FD1A0) : const Color(0xFF17593F))
                : credit
                    ? colors.primary
                    : colors.error;
    final background = Color.alphaBlend(
      accent.withValues(alpha: isDark ? .14 : .07),
      colors.surface,
    );
    final icon = isPending
        ? Icons.schedule_rounded
        : isRejected
            ? Icons.close_rounded
            : isApproved
                ? Icons.check_rounded
                : _icon(type, direction);
    final openDetails =
        onTap ??
        () => showActivityTransactionDetailsSheet(
          context: context,
          transaction: transaction,
          displayAmount: displayAmount,
        );

    final radius = BorderRadius.circular(20);
    final tile = Material(
      color: background,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(
          color: isRejected || isPending
              ? Colors.transparent
              : isApproved
                  ? accent.withValues(alpha: .42)
                  : colors.outlineVariant.withValues(alpha: .75),
          width: isPending || isApproved ? 1.5 : 1,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: openDetails,
        child: Padding(
          padding: EdgeInsets.symmetric(
            horizontal: compact ? 12 : 14,
            vertical: compact ? 11 : 14,
          ),
          child: Row(
            children: [
              Container(
                width: compact ? 42 : 52,
                height: compact ? 42 : 52,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: accent.withValues(
                    alpha: isDark ? .22 : .13,
                  ),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, size: 25, color: accent),
              ),
              const SizedBox(width: 14),
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
                        fontSize: compact ? 13 : 15,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '$category · ${_relativeDate(transaction['created_at'] ?? transaction['date'])}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                      fontSize: compact ? 11 : 13,
                      ),
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
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: accent,
                      fontWeight: FontWeight.w800,
                      decoration: isRejected
                          ? TextDecoration.lineThrough
                          : TextDecoration.none,
                      fontSize: compact ? 13 : 16,
                    ),
                  ),
                  if (status != null && status.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    _StatusChip(
                      label: _statusLabel(status),
                      color: accent,
                      background: Color.alphaBlend(
                        accent.withValues(
                          alpha: isDark ? .24 : .13,
                        ),
                        colors.surface,
                      ),
                      pulse: isPending,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (isRejected) {
      return ClipRRect(
        borderRadius: radius,
        child: DecoratedBox(
          decoration: BoxDecoration(
            border: Border(left: BorderSide(color: accent, width: 6)),
            borderRadius: radius,
          ),
          child: Padding(padding: const EdgeInsets.only(left: 6), child: tile),
        ),
      );
    }
    if (isPending) {
      return CustomPaint(
        foregroundPainter: _DashedRoundedBorderPainter(color: accent),
        child: tile,
      );
    }
    return tile;
  }

  static String _statusLabel(String status) {
    if (status.isEmpty) return '';
    return status[0].toUpperCase() + status.substring(1);
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

class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.color,
    required this.background,
    this.pulse = false,
  });

  final String label;
  final Color color;
  final Color background;
  final bool pulse;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (pulse) ...[
            _PulsingDot(color: color),
            const SizedBox(width: 5),
          ],
          Text(
            label,
            style: theme.textTheme.labelMedium?.copyWith(
              color: color,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _PulsingDot extends StatefulWidget {
  const _PulsingDot({required this.color});

  final Color color;

  @override
  State<_PulsingDot> createState() => _PulsingDotState();
}

class _PulsingDotState extends State<_PulsingDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1600),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
        opacity: Tween<double>(begin: 1, end: .25).animate(_controller),
        child: Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: widget.color, shape: BoxShape.circle),
        ),
      );
}

class _DashedRoundedBorderPainter extends CustomPainter {
  const _DashedRoundedBorderPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final path = Path()
      ..addRRect(RRect.fromRectAndRadius(rect.deflate(.75), const Radius.circular(20)));
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      const dashLength = 6.0;
      const gapLength = 4.0;
      while (distance < metric.length) {
        final end = (distance + dashLength).clamp(0.0, metric.length).toDouble();
        canvas.drawPath(metric.extractPath(distance, end), paint);
        distance = end + gapLength;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedRoundedBorderPainter oldDelegate) =>
      oldDelegate.color != color;
}
