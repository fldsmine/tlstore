import 'package:flutter/material.dart';

import 'activity_transaction_tile.dart';

class ActivityGroupedList extends StatelessWidget {
  const ActivityGroupedList({
    super.key,
    required this.transactions,
    required this.displayAmount,
  });

  final List<Map<String, dynamic>> transactions;
  final String Function(Map<String, dynamic>) displayAmount;

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final transaction in transactions) {
      final date = DateTime.tryParse(
        (transaction['created_at'] ?? '').toString(),
      )?.toLocal();
      final key = date == null
          ? 'unknown'
          : '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
      groups.putIfAbsent(key, () => []).add(transaction);
    }
    final keys = groups.keys.toList()..sort((a, b) => b.compareTo(a));

    return SliverList.builder(
      itemCount: keys.length,
      itemBuilder: (context, index) {
        final key = keys[index];
        final items = groups[key]!;
        final colors = Theme.of(context).colorScheme;
        return Padding(
          padding: const EdgeInsets.only(bottom: 13),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(3, 8, 3, 8),
                child: Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: colors.primary,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _dateLabel(key),
                      style: Theme.of(context).textTheme.titleSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const Spacer(),
                    Text(
                      '${items.length} ${items.length == 1 ? 'record' : 'records'}',
                      style: Theme.of(context).textTheme.labelSmall
                          ?.copyWith(color: colors.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
              Column(
                children: [
                  for (
                    var itemIndex = 0;
                    itemIndex < items.length;
                    itemIndex++
                  ) ...[
                    if (itemIndex > 0) const SizedBox(height: 10),
                    ActivityTransactionTile(
                      transaction: items[itemIndex],
                      displayAmount: displayAmount(items[itemIndex]),
                    ),
                  ],
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  String _dateLabel(String key) {
    if (key == 'unknown') return 'Date unavailable';
    final date = DateTime.tryParse(key);
    if (date == null) return 'Date unavailable';
    final today = DateTime.now();
    final todayDate = DateTime(today.year, today.month, today.day);
    final day = DateTime(date.year, date.month, date.day);
    if (day == todayDate) return 'Today';
    if (day == todayDate.subtract(const Duration(days: 1))) return 'Yesterday';
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
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }
}
