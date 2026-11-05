import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../screens/payout_screen.dart';
import '../../../screens/topup_screen.dart';
import '../../../screens/wallet_transfer_screen.dart';
import '../../../services/auth_api.dart';

class ReviewWalletScreen extends StatelessWidget {
  const ReviewWalletScreen({
    super.key,
    required this.api,
    required this.token,
    required this.wallet,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> wallet;

  @override
  Widget build(BuildContext context) => _WalletAccountScreen(
    api: api,
    token: token,
    wallet: wallet,
    title: 'Review wallet',
    subtitle: 'Rewards from app review tasks are collected here.',
    icon: Icons.rate_review_rounded,
  );
}

class ShareWalletScreen extends StatelessWidget {
  const ShareWalletScreen({
    super.key,
    required this.api,
    required this.token,
    required this.wallet,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> wallet;

  @override
  Widget build(BuildContext context) => _WalletAccountScreen(
    api: api,
    token: token,
    wallet: wallet,
    title: 'Share wallet',
    subtitle: 'Rewards from approved ad shares are collected here.',
    icon: Icons.campaign_rounded,
  );
}

class ReferralWalletScreen extends StatelessWidget {
  const ReferralWalletScreen({
    super.key,
    required this.api,
    required this.token,
    required this.wallet,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> wallet;

  @override
  Widget build(BuildContext context) => _WalletAccountScreen(
    api: api,
    token: token,
    wallet: wallet,
    title: 'Referral wallet',
    subtitle: 'Referral bonuses and invited-user rewards are collected here.',
    icon: Icons.group_add_rounded,
  );
}

class BonusWalletScreen extends StatelessWidget {
  const BonusWalletScreen({
    super.key,
    required this.api,
    required this.token,
    required this.wallet,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> wallet;

  @override
  Widget build(BuildContext context) => _WalletAccountScreen(
    api: api,
    token: token,
    wallet: wallet,
    title: 'Bonus wallet',
    subtitle: 'Welcome bonuses and social follow rewards are collected here.',
    icon: Icons.card_giftcard_rounded,
  );
}

class TopupFundWalletScreen extends StatelessWidget {
  const TopupFundWalletScreen({
    super.key,
    required this.api,
    required this.token,
    required this.wallet,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> wallet;

  @override
  Widget build(BuildContext context) => _WalletAccountScreen(
    api: api,
    token: token,
    wallet: wallet,
    title: 'Top-up fund wallet',
    subtitle: 'Use these funds to pay for subscriptions and location add-ons, or transfer them to an earnings wallet.',
    icon: Icons.account_balance_wallet_rounded,
  );
}

class _WalletAccountScreen extends StatefulWidget {
  const _WalletAccountScreen({
    required this.api,
    required this.token,
    required this.wallet,
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> wallet;
  final String title;
  final String subtitle;
  final IconData icon;

  @override
  State<_WalletAccountScreen> createState() => _WalletAccountScreenState();
}

class _WalletAccountScreenState extends State<_WalletAccountScreen> {
  late Map<String, dynamic> _wallet = Map<String, dynamic>.from(widget.wallet);
  List<Map<String, dynamic>> _entries = const [];
  bool _loading = true;
  bool _loadingMore = false;
  bool _hasMore = false;
  int _page = 1;
  int _totalEntries = 0;
  String? _error;

  bool get _isTopup => _wallet['type'] == 'topup_fund';
  String get _currency =>
      _wallet['currency']?.toString().toUpperCase() ?? 'USD';
  int get _walletId => (_wallet['id'] as num).toInt();

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
      final values = await Future.wait([
        widget.api.wallets(widget.token),
        widget.api.walletLedgerPage(widget.token, _walletId),
      ]);
      final wallets = values[0] as List<Map<String, dynamic>>;
      final pagination = (values[1] as Map<String, dynamic>)['data'];
      final pageData = pagination is Map<String, dynamic>
          ? pagination
          : <String, dynamic>{'data': pagination};
      final entries = pageData['data'] is List
          ? (pageData['data'] as List)
                .whereType<Map<String, dynamic>>()
                .toList()
          : <Map<String, dynamic>>[];
      final currentPage = (pageData['current_page'] as num?)?.toInt() ?? 1;
      final lastPage = (pageData['last_page'] as num?)?.toInt() ?? currentPage;
      final current = wallets
          .where((item) => (item['id'] as num?)?.toInt() == _walletId)
          .firstOrNull;
      if (!mounted) return;
      setState(() {
        if (current != null) _wallet = current;
        _entries = entries;
        _page = currentPage;
        _hasMore = currentPage < lastPage;
        _totalEntries = (pageData['total'] as num?)?.toInt() ?? entries.length;
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    try {
      final response = await widget.api.walletLedgerPage(
        widget.token,
        _walletId,
        page: _page + 1,
      );
      final pageData = response['data'];
      if (!mounted || pageData is! Map<String, dynamic>) return;
      final nextEntries = pageData['data'] is List
          ? (pageData['data'] as List)
                .whereType<Map<String, dynamic>>()
                .toList()
          : <Map<String, dynamic>>[];
      final currentPage =
          (pageData['current_page'] as num?)?.toInt() ?? _page + 1;
      final lastPage = (pageData['last_page'] as num?)?.toInt() ?? currentPage;
      setState(() {
        _entries = [..._entries, ...nextEntries];
        _page = currentPage;
        _hasMore = currentPage < lastPage;
        _totalEntries = (pageData['total'] as num?)?.toInt() ?? _totalEntries;
        _loadingMore = false;
      });
    } on AuthException catch (error) {
      if (mounted) {
        setState(() => _loadingMore = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _open(Widget page) async {
    await Navigator.of(context)
        .push(MaterialPageRoute<void>(builder: (_) => page));
    if (mounted) await _load();
  }

  Future<void> _withdraw() => _open(
    PayoutScreen(api: widget.api, token: widget.token, walletId: _walletId),
  );

  Future<void> _transfer() => _open(
    WalletTransferScreen(
      api: widget.api,
      token: widget.token,
      sourceWalletId: _walletId,
    ),
  );

  Future<void> _topup() =>
      _open(TopUpScreen(api: widget.api, token: widget.token));

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final balance = (_wallet['available_balance_minor'] as num?)?.toInt() ?? 0;
    final pending = (_wallet['pending_balance_minor'] as num?)?.toInt() ?? 0;

    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? _LoadError(message: _error!, onRetry: _load)
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 12, 18, 32),
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [colors.primary, colors.tertiary],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(22),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              widget.icon,
                              color: colors.onPrimary,
                              size: 28,
                            ),
                            const Spacer(),
                            Text(
                              _currency,
                              style: theme.textTheme.labelLarge?.copyWith(
                                color: colors.onPrimary.withValues(alpha: .82),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 18),
                        Text(
                          'Available balance',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: colors.onPrimary.withValues(alpha: .82),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _money(balance, _currency),
                          style: theme.textTheme.headlineMedium?.copyWith(
                            color: colors.onPrimary,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (pending > 0) ...[
                          const SizedBox(height: 8),
                          Text(
                            'Pending ${_money(pending, _currency)}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colors.onPrimary.withValues(alpha: .82),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    widget.subtitle,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 17),
                  if (_isTopup)
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: _topup,
                            icon: const Icon(Icons.add_rounded),
                            label: const Text('Add funds'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _transfer,
                            icon: const Icon(Icons.swap_horiz_rounded),
                            label: const Text('Transfer'),
                          ),
                        ),
                      ],
                    )
                  else
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _withdraw,
                        icon: const Icon(Icons.south_west_rounded),
                        label: const Text('Withdraw from this wallet'),
                      ),
                    ),
                  const SizedBox(height: 25),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Wallet transactions',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      Text(
                        '$_totalEntries',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (_entries.isEmpty)
                    _EmptyTransactions(isTopup: _isTopup)
                  else
                    ..._entries.map(
                      (entry) => Padding(
                        padding: const EdgeInsets.only(bottom: 9),
                        child: _WalletLedgerRow(
                          entry: entry,
                          currency: _currency,
                        ),
                      ),
                    ),
                  if (_hasMore) ...[
                    const SizedBox(height: 5),
                    OutlinedButton.icon(
                      onPressed: _loadingMore ? null : _loadMore,
                      icon: _loadingMore
                          ? const SizedBox.square(
                              dimension: 17,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.expand_more_rounded),
                      label: Text(
                        _loadingMore ? 'Loading…' : 'Load more transactions',
                      ),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

class _WalletLedgerRow extends StatelessWidget {
  const _WalletLedgerRow({required this.entry, required this.currency});
  final Map<String, dynamic> entry;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final isCredit = entry['direction']?.toString() == 'credit';
    final amount = (entry['amount_minor'] as num?)?.toInt() ?? 0;
    final title = entry['description']?.toString().trim().isNotEmpty == true
        ? entry['description'].toString()
        : _pretty(entry['entry_type']?.toString() ?? 'Wallet transaction');
    final date = DateTime.tryParse(entry['created_at']?.toString() ?? '')
        ?.toLocal();
    final status = _pretty(entry['status']?.toString() ?? 'posted');

    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: .55)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: (isCredit ? colors.primary : colors.error)
                .withValues(alpha: .1),
            child: Icon(
              isCredit ? Icons.south_west_rounded : Icons.north_east_rounded,
              color: isCredit ? colors.primary : colors.error,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  [if (date != null) _date(date), status].join(' • '),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '${isCredit ? '+' : '−'}${_money(amount, currency)}',
            style: theme.textTheme.labelLarge?.copyWith(
              color: isCredit ? colors.primary : colors.error,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyTransactions extends StatelessWidget {
  const _EmptyTransactions({required this.isTopup});
  final bool isTopup;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Icon(
            isTopup ? Icons.add_card_rounded : Icons.receipt_long_rounded,
            color: colors.primary,
            size: 30,
          ),
          const SizedBox(height: 8),
          Text(
            isTopup ? 'No top-up activity yet.' : 'No wallet transactions yet.',
          ),
        ],
      ),
    );
  }
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 38),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Retry'),
          ),
        ],
      ),
    ),
  );
}

String _money(int minor, String currency) {
  final digits = _precision(currency);
  final value = minor / math.pow(10, digits);
  final symbol = const {
    'USD': r'$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
    'GHS': '₵',
  }[currency];
  return '${symbol ?? '$currency '}${value.toStringAsFixed(digits)}';
}

int _precision(String currency) {
  if (const {
    'BIF',
    'CLP',
    'DJF',
    'GNF',
    'ISK',
    'JPY',
    'KMF',
    'KRW',
    'PYG',
    'RWF',
    'UGX',
    'VND',
    'VUV',
    'XAF',
    'XOF',
    'XPF',
  }.contains(currency))
    return 0;
  if (const {
    'BHD',
    'IQD',
    'JOD',
    'KWD',
    'LYD',
    'OMR',
    'TND',
  }.contains(currency))
    return 3;
  return 2;
}

String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (word) =>
          word.isEmpty ? word : '${word[0].toUpperCase()}${word.substring(1)}',
    )
    .join(' ');

String _date(DateTime date) =>
    '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
