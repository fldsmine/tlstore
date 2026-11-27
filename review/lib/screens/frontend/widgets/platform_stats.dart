import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'anim/anim_kit.dart';

class PlatformStats extends StatelessWidget {
  final List<Map<String, dynamic>> stats;

  const PlatformStats({super.key, required this.stats});

  @override
  Widget build(BuildContext context) {
    if (stats.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeading(
          eyebrow: '',
          title: 'Platform Stats',
          description: 'Whats keeps us outstanding',
          trailing: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: colors.tertiary.withValues(alpha: .3),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Floating(
                  amplitude: 2.5,
                  duration: const Duration(seconds: 2),
                  child: Icon(Icons.trending_up_rounded, size: 15, color: colors.tertiary),
                ),
                const SizedBox(width: 5),
                Text(
                  'Our Growth',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.tertiary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 760 ? 4 : 2;
              final width = (constraints.maxWidth - 12 * (columns - 1)) / columns;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  for (var i = 0; i < stats.length; i++)
                    SizedBox(
                      width: width,
                      child: Reveal(
                        delay: Duration(milliseconds: 100 * (i % columns)),
                        scale: .85,
                        child: _tile(context, stats[i], i),
                      ),
                    ),
                ],
              );
            },
          ),
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _tile(BuildContext context, Map<String, dynamic> stat, int index) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final accent = [colors.primary, colors.secondary, colors.tertiary][index % 3];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 52,
            height: 52,
            child: Loop(
              duration: const Duration(seconds: 5),
              builder: (context, t, child) => CustomPaint(
                painter: _RingPainter(t, accent),
                child: child,
              ),
              child: Center(
                child: Icon(_statIcon(stat['icon']?.toString()), color: accent, size: 22),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CountUpText(
                  value: _text(stat['value'], '—'),
                  style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                Text(
                  _text(stat['label'], 'Platform activity'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  IconData _statIcon(String? key) => switch (key?.toLowerCase()) {
    'users' => Icons.groups_rounded,
    'image' => Icons.task_alt_rounded,
    'dollar' => Icons.savings_rounded,
    'globe' => Icons.public_rounded,
    _ => Icons.insights_rounded,
  };
}

/// A faint track with a comet-like arc sweeping around the icon.
class _RingPainter extends CustomPainter {
  _RingPainter(this.t, this.color);

  final double t;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final inner = rect.deflate(3);
    canvas.drawCircle(
      rect.center,
      inner.width / 2,
      Paint()..color = color.withValues(alpha: .14),
    );
    canvas.drawCircle(
      rect.center,
      inner.width / 2,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2
        ..color = color.withValues(alpha: .25),
    );
    canvas.drawArc(
      inner,
      2 * math.pi * t,
      math.pi * .7,
      false,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round
        ..color = color,
    );
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.t != t || old.color != color;
}
