import 'package:flutter/material.dart';

Future<void> showActivityTransactionDetailsSheet({
  required BuildContext context,
  required Map<String, dynamic> transaction,
  required String displayAmount,
}) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  showDragHandle: true,
  backgroundColor: Theme.of(context).colorScheme.surface,
  builder: (_) => _ActivityTransactionDetailsSheet(
    transaction: transaction,
    displayAmount: displayAmount,
  ),
);

class _ActivityTransactionDetailsSheet extends StatelessWidget {
  const _ActivityTransactionDetailsSheet({
    required this.transaction,
    required this.displayAmount,
  });

  final Map<String, dynamic> transaction;
  final String displayAmount;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final direction = transaction['direction']?.toString().toLowerCase() ?? '';

    final status = transaction['status']?.toString().toLowerCase() ?? 'Unknown';
    final isPending = status == 'pending';
    final isRejected = status == 'rejected';

    final credit = direction == 'credit';
    final accent = credit ? colors.primary : colors.error;
    final description = transaction['description']?.toString().trim() ?? '';
    final rawType = transaction['type']?.toString() ?? 'transaction';
    final title = description.isNotEmpty ? description : _humanize(rawType);
    final wallet = transaction['wallet']?.toString();
    final createdAt = DateTime.tryParse(
      (transaction['created_at'] ?? transaction['date'])?.toString() ?? '',
    )?.toLocal();
    final dateLabel = createdAt == null
        ? 'Date unavailable'
        : '${_monthName(createdAt.month)} ${createdAt.day}, ${createdAt.year} · ${_time(createdAt)}';

    final details = <(String, String)>[
      ('Type', _humanize(rawType)),
      if (wallet != null && wallet.trim().isNotEmpty)
        ('Wallet', '${_humanize(wallet)} wallet'),
      ('Direction', direction.isEmpty ? '—' : _humanize(direction)),
      ('Status', _humanize(status)),
      ('Date', dateLabel),
      if (transaction['reference']?.toString().trim().isNotEmpty == true)
        ('Reference', transaction['reference'].toString()),
      if (transaction['id'] != null)
        ('Transaction ID', transaction['id'].toString()),
    ];

    return ConstrainedBox(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.sizeOf(context).height * .82,
      ),
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          22,
          8,
          22,
          24 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
					  color: isPending
					  ? colors.tertiaryContainer.withValues(alpha: .25)
					  : isRejected
						  ? colors.errorContainer.withValues(alpha: .45)
						  : colors.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(
                    credit
                        ? Icons.south_west_rounded
                        : Icons.receipt_long_rounded,
                    color: accent,
                  ),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Text(
                    'Transaction details',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Close',
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: 20),
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(
                color: colors.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 7),
            Text(
              '${credit
                  ? '+'
                  : direction == 'debit'
                  ? '−'
                  : ''}$displayAmount',
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineMedium?.copyWith(
                  color: isPending
					? colors.error.withValues(alpha: .4)
					: isRejected
						? colors.error.withValues(alpha: .6)
						: accent,
                fontWeight: FontWeight.w900,
				decoration: (isPending || isRejected)
				 ? TextDecoration.lineThrough
				 : TextDecoration.none,
                letterSpacing: -.5,
              ),
            ),
            const SizedBox(height: 22),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              decoration: BoxDecoration(
			  color: isPending
			  ? colors.tertiaryContainer.withValues(alpha: .15)
			  : isRejected
				  ? colors.errorContainer.withValues(alpha: .15)
				  : colors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: colors.outlineVariant),
              ),
              child: Column(
                children: [
                  for (var index = 0; index < details.length; index++) ...[
                    if (index > 0)
                      Divider(height: 1, color: colors.outlineVariant),
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SizedBox(
                            width: 112,
                            child: Text(
                              details[index].$1,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: colors.onSurfaceVariant,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          Expanded(
                            child: Text(
                              details[index].$2,
                              textAlign: TextAlign.end,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: colors.onSurface,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (description.isNotEmpty && description != title) ...[
              const SizedBox(height: 16),
              Text(
                'Description',
                style: theme.textTheme.labelLarge?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(description, style: theme.textTheme.bodyMedium),
            ],
          ],
        ),
      ),
    );
  }

  String _humanize(String value) => value
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');

  String _monthName(int month) => const [
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
  ][month - 1];

  String _time(DateTime date) {
    final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
    final minute = date.minute.toString().padLeft(2, '0');
    return '$hour:$minute ${date.hour >= 12 ? 'PM' : 'AM'}';
  }
}
