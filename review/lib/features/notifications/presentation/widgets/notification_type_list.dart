import 'package:flutter/material.dart';

import '../../../../widgets/auth_components.dart';

class NotificationTypeList extends StatelessWidget {
  const NotificationTypeList({
    super.key,
    required this.items,
    required this.selectedType,
    required this.onSelectType,
    required this.onRefresh,
    required this.onOpen,
  });

  final List<Map<String, dynamic>> items;
  final String selectedType;
  final ValueChanged<String> onSelectType;
  final Future<void> Function() onRefresh;
  final ValueChanged<Map<String, dynamic>> onOpen;

  static const _types = <(String, String)>[
    ('all', 'All'),
    ('account', 'Account'),
    ('reviews', 'App reviews'),
    ('ad_shares', 'Ad shares'),
    ('payments', 'Payments'),
    ('withdrawals', 'Withdrawals'),
    ('referrals', 'Referrals'),
    ('system', 'System'),
  ];

  List<Map<String, dynamic>> get _filtered => selectedType == 'all'
      ? items
      : items
            .where(
              (item) => _category(item['type']?.toString()) == selectedType,
            )
            .toList();

  int _count(String type) => type == 'all'
      ? items.length
      : items
            .where((item) => _category(item['type']?.toString()) == type)
            .length;

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    return Column(
      children: [
        SizedBox(
          height: 48,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            scrollDirection: Axis.horizontal,
            itemCount: _types.length,
            separatorBuilder: (_, _) => const SizedBox(width: 7),
            itemBuilder: (context, index) {
              final (key, label) = _types[index];
              final selected = key == selectedType;
              return ChoiceChip(
                label: Text('$label (${_count(key)})'),
                selected: selected,
                onSelected: (_) => onSelectType(key),
                showCheckmark: false,
                selectedColor: brandGreen,
                backgroundColor: Theme.of(context).cardColor,
                labelStyle: TextStyle(
                  color: selected ? Colors.white : mutedInk,
                  fontWeight: FontWeight.w700,
                  fontSize: 11,
                ),
                side: BorderSide(
                  color: selected ? brandGreen : const Color(0xFFE2E8E3),
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              );
            },
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: onRefresh,
            child: filtered.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      const SizedBox(height: 145),
                      Center(
                        child: Text(
                          items.isEmpty
                              ? 'You are all caught up.'
                              : 'No ${_typeLabel(selectedType).toLowerCase()} notifications.',
                          style: const TextStyle(color: mutedInk),
                        ),
                      ),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
                    physics: const AlwaysScrollableScrollPhysics(),
                    itemCount: filtered.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 9),
                    itemBuilder: (context, index) => _NotificationCard(
                      item: filtered[index],
                      onTap: () => onOpen(filtered[index]),
                    ),
                  ),
          ),
        ),
      ],
    );
  }

  static String _category(String? type) {
    final value = (type ?? '').toLowerCase();
    if (value.contains('review')) return 'reviews';
    if (value.contains('share') || value.contains('ad_')) return 'ad_shares';
    if (value.contains('withdraw')) return 'withdrawals';
    if (value.contains('deposit') ||
        value.contains('payment') ||
        value.contains('topup')) {
      return 'payments';
    }
    if (value.contains('referral')) return 'referrals';
    if (value.contains('account') ||
        value.contains('security') ||
        value.contains('auth')) {
      return 'account';
    }
    return 'system';
  }

  static String _typeLabel(String type) => switch (type) {
    'account' => 'Account',
    'reviews' => 'App review',
    'ad_shares' => 'Ad share',
    'payments' => 'Payment',
    'withdrawals' => 'Withdrawal',
    'referrals' => 'Referral',
    'system' => 'System',
    _ => 'All',
  };
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.item, required this.onTap});
  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final unread = item['read_at'] == null;
    final date = DateTime.tryParse(item['created_at']?.toString() ?? '')
        ?.toLocal();
    final dateLabel = date == null
        ? null
        : '${date.day}/${date.month}/${date.year} · ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(15),
      child: Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(15),
          border: Border.all(
            color: unread ? const Color(0xFFB8D6C1) : const Color(0xFFE5E9E4),
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              unread
                  ? Icons.notifications_active_outlined
                  : Icons.notifications_none_rounded,
              color: brandGreen,
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item['title']?.toString() ?? 'Update',
                    style: const TextStyle(
                      color: ink,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    item['body']?.toString() ??
                        item['message']?.toString() ??
                        '',
                    style: const TextStyle(
                      color: mutedInk,
                      fontSize: 12,
                      height: 1.4,
                    ),
                  ),
                  if (dateLabel != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        dateLabel,
                        style: const TextStyle(color: mutedInk, fontSize: 10),
                      ),
                    ),
                ],
              ),
            ),
            if (unread)
              const Padding(
                padding: EdgeInsets.only(left: 8, top: 5),
                child: CircleAvatar(radius: 4, backgroundColor: brandGreen),
              ),
          ],
        ),
      ),
    );
  }
}
