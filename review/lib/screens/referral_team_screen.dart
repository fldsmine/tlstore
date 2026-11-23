import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';

class ReferralTeamScreen extends StatefulWidget {
  const ReferralTeamScreen({
    super.key,
    required this.api,
    required this.token,
    required this.referralCode,
    required this.referralLink,
  });

  final AuthApi api;
  final String token;
  final String referralCode;
  final String referralLink;

  @override
  State<ReferralTeamScreen> createState() => _ReferralTeamScreenState();
}

class _ReferralTeamScreenState extends State<ReferralTeamScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _tiers = const [];

  Color get _ink => Theme.of(context).colorScheme.onSurface;
  Color get _muted => Theme.of(context).colorScheme.onSurfaceVariant;
  Color get _primary => Theme.of(context).colorScheme.primary;
  Color get _line => Theme.of(context).colorScheme.outlineVariant;

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
      await widget.api.loadExchangeRates(widget.token);
      final response = await widget.api.get('me/referral-team', widget.token);
      final data = response['data'];
      final rows = data is Map ? data['tiers'] : null;
      if (rows is! List) throw const FormatException('Missing tier data');
      if (!mounted) return;
      setState(() {
        _tiers = rows
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Your referral team could not be loaded. Please try again.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    appBar: AppBar(
      title: const Text('Referral team'),
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
    ),
    body: _loading
        ? Center(child: CircularProgressIndicator(color: _primary))
        : _error != null
        ? Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: _load, child: const Text('Retry')),
                ],
              ),
            ),
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                _inviteCard(),
                const SizedBox(height: 18),
                Text(
                  'Team & members',
                  style: TextStyle(
                    color: _ink,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  'Tap a tier to see its members and referral earnings. Rates are set by each subscription package.',
                  style: TextStyle(color: _muted, fontSize: 12, height: 1.4),
                ),
                const SizedBox(height: 14),
                ..._tiers.map(_tierCard),
              ],
            ),
          ),
  );

  Widget _inviteCard() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: _line),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(Icons.groups_rounded, color: _primary),
            const SizedBox(width: 9),
            Text(
              'Invite friends',
              style: TextStyle(color: _ink, fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Center(
          child: SelectableText(
            widget.referralCode,
            style: TextStyle(
              color: _primary,
              fontSize: 27,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.3,
            ),
          ),
        ),
        Center(
          child: TextButton.icon(
            onPressed: () =>
                _copy(widget.referralCode, 'Referral code copied.'),
            icon: const Icon(Icons.copy_rounded, size: 16),
            label: const Text('Copy share code'),
          ),
        ),
        const Divider(height: 20),
        SelectableText(
          widget.referralLink,
          textAlign: TextAlign.center,
          style: TextStyle(color: _primary, fontSize: 12),
        ),
        Center(
          child: TextButton.icon(
            onPressed: () =>
                _copy(widget.referralLink, 'Referral link copied.'),
            icon: const Icon(Icons.link_rounded, size: 16),
            label: const Text('Copy share link'),
          ),
        ),
      ],
    ),
  );

  Future<void> _copy(String value, String message) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (mounted)
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
  }

  Widget _tierCard(Map<String, dynamic> tier) {
    final level = _int(tier['level']);
    final count = _int(tier['member_count']);
    final paid = _convertPaid(tier['paid_amounts']);
    final totalRewards = _rewardCount(tier['paid_amounts']);
    final colors = Theme.of(context).colorScheme;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 0,
      color: colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(19),
        side: BorderSide(color: _line),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(19),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => _ReferralTierMembersScreen(
              api: widget.api,
              token: widget.token,
              level: level,
            ),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(15),
          child: Column(
            children: [
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: colors.primaryContainer,
                      border: Border.all(color: colors.primary),
                    ),
                    child: Text(
                      'T$level',
                      style: TextStyle(
                        color: colors.onPrimaryContainer,
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Tier $level',
                          style: TextStyle(
                            color: _ink,
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '$count ${count == 1 ? 'member' : 'members'}',
                          style: TextStyle(color: _muted, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    Icons.arrow_forward_ios_rounded,
                    size: 17,
                    color: colors.secondary,
                  ),
                ],
              ),
              const SizedBox(height: 13),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 11,
                ),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Expanded(child: _metric('Paid rewards', '$totalRewards')),
                    Container(width: 1, height: 32, color: _line),
                    Expanded(
                      child: _metric(
                        'Commission paid',
                        formatMajorMoney(paid, widget.api.preferredCurrency),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _metric(String label, String value) => Column(
    children: [
      Text(label, style: TextStyle(color: _muted, fontSize: 10)),
      const SizedBox(height: 4),
      Text(
        value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: _primary,
          fontSize: 13,
          fontWeight: FontWeight.w900,
        ),
      ),
    ],
  );

  double _convertPaid(dynamic raw) {
    if (raw is! List) return 0;
    var total = 0.0;
    for (final row in raw.whereType<Map>()) {
      final currency = row['currency']?.toString() ?? 'USD';
      final major =
          _num(row['amount_minor']) / math.pow(10, currencyPrecision(currency));
      total +=
          widget.api.convertMajor(
            major,
            currency,
            widget.api.preferredCurrency,
          ) ??
          major;
    }
    return total;
  }

  int _rewardCount(dynamic raw) => raw is List
      ? raw.whereType<Map>().fold<int>(
          0,
          (sum, row) => sum + _int(row['reward_count']),
        )
      : 0;
  static int _int(dynamic value) =>
      value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
  static num _num(dynamic value) =>
      value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0;
}

class _ReferralTierMembersScreen extends StatefulWidget {
  const _ReferralTierMembersScreen({
    required this.api,
    required this.token,
    required this.level,
  });
  final AuthApi api;
  final String token;
  final int level;

  @override
  State<_ReferralTierMembersScreen> createState() =>
      _ReferralTierMembersScreenState();
}

class _ReferralTierMembersScreenState
    extends State<_ReferralTierMembersScreen> {
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  List<Map<String, dynamic>> _members = const [];
  String? _preferredCurrency;
  int _page = 1;
  int _lastPage = 1;

  Color get _ink => Theme.of(context).colorScheme.onSurface;
  Color get _muted => Theme.of(context).colorScheme.onSurfaceVariant;
  Color get _primary => Theme.of(context).colorScheme.primary;
  Color get _line => Theme.of(context).colorScheme.outlineVariant;
  Color get _paleGreen => Theme.of(context).colorScheme.primaryContainer;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool append = false}) async {
    setState(() {
      if (append) {
        _loadingMore = true;
      } else {
        _loading = true;
      }
      _error = null;
    });
    try {
      final nextPage = append ? _page + 1 : 1;
      final response = await widget.api.get(
        'me/referrals?level=${widget.level}&per_page=100&page=$nextPage',
        widget.token,
      );
      final pageData = response['data'];
      final rows = pageData is Map ? pageData['data'] : null;
      if (rows is! List) throw const FormatException('Missing referrals');
      if (!mounted) return;
      setState(() {
        final loaded = rows
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
        _members = append ? [..._members, ...loaded] : loaded;
        _preferredCurrency = widget.api.preferredCurrency;
        _page = nextPage;
        _lastPage = pageData is Map ? _int(pageData['last_page']) : 1;
        _loading = false;
        _loadingMore = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        if (!append) _error = 'Could not load this tier’s members.';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text('Tier ${widget.level} members'),
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
      ],
    ),
    body: _loading
        ? Center(child: CircularProgressIndicator(color: _primary))
        : _error != null
        ? Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!),
                const SizedBox(height: 10),
                FilledButton(onPressed: _load, child: const Text('Retry')),
              ],
            ),
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: _members.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      const SizedBox(height: 100),
                      Icon(Icons.group_outlined, size: 48, color: _muted),
                      const SizedBox(height: 12),
                      Center(
                        child: Text(
                          'No members in this tier yet.',
                          style: TextStyle(color: _muted),
                        ),
                      ),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _members.length + (_page < _lastPage ? 1 : 0),
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (_, index) => index < _members.length
                        ? _buildReferralTile(_members[index])
                        : Center(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              child: OutlinedButton.icon(
                                onPressed: _loadingMore
                                    ? null
                                    : () => _load(append: true),
                                icon: _loadingMore
                                    ? const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Icon(Icons.expand_more_rounded),
                                label: Text(
                                  _loadingMore
                                      ? 'Loading'
                                      : 'Load more members',
                                ),
                              ),
                            ),
                          ),
                  ),
          ),
  );

  Widget _memberTile(Map<String, dynamic> row) {
    final user = row['referred_user'];
    final username = user is Map
        ? user['username']?.toString() ?? 'TL Store member'
        : 'TL Store member';
    final rewards = row['rewards'] is List
        ? (row['rewards'] as List).whereType<Map>().toList()
        : <Map>[];
    final paidRewards = rewards.where(
      (reward) => reward['status']?.toString().toLowerCase() == 'paid',
    );
    var total = 0.0;
    for (final reward in paidRewards) {
      final currency = reward['currency']?.toString() ?? 'USD';
      final amount =
          _num(reward['amount_minor']) /
          math.pow(10, currencyPrecision(currency));
      total +=
          widget.api.convertMajor(
            amount,
            currency,
            _preferredCurrency ?? widget.api.preferredCurrency,
          ) ??
          amount;
    }
    final date = DateTime.tryParse(row['attributed_at']?.toString() ?? '')
        ?.toLocal();
    final joined = date == null
        ? 'recently'
        : '${date.day}/${date.month}/${date.year}';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _line),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              Icons.person_rounded,
              color: Theme.of(context).colorScheme.onPrimaryContainer,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  username.startsWith('@') ? username : '@$username',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: _ink, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  'Joined $joined · ${rewards.length} ${rewards.length == 1 ? 'reward' : 'rewards'}',
                  style: TextStyle(color: _muted, fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            formatMajorMoney(
              total,
              _preferredCurrency ?? widget.api.preferredCurrency,
            ),
            style: TextStyle(
              color: _primary,
              fontWeight: FontWeight.w900,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReferralTile(Map<String, dynamic> referral) {
    final referredUser = referral['referred_user'];
    final username = referredUser is Map
        ? referredUser['username']?.toString() ?? 'TL Store member'
        : 'TL Store member';
    final status = referral['status']?.toString() ?? 'pending';
    final rewards = referral['rewards'] is List
        ? (referral['rewards'] as List).whereType<Map>().toList()
        : <Map>[];
    final paidRewards = rewards
        .where((reward) => reward['status']?.toString().toLowerCase() == 'paid')
        .toList();
    final rewardTotal = paidRewards.fold<double>(0, (sum, reward) {
      final currency = reward['currency']?.toString() ?? 'USD';
      final amount =
          _num(reward['amount_minor']) /
          math.pow(10, currencyPrecision(currency));
      return sum +
          (widget.api.convertMajor(
                amount,
                currency,
                widget.api.preferredCurrency,
              ) ??
              amount);
    });
    final date = _dateLabel(referral['attributed_at']?.toString());
    final referralLevel = _num(referral['referral_level'])
        .toInt()
        .clamp(1, 3);
    final isQualified = status.toLowerCase() == 'qualified';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: _line),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                height: 43,
                width: 43,
                decoration: BoxDecoration(
                  color: isQualified
                      ? _paleGreen
                      : Theme.of(context).colorScheme.tertiaryContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  Icons.person_rounded,
                  color: isQualified
                      ? _primary
                      : Theme.of(context).colorScheme.onTertiaryContainer,
                  size: 22,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      username.startsWith('@') ? username : '@$username',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: _ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Level $referralLevel · Joined $date',
                      style: TextStyle(color: _muted, fontSize: 10),
                    ),
                  ],
                ),
              ),
              _statusChip(status),
            ],
          ),
          const SizedBox(height: 13),
          Container(height: 1, color: _line),
          const SizedBox(height: 11),
          Row(
            children: [
              Expanded(
                child: _detailItem(
                  icon: Icons.card_giftcard_rounded,
                  label: 'Rewards',
                  value: '${rewards.length} earned',
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _detailItem(
                  icon: Icons.payments_outlined,
                  label: 'Paid amount',
                  value: rewardTotal > 0
                      ? formatMajorMoney(
                          rewardTotal,
                          widget.api.preferredCurrency,
                        )
                      : '—',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _detailItem({
    required IconData icon,
    required String label,
    required String value,
  }) => Row(
    children: [
      Icon(icon, color: _muted, size: 15),
      const SizedBox(width: 6),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(color: _muted, fontSize: 9)),
            const SizedBox(height: 2),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: _ink,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    ],
  );

  Widget _statusChip(String status) {
    final qualified = status.toLowerCase() == 'qualified';
    final color = qualified ? _primary : Theme.of(context).colorScheme.tertiary;
    final background = qualified
        ? _paleGreen
        : Theme.of(context).colorScheme.tertiaryContainer;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            qualified ? Icons.check_circle_rounded : Icons.schedule_rounded,
            size: 12,
            color: color,
          ),
          const SizedBox(width: 4),
          Text(
            _pretty(status),
            style: TextStyle(
              color: color,
              fontSize: 9,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  static String _dateLabel(String? value) {
    if (value == null || value.isEmpty) return 'recently';
    final date = DateTime.tryParse(value)?.toLocal();
    if (date == null) return 'recently';
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
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }

  static String _pretty(String value) => value
      .replaceAll('_', ' ')
      .split(' ')
      .map(
        (part) => part.isEmpty
            ? part
            : '${part[0].toUpperCase()}${part.substring(1)}',
      )
      .join(' ');

  static num _num(dynamic value) =>
      value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0;
  static int _int(dynamic value) =>
      value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 1;
}
