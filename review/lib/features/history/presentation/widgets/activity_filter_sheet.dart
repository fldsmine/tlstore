import 'package:flutter/material.dart';

class ActivityFilterSheet extends StatefulWidget {
  const ActivityFilterSheet({
    super.key,
    required this.status,
    required this.fromDate,
    required this.toDate,
    required this.onApply,
  });

  final String status;
  final DateTime? fromDate;
  final DateTime? toDate;
  final void Function(String status, DateTime? fromDate, DateTime? toDate)
  onApply;

  @override
  State<ActivityFilterSheet> createState() => _ActivityFilterSheetState();
}

class _ActivityFilterSheetState extends State<ActivityFilterSheet> {
  late String _status = widget.status;
  late DateTime? _from = widget.fromDate;
  late DateTime? _to = widget.toDate;

  Future<void> _pickDate(bool from) async {
    final now = DateTime.now();
    final initial = from
        ? (_from ?? now.subtract(const Duration(days: 30)))
        : (_to ?? now);
    final selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: now,
    );
    if (selected == null) return;
    setState(() {
      if (from) {
        _from = selected;
        if (_to != null && _to!.isBefore(selected)) _to = selected;
      } else {
        _to = selected;
        if (_from != null && _from!.isAfter(selected)) _from = selected;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final now = DateTime.now();
    return DraggableScrollableSheet(
      initialChildSize: .66,
      minChildSize: .48,
      maxChildSize: .92,
      expand: false,
      builder: (context, controller) => Container(
        decoration: BoxDecoration(
          color: colors.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: Column(
          children: [
            Container(
              width: 38,
              height: 4,
              margin: const EdgeInsets.symmetric(vertical: 13),
              decoration: BoxDecoration(
                color: colors.outlineVariant,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Filter activity',
                      style: Theme.of(context).textTheme.titleLarge
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                  ),
                  TextButton(
                    onPressed: () => setState(() {
                      _status = 'all';
                      _from = null;
                      _to = null;
                    }),
                    child: const Text('Reset'),
                  ),
                ],
              ),
            ),
            const Divider(height: 18),
            Expanded(
              child: ListView(
                controller: controller,
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 18),
                children: [
                  Text(
                    'Status',
                    style: Theme.of(context).textTheme.titleSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                              ('all', 'All'),
                              ('posted', 'Success'),
                              ('pending', 'Pending'),
                              ('failed', 'Failed'),
                              ('rejected', 'Rejected'),
                              ('cancelled', 'Cancelled'),
                            ]
                            .map(
                              (item) => ChoiceChip(
                                label: Text(item.$2),
                                selected: _status == item.$1,
                                showCheckmark: false,
                                onSelected: (_) =>
                                    setState(() => _status = item.$1),
                                selectedColor: colors.primary,
                                labelStyle: TextStyle(
                                  color: _status == item.$1
                                      ? colors.onPrimary
                                      : colors.onSurfaceVariant,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            )
                            .toList(),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Date range',
                    style: Theme.of(context).textTheme.titleSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 9),
                  Row(
                    children: [
                      Expanded(
                        child: _dateButton(
                          'From',
                          _from,
                          () => _pickDate(true),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _dateButton('To', _to, () => _pickDate(false)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: [
                      _quickDate(
                        'Today',
                        DateTime(now.year, now.month, now.day),
                        now,
                      ),
                      _quickDate(
                        '7 days',
                        DateTime(
                          now.year,
                          now.month,
                          now.day,
                        ).subtract(const Duration(days: 6)),
                        now,
                      ),
                      _quickDate(
                        '30 days',
                        DateTime(
                          now.year,
                          now.month,
                          now.day,
                        ).subtract(const Duration(days: 29)),
                        now,
                      ),
                      _quickDate(
                        'This month',
                        DateTime(now.year, now.month, 1),
                        now,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () {
                      widget.onApply(_status, _from, _to);
                      Navigator.pop(context);
                    },
                    child: const Text('Apply filters'),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _dateButton(String label, DateTime? date, VoidCallback onTap) =>
      OutlinedButton.icon(
        onPressed: onTap,
        icon: const Icon(Icons.calendar_month_rounded, size: 17),
        label: Text(
          date == null ? label : _formatDate(date),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      );

  String _formatDate(DateTime date) {
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

  Widget _quickDate(String label, DateTime from, DateTime to) => ActionChip(
    label: Text(label),
    onPressed: () => setState(() {
      _from = from;
      _to = to;
    }),
  );
}
