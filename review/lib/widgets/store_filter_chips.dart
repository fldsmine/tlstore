import 'package:flutter/material.dart';

/// Horizontal store selector shared by the app listings and daily task views.
class StoreFilterChips extends StatelessWidget {
  const StoreFilterChips({
    super.key,
    required this.selected,
    required this.onSelected,
  });

  final String selected;
  final ValueChanged<String> onSelected;

  static const options = <(String, String)>[
    ('all', 'All stores'),
    ('play_store', 'Play Store'),
    ('app_store', 'App Store'),
    ('galaxy_store', 'Galaxy Store'),
  ];

  static bool matches(Map<String, dynamic> item, String filter) {
    if (filter == 'all') return true;
    final app = item['app'] is Map
        ? Map<String, dynamic>.from(item['app'] as Map)
        : item;
    final store = (app['store']?.toString() ?? '').toLowerCase();
    return switch (filter) {
      'play_store' => store.contains('google') || store.contains('play'),
      'app_store' =>
        store.contains('apple') ||
            store.contains('app store') ||
            store == 'ios',
      'galaxy_store' => store.contains('samsung') || store.contains('galaxy'),
      _ => true,
    };
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: options.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final option = options[index];
          final isSelected = selected == option.$1;
          return ChoiceChip(
            label: Text(option.$2),
            selected: isSelected,
            showCheckmark: false,
            onSelected: (_) => onSelected(option.$1),
            labelStyle: TextStyle(
              color: isSelected ? scheme.onPrimary : scheme.onSurfaceVariant,
              fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
            ),
            backgroundColor: scheme.surface,
            selectedColor: scheme.primary,
            side: BorderSide(
              color: isSelected ? scheme.primary : scheme.outlineVariant,
            ),
            shape: const StadiumBorder(),
            padding: const EdgeInsets.symmetric(horizontal: 10),
          );
        },
      ),
    );
  }
}
