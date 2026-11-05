import 'package:flutter/material.dart';

class SupportTicketDemoScreen extends StatefulWidget {
  const SupportTicketDemoScreen({super.key, required this.user});

  final Map<String, dynamic> user;

  @override
  State<SupportTicketDemoScreen> createState() =>
      _SupportTicketDemoScreenState();
}

class _SupportTicketDemoScreenState extends State<SupportTicketDemoScreen> {
  final _formKey = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  bool _demoSubmitted = false;

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  void _submitDemoTicket() {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _demoSubmitted = true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final name = (widget.user['full_name'] ?? widget.user['name'] ?? 'there')
        .toString();
    final email = widget.user['email']?.toString() ?? '';

    return Scaffold(
      appBar: AppBar(title: const Text('Contact support')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: colors.primaryContainer,
                borderRadius: BorderRadius.circular(22),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.support_agent_rounded,
                    color: colors.primary,
                    size: 34,
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'We’re here to help, $name',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Describe what you need help with and our support team can take a look.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onPrimaryContainer.withValues(alpha: .82),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: colors.tertiaryContainer.withValues(alpha: .65),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline_rounded, color: colors.tertiary),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Demo preview: ticket sending and replies will be connected in a future update. This form does not contact support.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colors.onTertiaryContainer,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            if (_demoSubmitted)
              _DemoSubmittedCard(
                subject: _subjectController.text.trim(),
                onEdit: () => setState(() => _demoSubmitted = false),
              )
            else
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      initialValue: email,
                      readOnly: true,
                      decoration: const InputDecoration(
                        labelText: 'Reply email',
                        prefixIcon: Icon(Icons.alternate_email_rounded),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _subjectController,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(
                        labelText: 'Subject',
                        hintText: 'What do you need help with?',
                        prefixIcon: Icon(Icons.short_text_rounded),
                      ),
                      validator: (value) => (value?.trim().isNotEmpty ?? false)
                          ? null
                          : 'Enter a subject',
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _messageController,
                      minLines: 5,
                      maxLines: 8,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(
                        labelText: 'How can we help?',
                        hintText: 'Add details that can help us understand the issue.',
                        alignLabelWithHint: true,
                      ),
                      validator: (value) => (value?.trim().length ?? 0) >= 10
                          ? null
                          : 'Add at least 10 characters',
                    ),
                    const SizedBox(height: 20),
                    FilledButton.icon(
                      onPressed: _submitDemoTicket,
                      icon: const Icon(Icons.send_rounded),
                      label: const Text('Preview ticket'),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _DemoSubmittedCard extends StatelessWidget {
  const _DemoSubmittedCard({required this.subject, required this.onEdit});

  final String subject;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.task_alt_rounded, color: colors.primary, size: 34),
            const SizedBox(height: 12),
            Text(
              'Ticket preview ready',
              style: Theme.of(context).textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            Text(
              '“$subject” is ready to send when support tickets are enabled.',
            ),
            const SizedBox(height: 12),
            TextButton.icon(
              onPressed: onEdit,
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Edit details'),
            ),
          ],
        ),
      ),
    );
  }
}
