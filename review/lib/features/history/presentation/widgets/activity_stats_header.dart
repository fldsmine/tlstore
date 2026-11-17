import 'package:flutter/material.dart';

class ActivityStatsHeader extends StatelessWidget {
  const ActivityStatsHeader({
    super.key,
    required this.total,
    required this.thisMonth,
    required this.earned,
    required this.spent,
  });

  final int total;
  final int thisMonth;
  final String earned;
  final String spent;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [colors.primary, colors.tertiary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: [
          Expanded(
            child: _Stat(
              label: 'Total',
              value: '$total',
              icon: Icons.receipt_long_rounded,
            ),
          ),
          _divider(),
          Expanded(
            child: _Stat(
              label: 'This month',
              value: '$thisMonth',
              icon: Icons.calendar_month_rounded,
            ),
          ),
          _divider(),
          Expanded(
            child: _Stat(
              label: 'Earned',
              value: earned,
              icon: Icons.trending_up_rounded,
            ),
          ),
          _divider(),
          Expanded(
            child: _Stat(
              label: 'Withdrawn',
              value: spent,
              icon: Icons.trending_down_rounded,
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() => Container(
    height: 46,
    width: 1,
    margin: const EdgeInsets.symmetric(horizontal: 4),
    color: Colors.white24,
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Icon(icon, color: Colors.white, size: 19),
      const SizedBox(height: 7),
      FittedBox(
        fit: BoxFit.scaleDown,
        child: Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 14,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      const SizedBox(height: 2),
      Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(color: Colors.white70, fontSize: 9),
      ),
    ],
  );
}
