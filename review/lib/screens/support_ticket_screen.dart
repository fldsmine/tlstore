import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class SupportTicketsScreen extends StatefulWidget {
  const SupportTicketsScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

  @override
  State<SupportTicketsScreen> createState() => _SupportTicketsScreenState();
}

class _SupportTicketsScreenState extends State<SupportTicketsScreen> {
  List<Map<String, dynamic>> _tickets = const [];
  bool _loading = true;
  bool _creating = false;
  String? _error;
  String _filter = 'all';

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(Object? response) {
    final data = _map(response)['data'];
    final list = data is List ? data : _map(data)['data'];
    return list is List
        ? list.whereType<Map>().map(_map).toList()
        : const <Map<String, dynamic>>[];
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.supportTickets(
        widget.token,
        status: _filter,
      );
      if (mounted) setState(() => _tickets = _rows(response));
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Support tickets could not load.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _newTicket() async {
    if (_creating) return;
    setState(() => _creating = true);
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) =>
          _NewSupportTicketSheet(api: widget.api, token: widget.token),
    );
    if (!mounted) return;
    setState(() => _creating = false);
    if (created == true) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final firstName =
        (widget.user['full_name'] ?? widget.user['username'] ?? 'there')
            .toString()
            .split(' ')
            .first;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Help & support'),
        actions: [
          IconButton(
            tooltip: 'Refresh tickets',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _creating ? null : _newTicket,
        icon: const Icon(Icons.add_comment_outlined),
        label: const Text('New ticket'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 100),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [colors.primaryContainer, colors.secondaryContainer],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 25,
                    backgroundColor: colors.surface.withValues(alpha: .8),
                    child: Icon(
                      Icons.support_agent_rounded,
                      color: colors.primary,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'How can we help, $firstName?',
                          style: theme.textTheme.titleMedium?.copyWith(
                            color: colors.onPrimaryContainer,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Send a message and follow replies here.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onPrimaryContainer.withValues(
                              alpha: .82,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'all', label: Text('All')),
                ButtonSegment(value: 'open', label: Text('Open')),
                ButtonSegment(value: 'closed', label: Text('Closed')),
              ],
              selected: {_filter},
              onSelectionChanged: (selection) {
                setState(() => _filter = selection.first);
                _load();
              },
            ),
            const SizedBox(height: 14),
            if (_error != null)
              Card(
                color: colors.errorContainer,
                child: ListTile(
                  leading: Icon(Icons.error_outline, color: colors.error),
                  title: Text(_error!),
                  trailing: IconButton(
                    onPressed: _load,
                    icon: const Icon(Icons.refresh_rounded),
                  ),
                ),
              ),
            if (_loading && _tickets.isEmpty)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_tickets.isEmpty)
              _emptyState(context)
            else
              for (final ticket in _tickets)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _TicketTile(
                    ticket: ticket,
                    onTap: () async {
                      await Navigator.of(context).push<void>(
                        MaterialPageRoute<void>(
                          builder: (_) => SupportTicketDetailScreen(
                            api: widget.api,
                            token: widget.token,
                            ticket: ticket,
                          ),
                        ),
                      );
                      if (mounted) _load();
                    },
                  ),
                ),
          ],
        ),
      ),
    );
  }

  Widget _emptyState(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 38, 24, 30),
      child: Column(
        children: [
          Icon(Icons.forum_outlined, size: 48, color: colors.primary),
          const SizedBox(height: 12),
          Text(
            'No support tickets yet',
            style: Theme.of(context).textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          Text(
            'Create a ticket and our team will reply in this inbox.',
            textAlign: TextAlign.center,
            style: TextStyle(color: colors.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

class SupportTicketDetailScreen extends StatefulWidget {
  const SupportTicketDetailScreen({
    super.key,
    required this.api,
    required this.token,
    required this.ticket,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> ticket;

  @override
  State<SupportTicketDetailScreen> createState() =>
      _SupportTicketDetailScreenState();
}

class _SupportTicketDetailScreenState extends State<SupportTicketDetailScreen> {
  final _message = TextEditingController();
  Map<String, dynamic> _ticket = const {};
  bool _loading = true;
  bool _sending = false;
  String? _error;

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  @override
  void initState() {
    super.initState();
    _ticket = widget.ticket;
    _load();
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.supportTicket(
        widget.token,
        widget.ticket['id'],
      );
      if (mounted) setState(() => _ticket = _map(response['data']));
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'This ticket could not load.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _send() async {
    final text = _message.text.trim();
    if (text.length < 2 || _sending) return;
    FocusScope.of(context).unfocus();
    setState(() => _sending = true);
    try {
      final response = await widget.api.replySupportTicket(
        widget.token,
        widget.ticket['id'],
        text,
      );
      if (!mounted) return;
      setState(() {
        _ticket = _map(response['data']);
        _message.clear();
      });
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Your reply could not be sent.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _close() async {
    try {
      final response = await widget.api.closeSupportTicket(
        widget.token,
        widget.ticket['id'],
      );
      if (mounted) setState(() => _ticket = _map(response['data']));
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Ticket could not be closed.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final messages = _ticket['messages'] is List
        ? (_ticket['messages'] as List).whereType<Map>().map(_map).toList()
        : const <Map<String, dynamic>>[];
    final isClosed = _ticket['status'] == 'closed';

    return Scaffold(
      appBar: AppBar(
        title: Text(_ticket['ticket_number']?.toString() ?? 'Support ticket'),
        actions: [
          if (!isClosed)
            IconButton(
              tooltip: 'Close ticket',
              onPressed: _close,
              icon: const Icon(Icons.check_circle_outline_rounded),
            ),
        ],
      ),
      body: Column(
        children: [
          Material(
            color: colors.surfaceContainerLow,
            child: ListTile(
              title: Text(
                _ticket['subject']?.toString() ?? 'Support request',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                '${_label(_ticket['category'])}  ·  ${_label(_ticket['status'])}',
              ),
              trailing: _StatusBadge(
                status: _ticket['status']?.toString() ?? '',
              ),
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(10),
              child: Text(_error!, style: TextStyle(color: colors.error)),
            ),
          Expanded(
            child: _loading && messages.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: messages.length,
                    itemBuilder: (context, index) {
                      final message = messages[index];
                      final fromUser = message['sender_type'] == 'user';
                      return Align(
                        alignment: fromUser
                            ? Alignment.centerRight
                            : Alignment.centerLeft,
                        child: Container(
                          constraints: BoxConstraints(
                            maxWidth: MediaQuery.sizeOf(context).width * .8,
                          ),
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: fromUser
                                ? colors.primaryContainer
                                : colors.surfaceContainerHigh,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                fromUser
                                    ? 'You'
                                    : message['sender_name']?.toString() ??
                                          'TLStore Support',
                                style: TextStyle(
                                  color: fromUser
                                      ? colors.onPrimaryContainer
                                      : colors.primary,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(message['message']?.toString() ?? ''),
                              const SizedBox(height: 5),
                              Text(
                                _date(message['created_at']),
                                style: Theme.of(context).textTheme.labelSmall
                                    ?.copyWith(color: colors.onSurfaceVariant),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
              child: isClosed
                  ? Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: colors.surfaceContainerHigh,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Text(
                        'This ticket is closed. Create a new ticket if you need more help.',
                        textAlign: TextAlign.center,
                      ),
                    )
                  : Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _message,
                            minLines: 1,
                            maxLines: 4,
                            decoration: const InputDecoration(
                              hintText: 'Write a reply…',
                              border: OutlineInputBorder(),
                              isDense: true,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton.filled(
                          onPressed: _sending ? null : _send,
                          tooltip: 'Send reply',
                          icon: _sending
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.send_rounded),
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

class _NewSupportTicketSheet extends StatefulWidget {
  const _NewSupportTicketSheet({required this.api, required this.token});

  final AuthApi api;
  final String token;

  @override
  State<_NewSupportTicketSheet> createState() => _NewSupportTicketSheetState();
}

class _NewSupportTicketSheetState extends State<_NewSupportTicketSheet> {
  final _formKey = GlobalKey<FormState>();
  final _subject = TextEditingController();
  final _message = TextEditingController();
  String _category = 'general';
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _sending) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await widget.api.createSupportTicket(
        widget.token,
        subject: _subject.text,
        category: _category,
        message: _message.text,
      );
      if (mounted) Navigator.pop(context, true);
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted)
        setState(() => _error = 'The support request could not be sent.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    final colors = Theme.of(context).colorScheme;
    const categories = <String, String>{
      'general': 'General question',
      'account': 'Account',
      'payments': 'Payments and withdrawals',
      'subscriptions': 'Subscriptions',
      'app_reviews': 'App reviews',
      'ad_shares': 'Ad sharing',
      'vas': 'Value added services',
      'other': 'Other',
    };
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(20, 8, 20, 18 + bottom),
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Create support ticket',
                  style: Theme.of(context).textTheme.headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(
                  'Our support team will reply in your ticket inbox.',
                  style: TextStyle(color: colors.onSurfaceVariant),
                ),
                const SizedBox(height: 18),
                DropdownButtonFormField<String>(
                  value: _category,
                  decoration: const InputDecoration(
                    labelText: 'Topic',
                    border: OutlineInputBorder(),
                  ),
                  items: categories.entries
                      .map(
                        (entry) => DropdownMenuItem(
                          value: entry.key,
                          child: Text(entry.value),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setState(() => _category = value ?? 'general'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _subject,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Subject',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) => (value?.trim().length ?? 0) >= 4
                      ? null
                      : 'Enter a short subject.',
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _message,
                  minLines: 4,
                  maxLines: 7,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Describe the issue',
                    alignLabelWithHint: true,
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) => (value?.trim().length ?? 0) >= 10
                      ? null
                      : 'Add at least 10 characters.',
                ),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(_error!, style: TextStyle(color: colors.error)),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _sending ? null : _submit,
                  icon: _sending
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.send_rounded),
                  label: Text(_sending ? 'Sending…' : 'Send ticket'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(50),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TicketTile extends StatelessWidget {
  const _TicketTile({required this.ticket, required this.onTap});

  final Map<String, dynamic> ticket;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final messages = ticket['messages'];
    final lastMessage = messages is List && messages.isNotEmpty
        ? (messages.last is Map ? (messages.last as Map)['message'] : null)
        : null;
    return Card(
      margin: EdgeInsets.zero,
      child: ListTile(
        onTap: onTap,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        leading: CircleAvatar(
          backgroundColor: colors.primaryContainer,
          foregroundColor: colors.onPrimaryContainer,
          child: const Icon(Icons.forum_outlined),
        ),
        title: Text(
          ticket['subject']?.toString() ?? 'Support ticket',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(
              '${ticket['ticket_number'] ?? ''} · ${_label(ticket['category'])}',
            ),
            if (lastMessage != null) ...[
              const SizedBox(height: 3),
              Text(
                lastMessage.toString(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ],
        ),
        trailing: _StatusBadge(status: ticket['status']?.toString() ?? ''),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final closed = status == 'closed' || status == 'resolved';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: (closed ? colors.tertiary : colors.primary).withValues(
          alpha: .14,
        ),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        _label(status),
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: closed ? colors.tertiary : colors.primary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

String _label(Object? value) => (value?.toString() ?? '')
    .split(RegExp(r'[_\-\s]+'))
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');

String _date(Object? value) {
  final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
  if (date == null) return '';
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  final meridian = date.hour >= 12 ? 'PM' : 'AM';
  return '${date.day}/${date.month}/${date.year} · $hour:$minute $meridian';
}
