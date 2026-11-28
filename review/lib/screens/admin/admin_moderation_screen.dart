import 'dart:convert';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:cross_file/cross_file.dart';
import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/currency/money.dart';
import '../../services/auth_api.dart';
import '../../config/api_config.dart';

class AdminModerationScreen extends StatefulWidget {
  const AdminModerationScreen({
    super.key,
    required this.api,
    this.adminToken,
    this.initialSearch = '',
    this.initialTabIndex = 0,
  });

  final AuthApi api;
  final String? adminToken;
  final String initialSearch;
  final int initialTabIndex;

  @override
  State<AdminModerationScreen> createState() => _AdminModerationScreenState();
}

enum _ModerationQueue { reviews, shares, deposits, withdrawals }

extension on _ModerationQueue {
  String get title => switch (this) {
        _ModerationQueue.reviews => 'App reviews',
        _ModerationQueue.shares => 'Ad shares',
        _ModerationQueue.deposits => 'Manual deposits',
        _ModerationQueue.withdrawals => 'Withdrawals',
      };

  String get path => switch (this) {
        _ModerationQueue.reviews => 'admin/moderation/reviews',
        _ModerationQueue.shares => 'admin/moderation/shares',
        _ModerationQueue.deposits => 'admin/moderation/manual-deposits',
        _ModerationQueue.withdrawals => 'admin/moderation/withdrawals',
      };

  IconData get icon => switch (this) {
        _ModerationQueue.reviews => Icons.rate_review_outlined,
        _ModerationQueue.shares => Icons.campaign_outlined,
        _ModerationQueue.deposits => Icons.account_balance_outlined,
        _ModerationQueue.withdrawals => Icons.payments_outlined,
      };
}

class _QueueState {
  final search = TextEditingController();
  List<Map<String, dynamic>> rows = [];
  int page = 1;
  int lastPage = 1;
  int total = 0;
  int generation = 0;
  bool loading = false;
  bool loaded = false;
  String? error;
}

