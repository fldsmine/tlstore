import 'package:flutter/material.dart';

class NotificationTypeList extends StatelessWidget {
  const NotificationTypeList({
    super.key,
    required this.items,
    required this.selectedType,
    required this.onSelectType,
    required this.onRefresh,
    required this.onOpen,
    required this.onMarkAllRead,
  });

  final List<Map<String, dynamic>> items;
  final String selectedType;
  final ValueChanged<String> onSelectType;
  final Future<void> Function() onRefresh;
  final ValueChanged<Map<String, dynamic>> onOpen;
  final VoidCallback onMarkAllRead;

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

  int _unreadCount(String type) => items
      .where(
        (item) =>
            item['read_at'] == null &&
            (type == 'all' || _category(item['type']?.toString()) == type),
      )
      .length;

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    final colors = Theme.of(context).colorScheme;
    final unreadCount = _unreadCount('all');
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
                label: Text('$label (${_unreadCount(key)})'),
                selected: selected,
                onSelected: (_) => onSelectType(key),
                showCheckmark: false,
                selectedColor: colors.primary,
                backgroundColor: Theme.of(context).cardColor,
                labelStyle: TextStyle(
                  color: selected ? colors.onPrimary : colors.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                  fontSize: 11,
                ),
                side: BorderSide(
                  color: selected ? colors.primary : colors.outlineVariant,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              );
            },
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 2, 16, 10),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  unreadCount == 0
                      ? "You're all caught up"
                      : '$unreadCount unread',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: unreadCount == 0 ? null : onMarkAllRead,
                icon: const Icon(Icons.done_all_rounded, size: 18),
                label: const Text('Mark all as read'),
                style: TextButton.styleFrom(
                  foregroundColor: colors.primary,
                  backgroundColor: colors.primaryContainer.withValues(
                    alpha: .45,
                  ),
                  disabledForegroundColor: colors.onSurfaceVariant.withValues(
                    alpha: .45,
                  ),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 9,
                  ),
                  shape: const StadiumBorder(),
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: onRefresh,
            child: filtered.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      const SizedBox(height: 120),
                      Center(
                        child: Text(
                          items.isEmpty
                              ? 'No notifications here yet.'
                              : 'No ${_typeLabel(selectedType).toLowerCase()} notifications.',
                          style: TextStyle(color: colors.onSurfaceVariant),
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
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final unread = item['read_at'] == null;
    final date = DateTime.tryParse(item['created_at']?.toString() ?? '')
        ?.toLocal();
    final dateLabel = date == null
        ? null
        : '${date.day}/${date.month}/${date.year} · ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    final background = unread
        ? colors.onSecondary
        : Color.alphaBlend(colors.onSurface.withValues(alpha: .035), colors.surface);
    return Material(
      color: background,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(
          color: unread
              ? Color.alphaBlend(colors.primary.withValues(alpha: .48), colors.outlineVariant)
              : colors.outlineVariant,
        ),
      ),
      elevation: unread ? 1 : 0,
      shadowColor: colors.primary.withValues(alpha: .16),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          if (unread)
            Positioned(
              left: 0,
              top: 14,
              bottom: 14,
              child: Container(
                width: 5,
                decoration: BoxDecoration(
                  color: colors.primary,
                  borderRadius: const BorderRadius.horizontal(
                    right: Radius.circular(4),
                  ),
                ),
              ),
            ),
          InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 16, 16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Icon(
                      unread
                          ? Icons.notifications_active_outlined
                          : Icons.notifications_none_rounded,
                      color: unread ? colors.primary : colors.onSurfaceVariant,
                      size: 25,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text.rich(
                          TextSpan(
                            children: [
                              TextSpan(
                                text: item['title']?.toString() ?? 'Update',
                                style: TextStyle(
                                  color: unread
                                      ? colors.onSurface
                                      : colors.onSurfaceVariant,
                                  fontWeight: unread
                                      ? FontWeight.w800
                                      : FontWeight.w500,
                                ),
                              ),
                              if (unread)
                                WidgetSpan(
                                  alignment: PlaceholderAlignment.middle,
                                  child: Padding(
                                    padding: const EdgeInsets.only(left: 8),
                                    child: _NewTag(
                                      color: colors.primary,
                                      foreground: colors.onPrimary,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(
                            height: 1.3,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          item['body']?.toString() ??
                              item['message']?.toString() ??
                              '',
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: unread
                                ? colors.onSurface
                                : colors.onSurfaceVariant,
                            height: 1.45,
                          ),
                        ),
                        if (dateLabel != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 10),
                            child: Text(
                              dateLabel,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: unread
                                    ? colors.primary
                                    : colors.onSurfaceVariant,
                                fontWeight: unread
                                    ? FontWeight.w600
                                    : FontWeight.normal,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (unread)
                    Padding(
                      padding: const EdgeInsets.only(left: 10, top: 6),
                      child: Container(
                        width: 11,
                        height: 11,
                        decoration: BoxDecoration(
                          color: colors.primary,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NewTag extends StatelessWidget {
  const _NewTag({required this.color, required this.foreground});

  final Color color;
  final Color foreground;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text(
      'New',
      style: Theme.of(context).textTheme.labelSmall?.copyWith(
        color: foreground,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}
