import 'package:flutter/material.dart';

class VasServicesSection extends StatelessWidget {
  const VasServicesSection({super.key, required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final services = data['services'] is List
        ? (data['services'] as List)
              .whereType<Map>()
              .map(
                (row) =>
                    row.map((key, value) => MapEntry(key.toString(), value)),
              )
              .toList()
        : <Map<String, dynamic>>[];

    if (services.isEmpty) return const SizedBox.shrink();

    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final active = data['enabled'] == true;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeading(context, services.length),

          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 760 ? 3 : 2;
              const spacing = 12.0;

              final itemWidth =
                  (constraints.maxWidth - spacing * (columns - 1)) / columns;

              return Wrap(
                spacing: spacing,
                runSpacing: spacing,
                children: [
                  for (var index = 0; index < services.length; index++)
                    SizedBox(
                      width:
                          index == services.length - 1 && services.length.isOdd
                          ? constraints.maxWidth
                          : itemWidth,
                      child: _ServiceCard(
                        service: services[index],
                        isAvailable:
                            active && services[index]['available'] == true,
                        expanded:
                            index == services.length - 1 &&
                            services.length.isOdd,
                      ),
                    ),
                ],
              );
            },
          ),

          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _sectionHeading(BuildContext context, int count) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 12, 2, 18),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'EVERYDAY SERVICES',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.primary,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.3,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  'Value added services',
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.5,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  'Take care of everyday payments and essential services in one place.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
            decoration: BoxDecoration(
              color: colors.primaryContainer,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              children: [
                Text(
                  '$count',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: colors.onPrimaryContainer,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  'SERVICES',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.onPrimaryContainer,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({
    required this.service,
    required this.isAvailable,
    this.expanded = false,
  });

  final Map<String, dynamic> service;
  final bool isAvailable;
  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final icon = switch (service['icon']?.toString()) {
      'phone_android' => Icons.phone_android_rounded,
      'wifi' => Icons.wifi_rounded,
      'tv' => Icons.live_tv_rounded,
      'bolt' => Icons.bolt_rounded,
      'school' => Icons.school_rounded,
      _ => Icons.miscellaneous_services_rounded,
    };

    final title = service['label']?.toString() ?? 'Service';
    final description = service['description']?.toString() ?? '';

    final statusColor = isAvailable ? colors.tertiary : colors.onSurfaceVariant;

    return Container(
      constraints: BoxConstraints(minHeight: expanded ? 120 : 158),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: .8)),
      ),
      child: expanded
          ? _expandedContent(context, icon, title, description, statusColor)
          : _normalContent(context, icon, title, description, statusColor),
    );
  }

  Widget _iconBox(BuildContext context, IconData icon) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: 46,
      height: 46,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            colors.primaryContainer,
            colors.primaryContainer.withValues(alpha: .55),
          ],
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      alignment: Alignment.center,
      child: Icon(icon, size: 24, color: colors.onPrimaryContainer),
    );
  }

  Widget _status(BuildContext context, Color statusColor) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: statusColor.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isAvailable ? Icons.check_circle_rounded : Icons.schedule_rounded,
            size: 12,
            color: statusColor,
          ),
          const SizedBox(width: 4),
          Text(
            isAvailable ? 'Available' : 'Coming soon',
            style: theme.textTheme.labelSmall?.copyWith(
              color: statusColor,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _normalContent(
    BuildContext context,
    IconData icon,
    String title,
    String description,
    Color statusColor,
  ) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _iconBox(context, icon),
            const Spacer(),
            Flexible(child: _status(context, statusColor)),
          ],
        ),
        const SizedBox(height: 13),
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          description,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.bodySmall?.copyWith(
            color: colors.onSurfaceVariant,
            height: 1.4,
          ),
        ),
      ],
    );
  }

  Widget _expandedContent(
    BuildContext context,
    IconData icon,
    String title,
    String description,
    Color statusColor,
  ) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Row(
      children: [
        _iconBox(context, icon),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                description,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        _status(context, statusColor),
      ],
    );
  }
}