class _AdminModerationScreenState extends State<AdminModerationScreen>
    with SingleTickerProviderStateMixin {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _queues = {for (final queue in _ModerationQueue.values) queue: _QueueState()};
  late final TabController _tabs;
  String? _token;
  String? _loginError;
  String? _busyItem;
  bool _loginBusy = false;
  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: _ModerationQueue.values.length, vsync: this);
    _tabs.addListener(_onTabChanged);
    if (widget.adminToken?.isNotEmpty == true) {
      _token = widget.adminToken;
      _tabs.index = widget.initialTabIndex
          .clamp(0, _ModerationQueue.values.length - 1)
          .toInt();
      for (final state in _queues.values) {
        state.search.text = widget.initialSearch;
      }
      Future.microtask(() => Future.wait(_ModerationQueue.values.map(_load)));
    }
  }

  @override
  void dispose() {
    _tabs.removeListener(_onTabChanged);
    _tabs.dispose();
    _email.dispose();
    _password.dispose();
    for (final queue in _queues.values) {
      queue.search.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(Object? response) {
    final data = _map(response)['data'];
    final values = data is List ? data : _map(data)['data'];
    return values is List ? values.map(_map).toList() : <Map<String, dynamic>>[];
  }

  int _int(Object? value, [int fallback = 0]) => value is num
      ? value.toInt()
      : int.tryParse(value?.toString() ?? '') ?? fallback;

  void _onTabChanged() {
    if (_tabs.indexIsChanging || _token == null) return;
    final queue = _ModerationQueue.values[_tabs.index];
    final state = _queues[queue]!;
    if (!state.loaded && !state.loading) {
      _load(queue);
    }
  }

  Future<void> _login() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _loginBusy = true;
      _loginError = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
      });
      final token = response['token'] ?? _map(response['data'])['token'];
      if (token == null || token.toString().isEmpty) {
        throw const AuthException('Admin sign-in did not return an access token.');
      }
      if (!mounted) return;
      setState(() => _token = token.toString());
      await Future.wait(_ModerationQueue.values.map(_load));
    } on AuthException catch (error) {
      if (mounted) setState(() => _loginError = error.message);
    } catch (error) {
      if (mounted) setState(() => _loginError = error.toString());
    } finally {
      if (mounted) setState(() => _loginBusy = false);
    }
  }

  Future<void> _load(_ModerationQueue kind, {int? page}) async {
    final token = _token;
    if (token == null) return;
    final state = _queues[kind]!;
    final generation = ++state.generation;
    final targetPage = page ?? state.page;
    setState(() {
      state.loading = true;
      state.error = null;
    });
    final params = <String, String>{'page': '$targetPage', 'per_page': '30'};
    if (state.search.text.trim().isNotEmpty) {
      params['search'] = state.search.text.trim();
    }
    final query = params.entries
        .map((entry) => '${Uri.encodeQueryComponent(entry.key)}=${Uri.encodeQueryComponent(entry.value)}')
        .join('&');
    try {
      final response = await widget.api.get('${kind.path}?$query', token);
      if (!mounted || generation != state.generation) return;
      final envelope = _map(_map(response)['data']);
      final rows = _rows(response);
      setState(() {
        state.rows = rows;
        state.page = _int(envelope['current_page'], targetPage);
        state.lastPage = _int(envelope['last_page'], 1).clamp(1, 100000).toInt();
        state.total = _int(envelope['total'], rows.length);
        state.loaded = true;
      });
    } on AuthException catch (error) {
      if (mounted && generation == state.generation) {
        setState(() => state.error = error.message);
      }
    } catch (error) {
      if (mounted && generation == state.generation) {
        setState(() => state.error = error.toString());
      }
    } finally {
      if (mounted && generation == state.generation) {
        setState(() => state.loading = false);
      }
    }
  }

  Future<void> _decide(
    _ModerationQueue kind,
    Map<String, dynamic> row,
    String decision,
  ) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _DecisionDialog(kind: kind, decision: decision),
    );
    if (payload == null || _token == null) return;
    final id = _rowId(kind, row);
    final busyKey = '${kind.name}:$id';
    setState(() => _busyItem = busyKey);
    try {
      final suffix = switch (kind) {
        _ModerationQueue.reviews => 'reviews/$id/decision',
        _ModerationQueue.shares => 'shares/$id/decision',
        _ModerationQueue.deposits => 'manual-deposits/$id/decision',
        _ModerationQueue.withdrawals => 'withdrawals/$id/settle',
      };
      await widget.api.post('admin/moderation/$suffix', _token!, payload);
      if (!mounted) return;
      _toast(_successMessage(kind, decision));
      final state = _queues[kind]!;
      final nextPage = state.rows.length == 1 && state.page > 1
          ? state.page - 1
          : state.page;
      await _load(kind, page: nextPage);
    } on AuthException catch (error) {
      if (mounted) _toast(error.message, error: true);
    } catch (error) {
      if (mounted) _toast(error.toString(), error: true);
    } finally {
      if (mounted) setState(() => _busyItem = null);
    }
  }

  String _rowId(_ModerationQueue kind, Map<String, dynamic> row) {
    if (kind == _ModerationQueue.reviews) return _text(row['id']);
    if (kind == _ModerationQueue.shares) return _text(row['id']);
    return _text(row['id']);
  }

  String _successMessage(_ModerationQueue kind, String decision) => switch (kind) {
        _ModerationQueue.reviews => decision == 'approved' ? 'Review approved and reward credited.' : 'Review rejected.',
        _ModerationQueue.shares => decision == 'approved' ? 'Share approved and reward credited.' : 'Share rejected.',
        _ModerationQueue.deposits => decision == 'approved' ? 'Deposit approved and funds credited.' : 'Deposit rejected.',
        _ModerationQueue.withdrawals => decision == 'paid' ? 'Withdrawal marked as paid.' : 'Withdrawal rejected and funds released.',
      };

  String _text(Object? value, [String fallback = '—']) =>
      value == null || value.toString().isEmpty ? fallback : value.toString();

  String _money(Object? minor, Object? currency) {
    final code = _text(currency, 'USD');
    final value = minor is num ? minor.toDouble() : double.tryParse('$minor');
    if (value == null) return '—';
    final precision = _precision(code);
    return formatMajorMoney(value / _pow10(precision), code);
  }

  int _precision(String currency) {
    if (const {'BIF','CLP','DJF','GNF','ISK','JPY','KMF','KRW','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'}.contains(currency.toUpperCase())) return 0;
    if (const {'BHD','IQD','JOD','KWD','LYD','OMR','TND'}.contains(currency.toUpperCase())) return 3;
    return 2;
  }

  int _pow10(int digits) => switch (digits) { 0 => 1, 1 => 10, 2 => 100, 3 => 1000, _ => 10000 };

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      behavior: SnackBarBehavior.floating,
      backgroundColor: error ? Theme.of(context).colorScheme.error : null,
    ));
  }

  void _logout() => setState(() {
        _token = null;
        _password.clear();
        _busyItem = null;
        for (final state in _queues.values) {
          // Ignore any requests that were still in flight when the admin signed out.
          state.generation++;
          state.loading = false;
          state.rows = [];
          state.total = 0;
          state.page = 1;
          state.lastPage = 1;
          state.loaded = false;
          state.error = null;
          state.search.clear();
        }
      });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    if (_token == null) return _loginPage(theme);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Task moderation'),
        actions: [
          IconButton(
            tooltip: 'Refresh queue',
            onPressed: _queues[_ModerationQueue.values[_tabs.index]]!.loading
                ? null
                : () => _load(_ModerationQueue.values[_tabs.index]),
            icon: const Icon(Icons.refresh_rounded),
          ),
          IconButton(tooltip: 'Sign out', onPressed: _logout, icon: const Icon(Icons.logout_rounded)),
        ],
        bottom: TabBar(
          controller: _tabs,
          isScrollable: true,
          tabs: [
            for (final kind in _ModerationQueue.values)
              Tab(text: '${kind.title} (${_queues[kind]!.total})', icon: Icon(kind.icon)),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [for (final kind in _ModerationQueue.values) _queuePage(kind, colors)],
      ),
    );
  }

  Widget _loginPage(ThemeData theme) => Scaffold(
        appBar: AppBar(title: const Text('Task moderation')),
        body: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 430),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.fact_check_outlined, size: 48, color: theme.colorScheme.primary),
                    const SizedBox(height: 12),
                    Text('Admin sign in', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 18),
                    TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Admin email', prefixIcon: Icon(Icons.alternate_email_rounded), border: OutlineInputBorder())),
                    const SizedBox(height: 12),
                    TextField(controller: _password, obscureText: _obscurePassword, onSubmitted: (_) { if (!_loginBusy) _login(); }, decoration: InputDecoration(labelText: 'Password', prefixIcon: const Icon(Icons.lock_outline_rounded), border: const OutlineInputBorder(), suffixIcon: IconButton(onPressed: () => setState(() => _obscurePassword = !_obscurePassword), icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined)))),
                    if (_loginError != null) ...[const SizedBox(height: 12), _ErrorBanner(message: _loginError!)],
                    const SizedBox(height: 16),
                    SizedBox(width: double.infinity, child: FilledButton.icon(onPressed: _loginBusy ? null : _login, icon: _loginBusy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.login_rounded), label: Text(_loginBusy ? 'Signing in…' : 'Sign in'))),
                  ]),
                ),
              ),
            ),
          ),
        ),
      );

  Widget _queuePage(_ModerationQueue kind, ColorScheme colors) {
    final state = _queues[kind]!;
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
        child: Row(children: [
          Expanded(child: TextField(controller: state.search, textInputAction: TextInputAction.search, onSubmitted: (_) => _load(kind, page: 1), decoration: InputDecoration(hintText: _searchHint(kind), prefixIcon: const Icon(Icons.search_rounded), suffixIcon: IconButton(tooltip: 'Search queue', onPressed: () => _load(kind, page: 1), icon: const Icon(Icons.arrow_forward_rounded)), border: const OutlineInputBorder()))),
        ]),
      ),
      if (state.loading) const LinearProgressIndicator(minHeight: 2),
      if (state.error != null) Padding(padding: const EdgeInsets.fromLTRB(14, 8, 14, 0), child: _ErrorBanner(message: state.error!)),
      Expanded(
        child: state.loading && state.rows.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : state.error != null && state.rows.isEmpty
                ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(state.error!, textAlign: TextAlign.center)))
                : state.rows.isEmpty
                    ? _emptyQueue(kind, colors)
                    : RefreshIndicator(
                        onRefresh: () => _load(kind),
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
                          itemCount: state.rows.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final row = state.rows[index];
                            final busyKey = '${kind.name}:${_rowId(kind, row)}';
                            return _ModerationCard(
                              kind: kind,
                              row: row,
                              money: _money,
                              busy: _busyItem == busyKey,
                              onApprove: () => _decide(kind, row, kind == _ModerationQueue.withdrawals ? 'paid' : 'approved'),
                              onReject: () => _decide(kind, row, 'rejected'),
                              onEvidence: () => _showEvidence(kind, row),
                            );
                          },
                        ),
                      ),
      ),
      if (state.total > 0)
        SafeArea(top: false, child: Padding(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6), child: Row(children: [Text('${state.total} records · page ${state.page}/${state.lastPage}', style: Theme.of(context).textTheme.labelMedium), const Spacer(), IconButton(tooltip: 'Previous page', onPressed: state.page > 1 && !state.loading ? () => _load(kind, page: state.page - 1) : null, icon: const Icon(Icons.chevron_left_rounded)), IconButton(tooltip: 'Next page', onPressed: state.page < state.lastPage && !state.loading ? () => _load(kind, page: state.page + 1) : null, icon: const Icon(Icons.chevron_right_rounded))]))),
    ]);
  }

  String _searchHint(_ModerationQueue kind) => switch (kind) {
        _ModerationQueue.reviews => 'Search reviewer or app',
        _ModerationQueue.shares => 'Search user, campaign or reference',
        _ModerationQueue.deposits => 'Search user, bank or payment reference',
        _ModerationQueue.withdrawals => 'Search user or payout method',
      };

  Widget _emptyQueue(_ModerationQueue kind, ColorScheme colors) => Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(kind.icon, size: 42, color: colors.primary),
          const SizedBox(height: 10),
          Text('No pending ${kind.title.toLowerCase()}.', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text('New items awaiting review will appear here.', style: Theme.of(context).textTheme.bodySmall),
        ]),
      );

  Future<void> _showEvidence(_ModerationQueue kind, Map<String, dynamic> row) async {
    final token = _token;
    if (token == null) return;
    if (kind == _ModerationQueue.deposits) {
      final path = 'admin/moderation/manual-deposits/${row['id']}/proof';
      final proofPath = _text(row['proof_storage_path'], '');
      if (proofPath.toLowerCase().endsWith('.pdf')) {
        try {
          final file = await widget.api.download(path, token);
          await SharePlus.instance.share(ShareParams(files: [XFile.fromData(file.bodyBytes, mimeType: file.headers['content-type'] ?? 'application/pdf', name: 'deposit-proof-${row['id']}.pdf')], title: 'Deposit proof'));
        } on AuthException catch (error) {
          if (mounted) _toast(error.message, error: true);
        }
        return;
      }
      await _showImageProof(path, token, 'Deposit proof');
      return;
    }
    if (kind == _ModerationQueue.shares) {
      final evidence = _map(row['evidence']);
      final screenshot = _text(evidence['screenshot_path'], '');
      if (screenshot.isNotEmpty) {
        await _showImageProof('admin/moderation/shares/${row['id']}/proof', token, 'Share proof');
      } else {
        _toast('No screenshot proof was attached to this share.', error: true);
      }
    }
  }

  Future<void> _showImageProof(String path, String token, String title) async {
    await showDialog<void>(
      context: context,
      builder: (context) => Dialog(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 900, maxHeight: 780),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            ListTile(title: Text(title), trailing: IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded))),
            const Divider(height: 1),
            Flexible(child: InteractiveViewer(child: CachedNetworkImage(imageUrl: ApiConfig.endpoint(path).toString(), httpHeaders: {'Accept': 'image/*', 'Authorization': 'Bearer $token'}, fit: BoxFit.contain, errorWidget: (_, __, ___) => const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Could not display this evidence file.')))))),
          ]),
        ),
      ),
    );
  }

}

