import 'package:flutter/material.dart';

import '../../../../services/auth_api.dart';

class AppReportDialog extends StatefulWidget {
  const AppReportDialog({
    super.key,
    required this.api,
    required this.token,
    required this.campaignId,
  });

  final AuthApi api;
  final String token;
  final Object campaignId;

  static Future<bool> show(
    BuildContext context, {
    required AuthApi api,
    required String token,
    required Object campaignId,
  }) async =>
      await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        showDragHandle: true,
        builder: (_) =>
            AppReportDialog(api: api, token: token, campaignId: campaignId),
      ) ??
      false;

  @override
  State<AppReportDialog> createState() => _AppReportDialogState();
}

class _AppReportDialogState extends State<AppReportDialog> {
  static const _reasons = <String, String>{
    'misleading_listing': 'Listing information is misleading',
    'broken_store_link': 'Store link is broken or incorrect',
    'inappropriate_content': 'Inappropriate content',
    'impersonation_or_copyright': 'Impersonation or copyright concern',
    'other': 'Other',
  };

  final _details = TextEditingController();
  String _reason = 'misleading_listing';
  bool _submitting = false;

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      await widget.api.post(
        'review-campaigns/${widget.campaignId}/reports',
        widget.token,
        {'reason_code': _reason, 'details': _details.text.trim()},
      );
      if (mounted) Navigator.of(context).pop(true);
    } on AuthException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        8,
        20,
        MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Report this app',
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Text(
            'Tell our review team what is wrong with this listing.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 18),
          DropdownButtonFormField<String>(
            value: _reason,
            decoration: const InputDecoration(labelText: 'Reason'),
            items: _reasons.entries
                .map(
                  (entry) => DropdownMenuItem(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: _submitting
                ? null
                : (value) {
                    if (value != null) setState(() => _reason = value);
                  },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _details,
            minLines: 3,
            maxLines: 5,
            maxLength: 3000,
            decoration: const InputDecoration(
              labelText: 'Details (optional)',
              alignLabelWithHint: true,
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: _submitting ? null : _submit,
            style: FilledButton.styleFrom(backgroundColor: scheme.error),
            icon: _submitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.flag_outlined),
            label: Text(_submitting ? 'Sending report…' : 'Send report'),
          ),
        ],
      ),
    );
  }
}
