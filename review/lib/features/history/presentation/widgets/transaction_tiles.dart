import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'activity_transaction_details_sheet.dart';

class TransactionTiles extends StatelessWidget {
  const TransactionTiles({
    super.key,
    required this.transaction,
    required this.displayAmount,
    this.compact = false,
    this.preferDescriptionTitle = false,

    this.margin = const EdgeInsets.only(bottom: 8),
    this.sizeScale = 0.82,
    this.onTap,
  });

  final Map<String, dynamic> transaction;
  final String displayAmount;
  final bool compact;
  final bool preferDescriptionTitle;

  /// Space around the tile.
  final EdgeInsetsGeometry margin;

  /// Uniformly scales every internal dimension. Lower this value for smaller tiles.
  final double sizeScale;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final rawStatus =
        transaction['status']?.toString().toLowerCase() ?? 'unknown';
    final status = switch (rawStatus) {
      'success' || 'completed' => 'approved',
      _ => rawStatus,
    };
    final theme = _themeFor(status);
    return Container(
      margin: margin,
      child: LayoutBuilder(
        builder: (context, constraints) {
          // Respond to narrow layouts, then apply the requested uniform size.
          final responsiveScale = (constraints.maxWidth / 886)
              .clamp(0.72, 1.0)
              .toDouble();
          final scale =
              responsiveScale *
              sizeScale.clamp(0.5, 1.2).toDouble() *
              (compact ? 0.82 : 1.0);

          final tileHeight = 118.0 * scale;
          final radius = 26.0 * scale;
          final iconSize = 70.0 * scale;
          final rightColumnWidth = 135.0 * scale;
          final accentInset = 12.0 * scale;

          final card = Container(
            width: constraints.maxWidth - accentInset,
            height: tileHeight,
            decoration: BoxDecoration(
              color: theme.background,
              borderRadius: BorderRadius.all(Radius.circular(radius)),
              border: theme.solidBorderColor == null
                  ? null
                  : Border.all(
                      color: theme.solidBorderColor!,
                      width: 2 * scale,
                    ),
            ),
            child: theme.dashedBorder
                ? CustomPaint(
                    painter: _DashedBorderPainter(
                      color: theme.borderColor,
                      strokeWidth: 2 * scale,
                      dashWidth: 5 * scale,
                      gapWidth: 4 * scale,
                      borderRadius: BorderRadius.all(Radius.circular(radius)),
                    ),
                    child: _buildContents(
                      context,
                      theme,
                      scale,
                      iconSize,
                      rightColumnWidth,
                    ),
                  )
                : _buildContents(
                    context,
                    theme,
                    scale,
                    iconSize,
                    rightColumnWidth,
                  ),
          );

          return Stack(
            clipBehavior: Clip.none,
            children: [
              if (theme.accentColor != null)
                Positioned(
                  left: 0,
                  top: 6 * scale,
                  bottom: 6 * scale,
                  child: Container(
                    width: 28 * scale,
                    decoration: BoxDecoration(
                      color: theme.accentColor,
                      borderRadius: BorderRadius.all(
                        Radius.circular(14 * scale),
                      ),
                    ),
                  ),
                ),
              Padding(
                padding: EdgeInsets.only(
                  left: status == 'rejected' ? accentInset : 0,
                ),
                child: card,
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildContents(
    BuildContext context,
    _TransactionTheme theme,
    double scale,
    double iconSize,
    double rightColumnWidth,
  ) {
    final direction = transaction['direction']?.toString().toLowerCase();
    final description = transaction['description']?.toString().trim() ?? '';
    final type =
        '${transaction['type'] ?? ''} ${transaction['wallet'] ?? ''} $description'
            .toLowerCase();
    final title = preferDescriptionTitle && description.isNotEmpty
        ? description
        : _title(type, direction, description);
    final category = _category(type, direction);
    final time = _relativeDate(
      transaction['created_at'] ?? transaction['date'],
    );
    final rawStatus = transaction['status']?.toString().toLowerCase();
    final status = switch (rawStatus) {
      'success' || 'completed' => 'approved',
      _ => rawStatus,
    };
    final icon = const ['pending', 'rejected', 'approved'].contains(status)
        ? theme.icon
        : _iconFor(type, direction);
    final handleTap =
        onTap ??
        () => showActivityTransactionDetailsSheet(
          context: context,
          transaction: transaction,
          displayAmount: displayAmount,
        );
    final amountPrefix = direction == 'credit'
        ? '+'
        : direction == 'debit'
        ? '−'
        : '';

    Widget content = Padding(
      padding: EdgeInsets.fromLTRB(
        16 * scale,
        16 * scale,
        15 * scale,
        16 * scale,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: iconSize,
            height: iconSize,
            decoration: BoxDecoration(
              color: theme.iconBackground,
              borderRadius: BorderRadius.all(Radius.circular(26 * scale)),
            ),
            child: Icon(icon, color: theme.iconColor, size: 44 * scale),
          ),
          SizedBox(width: 14 * scale),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: theme.titleColor,
                    fontSize: 22 * scale,
                    fontWeight: FontWeight.w700,
                    height: 1.15,
                  ),
                ),
                SizedBox(height: 9 * scale),
                Text(
                  '$category · $time',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: theme.subtitleColor,
                    fontSize: 17 * scale,
                    fontWeight: FontWeight.w400,
                    height: 1.2,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 6 * scale),
          SizedBox(
            width: rightColumnWidth,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '$amountPrefix$displayAmount',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: theme.amountColor,
                    fontSize: 21 * scale,
                    fontWeight: FontWeight.w700,
                    height: 1.1,
                    decoration: theme.strikeAmount
                        ? TextDecoration.lineThrough
                        : TextDecoration.none,
                    decorationColor: theme.amountColor,
                    decorationThickness: 2 * scale,
                  ),
                ),
                SizedBox(height: 8 * scale),
                _StatusBadge(theme: theme, scale: scale),
              ],
            ),
          ),
        ],
      ),
    );

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: handleTap,
        borderRadius: BorderRadius.all(Radius.circular(26 * scale)),
        child: content,
      ),
    );
  }

  static String _title(String type, String? direction, String description) {
    if (description.isNotEmpty) return description;
    if (type.contains('withdraw')) return 'Withdrawal';
    if (type.contains('referral')) return 'Referral bonus';
    if (type.contains('review')) return 'Product review';
    if (type.contains('social_follow')) return 'Social follow reward';
    if (type.contains('share')) return 'Ad share reward';
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

  static IconData _iconFor(String type, String? direction) {
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
    if (difference.inHours < 1) {
      return '${difference.inMinutes} ${difference.inMinutes == 1 ? 'minute' : 'minutes'} ago';
    }
    if (difference.inDays < 1) {
      return '${difference.inHours} ${difference.inHours == 1 ? 'hour' : 'hours'} ago';
    }
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

class _StatusBadge extends StatefulWidget {
  const _StatusBadge({required this.theme, required this.scale});

  final _TransactionTheme theme;
  final double scale;

  @override
  State<_StatusBadge> createState() => _StatusBadgeState();
}

class _StatusBadgeState extends State<_StatusBadge>
    with SingleTickerProviderStateMixin {
  AnimationController? _pulseController;

  @override
  void initState() {
    super.initState();
    if (widget.theme.showBadgeDot) {
      _pulseController = AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 1400),
      )..repeat();
    }
  }

  @override
  void dispose() {
    _pulseController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scale = widget.scale;

    return Container(
      width: widget.theme.badgeWidth * scale,
      height: 44 * scale,
      alignment: Alignment.center,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: widget.theme.badgeBackground,
        borderRadius: BorderRadius.all(Radius.circular(22 * scale)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (widget.theme.showBadgeDot) ...[
            _buildPulsingDot(),
            SizedBox(width: 9 * scale),
          ],
          Text(
            widget.theme.label,
            style: TextStyle(
              color: widget.theme.badgeForeground,
              fontSize: 16 * scale,
              fontWeight: FontWeight.w700,
              height: 1,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPulsingDot() {
    final scale = widget.scale;
    final controller = _pulseController!;
    const dotColor = Color(0xFFF3F5F3);

    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final phase = controller.value;
        final haloScale = 1.0 + phase * 2.2;
        final haloOpacity = (1.0 - phase) * 0.45;
        final dotScale = 1.0 + 0.12 * math.sin(math.pi * phase);

        return SizedBox(
          width: 18 * scale,
          height: 18 * scale,
          child: Stack(
            alignment: Alignment.center,
            clipBehavior: Clip.none,
            children: [
              Transform.scale(
                scale: haloScale,
                child: Opacity(
                  opacity: haloOpacity,
                  child: Container(
                    width: 8 * scale,
                    height: 8 * scale,
                    decoration: const BoxDecoration(
                      color: dotColor,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
              ),
              Transform.scale(
                scale: dotScale,
                child: Container(
                  width: 8 * scale,
                  height: 8 * scale,
                  decoration: const BoxDecoration(
                    color: dotColor,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _TransactionTheme {
  const _TransactionTheme({
    required this.background,
    required this.titleColor,
    this.subtitleColor = const Color(0xFF5B655F),
    required this.amountColor,
    required this.iconBackground,
    required this.iconColor,
    required this.icon,
    required this.label,
    required this.badgeBackground,
    required this.badgeForeground,
    required this.badgeWidth,
    this.borderColor = Colors.transparent,
    this.solidBorderColor,
    this.dashedBorder = false,
    this.accentColor,
    this.showBadgeDot = false,
    this.strikeAmount = false,
  });

  final Color background;
  final Color titleColor;
  final Color subtitleColor;
  final Color amountColor;
  final Color iconBackground;
  final Color iconColor;
  final IconData icon;
  final String label;
  final Color badgeBackground;
  final Color badgeForeground;
  final double badgeWidth;
  final Color borderColor;
  final Color? solidBorderColor;
  final bool dashedBorder;
  final Color? accentColor;
  final bool showBadgeDot;
  final bool strikeAmount;
}

_TransactionTheme _themeFor(String status) {
  switch (status) {
    case 'pending':
      return const _TransactionTheme(
        background: Color(0xFFFFF6DF),
        titleColor: Color(0xFF151A17),
        amountColor: Color(0xFF8A5A00),
        iconBackground: Color(0xFFFBE3A1),
        iconColor: Color(0xFF8A5A00),
        icon: Icons.schedule,
        label: 'Pending',
        badgeBackground: Color(0xFF8A5A00),
        badgeForeground: Color(0xFFF3F5F3),
        badgeWidth: 145,
        borderColor: Color(0xFFE3A62B),
        dashedBorder: true,
        showBadgeDot: true,
      );
    case 'rejected':
      return const _TransactionTheme(
        background: Color(0xFFFDECEB),
        titleColor: Color(0xFF5B655F),
        amountColor: Color(0xFFD6403A),
        iconBackground: Color(0xFFF6C6C3),
        iconColor: Color(0xFFB3261E),
        icon: Icons.close,
        label: 'Rejected',
        badgeBackground: Color(0xFFB3261E),
        badgeForeground: Color(0xFFF3F5F3),
        badgeWidth: 123,
        accentColor: Color(0xFFD6403A),
        strikeAmount: true,
      );
    case 'approved':
      return const _TransactionTheme(
        background: Color(0xFFEAF3EE),
        titleColor: Color(0xFF151A17),
        amountColor: Color(0xFF17593F),
        iconBackground: Color(0xFFE1EBE5),
        iconColor: Color(0xFF17593F),
        icon: Icons.check,
        label: 'Approved',
        badgeBackground: Color(0xFF17593F),
        badgeForeground: Color(0xFFF3F5F3),
        badgeWidth: 138,
        solidBorderColor: Color(0xFFB9D5C5),
      );
    default: //maybe this default color should be changed
      return _TransactionTheme(
        background: Color(0xFFEAF3EE),
        titleColor: Color(0xFF151A17),
        amountColor: Color(0xFF17593F),
        iconBackground: Color(0xFFE1EBE5),
        iconColor: Color(0xFF17593F),
        icon: Icons.check,
        label: status,
        badgeBackground: Color(0xFF17593F),
        badgeForeground: Color(0xFFF3F5F3),
        badgeWidth: 138,
        solidBorderColor: Color(0xFFB9D5C5),
      );
  }
}

class _DashedBorderPainter extends CustomPainter {
  const _DashedBorderPainter({
    required this.color,
    required this.strokeWidth,
    required this.dashWidth,
    required this.gapWidth,
    required this.borderRadius,
  });

  final Color color;
  final double strokeWidth;
  final double dashWidth;
  final double gapWidth;
  final BorderRadius borderRadius;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final path = Path()
      ..addRRect(borderRadius.toRRect(rect).deflate(strokeWidth / 2));

    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.butt;

    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final end = distance + dashWidth > metric.length
            ? metric.length
            : distance + dashWidth;
        canvas.drawPath(metric.extractPath(distance, end), paint);
        distance = end + gapWidth;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedBorderPainter oldDelegate) {
    return color != oldDelegate.color ||
        strokeWidth != oldDelegate.strokeWidth ||
        dashWidth != oldDelegate.dashWidth ||
        gapWidth != oldDelegate.gapWidth ||
        borderRadius != oldDelegate.borderRadius;
  }
}