class _ModerationCard extends StatelessWidget {
  const _ModerationCard({required this.kind, required this.row, required this.money, required this.busy, required this.onApprove, required this.onReject, required this.onEvidence});
  final _ModerationQueue kind;
  final Map<String, dynamic> row;
  final String Function(Object?, Object?) money;
  final bool busy;
  final VoidCallback onApprove, onReject, onEvidence;

  Map<String, dynamic> _map(Object? value) => value is Map ? value.map<String, dynamic>((key, value) => MapEntry(key.toString(), value)) : <String, dynamic>{};
  String _text(Object? value, [String fallback = '—']) => value == null || value.toString().isEmpty ? fallback : value.toString();

  Widget _answerRows(Object? raw) {
    final answers = raw is List ? raw : const [];
    if (answers.isEmpty) return const Text('No written answers were included.');
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      for (final item in answers)
        if (item is Map) ...[
          Text(_text(_map(item['question'])['question'], 'Review answer'), style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 3),
          Text(_prettyValue(item['answer_value'])),
          const SizedBox(height: 10),
        ],
    ]);
  }

  String _prettyValue(Object? value) {
    if (value == null) return '—';
    if (value is String) return value;
    try { return JsonEncoder.withIndent('  ').convert(value); } catch (_) { return value.toString(); }
  }

  @override
  Widget build(BuildContext context) {
    final user = _map(row['reviewer'] ?? _map(row['task'])['user'] ?? row['user']);
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final userName = _text(user['full_name'] ?? user['username'], 'User #${row['reviewer_id'] ?? row['user_id'] ?? '—'}');
    final risk = row['risk_signals'] is List ? row['risk_signals'] as List : const [];
    final busyLabel = busy ? const LinearProgressIndicator(minHeight: 2) : const SizedBox.shrink();
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            CircleAvatar(child: Text(userName.isEmpty ? '?' : userName[0].toUpperCase())),
            const SizedBox(width: 11),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(userName, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)), Text(_text(user['email']), style: theme.textTheme.bodySmall)])),
            if (risk.isNotEmpty) Chip(avatar: Icon(Icons.warning_amber_rounded, color: colors.error, size: 17), label: Text('${risk.length} risk signal${risk.length == 1 ? '' : 's'}'), visualDensity: VisualDensity.compact),
          ]),
          const SizedBox(height: 12),
          _kindDetails(context),
          if (risk.isNotEmpty) ...[
            const SizedBox(height: 10),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              childrenPadding: const EdgeInsets.only(bottom: 8),
              title: const Text('Risk signals', style: TextStyle(fontWeight: FontWeight.w700)),
              children: [for (final signal in risk) if (signal is Map) ListTile(dense: true, leading: Icon(Icons.flag_outlined, color: colors.error), title: Text(_text(signal['signal_type'])), subtitle: Text('${_text(signal['severity'])} · ${_text(signal['source'])}'))],
            ),
          ],
          const Divider(height: 22),
          busyLabel,
          if (!busy)
            Wrap(spacing: 8, runSpacing: 8, children: [
              if (kind == _ModerationQueue.shares || kind == _ModerationQueue.deposits)
                OutlinedButton.icon(onPressed: onEvidence, icon: const Icon(Icons.visibility_outlined), label: Text(kind == _ModerationQueue.shares ? 'View screenshot' : 'View proof')),
              OutlinedButton.icon(onPressed: onReject, icon: const Icon(Icons.close_rounded), label: const Text('Reject')),
              FilledButton.icon(onPressed: onApprove, icon: Icon(kind == _ModerationQueue.withdrawals ? Icons.payments_outlined : Icons.check_rounded), label: Text(kind == _ModerationQueue.withdrawals ? 'Mark paid' : 'Approve')),
            ]),
        ]),
      ),
    );
  }

  Widget _kindDetails(BuildContext context) {
    final theme = Theme.of(context);
    final details = switch (kind) {
      _ModerationQueue.reviews => _reviewDetails(),
      _ModerationQueue.shares => _shareDetails(),
      _ModerationQueue.deposits => _depositDetails(),
      _ModerationQueue.withdrawals => _withdrawalDetails(),
    };
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(details.$1, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
      const SizedBox(height: 4),
      Text(details.$2, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant, height: 1.45)),
      if (details.$3 != null) ...[
        const SizedBox(height: 10),
        ExpansionTile(tilePadding: EdgeInsets.zero, childrenPadding: const EdgeInsets.only(bottom: 8), title: Text(details.$3!.$1, style: const TextStyle(fontWeight: FontWeight.w700)), children: [Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: details.$3!.$2)]),
      ],
      if (kind == _ModerationQueue.withdrawals) ...[
        const SizedBox(height: 10),
        Text('Payout details', style: theme.textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        _keyValues(_map(row['payout_details'])),
      ],
    ]);
  }

  (String, String, (String, Widget)?) _reviewDetails() {
    final task = _map(row['task']), campaign = _map(task['campaign']), app = _map(campaign['app']);
    final title = _text(app['name'], 'App review');
    final currency = _text(task['reward_currency'], _text(campaign['currency'], 'USD'));
    final reward = money(task['reserved_reward_minor'], currency);
    final submitted = _text(row['submitted_at']);
    final answers = row['answers'] is List ? row['answers'] as List : const [];
    return (title, 'Reward: $reward · Submitted: $submitted · Duration: ${row['duration_seconds'] ?? '—'} seconds', answers.isEmpty ? null : ('Review answers (${answers.length})', _answerRows(answers)));
  }

  (String, String, (String, Widget)?) _shareDetails() {
    final task = _map(row['task']), ad = _map(task['ad']), evidence = _map(row['evidence']);
    final currency = _text(ad['currency'], 'USD');
    final reward = money(task['reserved_reward_minor'], currency);
    final info = <String>[
      'Platform: ${_text(row['channel'], _text(ad['type']))}',
      'Reward: $reward',
      'Shared: ${_text(row['shared_at'])}',
      if (_text(row['external_reference'], '').isNotEmpty) 'Reference: ${row['external_reference']}',
      if (_text(evidence['public_url'], '').isNotEmpty) 'Post link: ${evidence['public_url']}',
      if (_text(evidence['caption'], '').isNotEmpty) 'Caption: ${evidence['caption']}',
    ];
    return (_text(ad['title'], 'Ad share'), info.join('\n'), null);
  }

  (String, String, (String, Widget)?) _depositDetails() {
    final payment = _map(row['payment_intent']);
    final currency = _text(row['currency'], _text(payment['currency'], 'USD'));
    return ('Manual top-up', 'Amount: ${money(row['amount_minor'] ?? payment['amount_minor'], currency)} · Currency: $currency\nBank reference: ${_text(row['bank_reference'])}\nSubmitted: ${_text(row['submitted_at'])}\nPayment intent: ${_text(row['payment_intent_id'])}', null);
  }

  (String, String, (String, Widget)?) _withdrawalDetails() {
    final wallet = _map(row['wallet']), option = _map(row['option']);
    final subscription = _map(row['user_subscription']), plan = _map(subscription['plan']), group = _map(plan['group']);
    final currency = _text(row['currency'], _text(wallet['currency'], 'USD'));
    final amount = money(row['amount_minor'], currency);
    final fee = money(row['fee_minor'], currency);
    final net = (row['amount_minor'] is num ? (row['amount_minor'] as num) : num.tryParse('${row['amount_minor']}')) ?? 0;
    final feeValue = (row['fee_minor'] is num ? (row['fee_minor'] as num) : num.tryParse('${row['fee_minor']}')) ?? 0;
    return ('${_text(option['name'], 'Withdrawal')} · $amount', 'Fee: $fee · Estimated payout: ${money((net - feeValue).clamp(0, double.infinity), currency)}\nWallet: ${_text(wallet['wallet_type'])} · $currency · Plan: ${_text(plan['name'], _text(group['name']))}\nRequested: ${_text(row['requested_at'])}${_text(row['provider_reference'], '').isEmpty ? '' : '\nReference: ${row['provider_reference']}'}', null);
  }

  Widget _keyValues(Map<String, dynamic> values) => Wrap(spacing: 8, runSpacing: 8, children: [for (final entry in values.entries) Chip(label: Text('${entry.key.replaceAll('_', ' ')}: ${entry.value is Map || entry.value is List ? jsonEncode(entry.value) : entry.value}'))]);
}

