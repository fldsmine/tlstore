import 'package:flutter/material.dart';

import 'anim/anim_kit.dart';
import 'anim/illustrations.dart';

class VasServicesSection extends StatelessWidget {
  const VasServicesSection({super.key, required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final services = data['services'] is List
        ? (data['services'] as List)
              .whereType<Map>()
              .map((row) => row.map((k, v) => MapEntry(k.toString(), v)))
              .toList()
        : <Map<String, dynamic>>[];

    if (services.isEmpty) return const SizedBox.shrink();

    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final active = data['enabled'] == true;
    final accents = [colors.primary, colors.secondary, colors.tertiary];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SectionHeading(
            eyebrow: 'Everyday services',
            title: 'Value added services',
            description:
                'Take care of everyday payments and essential services in one place.',
            padding: const EdgeInsets.fromLTRB(2, 12, 2, 18),
            trailing: Padding(
              padding: const EdgeInsets.only(left: 10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  children: [
                    CountUpText(
                      value: '${services.length}',
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
            ),
          ),
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
                  for (var i = 0; i < services.length; i++)
                    SizedBox(
                      width: i == services.length - 1 && services.length.isOdd
                          ? constraints.maxWidth
                          : itemWidth,
                      child: Reveal(
                        delay: Duration(milliseconds: 90 * (i % columns)),
                        child: _ServiceCard(
                          service: services[i],
                          accent: accents[i % accents.length],
                          isAvailable: active && services[i]['available'] == true,
                          expanded:
                              i == services.length - 1 && services.length.isOdd,
                        ),
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
}

class _ServiceCard extends StatefulWidget {
  const _ServiceCard({
    required this.service,
    required this.accent,
    required this.isAvailable,
    this.expanded = false,
  });

  final Map<String, dynamic> service;
  final Color accent;
  final bool isAvailable;
  final bool expanded;

  @override
  State<_ServiceCard> createState() => _ServiceCardState();
}

class _ServiceCardState extends State<_ServiceCard> {
  bool _lifted = false;

  void _set(bool v) {
    if (_lifted != v) setState(() => _lifted = v);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final accent = widget.accent;
    final title = widget.service['label']?.toString() ?? 'Service';
    final description = widget.service['description']?.toString() ?? '';
    final statusColor = widget.isAvailable ? colors.tertiary : colors.onSurfaceVariant;

    final art = Container(
      width: widget.expanded ? 88 : double.infinity,
      height: widget.expanded ? 88 : 96,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [accent.withValues(alpha: .22), accent.withValues(alpha: .05)],
        ),
      ),
      alignment: Alignment.center,
      child: Floating(
        amplitude: 3,
        duration: const Duration(seconds: 3),
        child: ServiceArt(
          kind: ServiceArt.kindFor(widget.service),
          color: accent,
          size: widget.expanded ? 66 : 72,
        ),
      ),
    );

    final text = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        Text(
          description,
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.bodySmall?.copyWith(
            color: colors.onSurfaceVariant,
            height: 1.4,
          ),
        ),
        const SizedBox(height: 10),
        _status(theme, statusColor),
      ],
    );

    return MouseRegion(
      onEnter: (_) => _set(true),
      onExit: (_) => _set(false),
      child: GestureDetector(
        onTapDown: (_) => _set(true),
        onTapUp: (_) => _set(false),
        onTapCancel: () => _set(false),
        child: AnimatedScale(
          scale: _lifted ? 1.03 : 1,
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOut,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: colors.surfaceContainerLow,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(
                color: _lifted
                    ? accent.withValues(alpha: .8)
                    : colors.outlineVariant.withValues(alpha: .8),
              ),
              boxShadow: [
                BoxShadow(
                  color: accent.withValues(alpha: _lifted ? .28 : 0),
                  blurRadius: 22,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: widget.expanded
                ? Row(
                    children: [
                      art,
                      const SizedBox(width: 14),
                      Expanded(child: text),
                    ],
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [art, const SizedBox(height: 12), text],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _status(ThemeData theme, Color statusColor) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: statusColor.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (widget.isAvailable)
            Loop(
              duration: const Duration(milliseconds: 1800),
              builder: (context, t, _) => Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  color: statusColor,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: statusColor.withValues(alpha: .6 * (1 - t)),
                      blurRadius: 2 + 8 * t,
                      spreadRadius: 4 * t,
                    ),
                  ],
                ),
              ),
            )
          else
            Icon(Icons.schedule_rounded, size: 12, color: statusColor),
          const SizedBox(width: 6),
          Text(
            widget.isAvailable ? 'Available' : 'Coming soon',
            style: theme.textTheme.labelSmall?.copyWith(
              color: statusColor,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