class _DecisionDialog extends StatefulWidget {
  const _DecisionDialog({required this.kind, required this.decision});
  final _ModerationQueue kind;
  final String decision;
  @override
  State<_DecisionDialog> createState() => _DecisionDialogState();
}

class _DecisionDialogState extends State<_DecisionDialog> {
  final _reason = TextEditingController();
  final _reference = TextEditingController();
  String _reasonCode = 'incomplete';
  @override
  void dispose() { _reason.dispose(); _reference.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final reject = widget.decision == 'rejected';
    final withdrawal = widget.kind == _ModerationQueue.withdrawals;
    final hasReasonCode = widget.kind == _ModerationQueue.reviews || widget.kind == _ModerationQueue.shares;
    return AlertDialog(
      title: Text(withdrawal ? (reject ? 'Reject withdrawal?' : 'Mark withdrawal paid?') : (reject ? 'Reject this submission?' : 'Approve this submission?')),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(reject ? 'A clear reason will be sent to the user.' : 'Confirm that the evidence and submission meet the task requirements.'),
        if (!reject && withdrawal) ...[
          const SizedBox(height: 14),
          TextField(controller: _reference, decoration: const InputDecoration(labelText: 'Payout reference (optional)', border: OutlineInputBorder())),
        ],
        if (reject && hasReasonCode) ...[
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(value: _reasonCode, decoration: const InputDecoration(labelText: 'Reason code', border: OutlineInputBorder()), items: const [DropdownMenuItem(value: 'incomplete', child: Text('Incomplete proof')), DropdownMenuItem(value: 'invalid_evidence', child: Text('Invalid evidence')), DropdownMenuItem(value: 'requirements_not_met', child: Text('Requirements not met')), DropdownMenuItem(value: 'duplicate', child: Text('Duplicate submission')), DropdownMenuItem(value: 'other', child: Text('Other'))], onChanged: (value) => setState(() => _reasonCode = value ?? _reasonCode)),
        ],
        if (reject) ...[
          const SizedBox(height: 12),
          TextField(controller: _reason, maxLines: 3, onChanged: (_) => setState(() {}), decoration: const InputDecoration(labelText: 'Reason', hintText: 'Explain what needs to be corrected', border: OutlineInputBorder())),
        ],
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: reject && _reason.text.trim().isEmpty ? null : () {
            Navigator.pop(context, <String, dynamic>{
              'decision': widget.decision,
              if (reject) 'reason': _reason.text.trim(),
              if (reject && hasReasonCode) 'reason_code': _reasonCode,
              if (withdrawal && !reject && _reference.text.trim().isNotEmpty) 'provider_reference': _reference.text.trim(),
            });
          },
          child: Text(reject ? 'Reject' : (withdrawal ? 'Mark paid' : 'Approve')),
        ),
      ],
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: Theme.of(context).colorScheme.errorContainer, borderRadius: BorderRadius.circular(12)), child: Text(message, style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer)));
}
