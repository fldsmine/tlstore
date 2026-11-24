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
      title: const Text('Referral team', style: TextStyle(fontWeight: FontWeight.w800)),
      centerTitle: false,
      actions: [
        IconButton(
          tooltip: 'Refresh team',
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh_rounded),
        ),
        const SizedBox(width: 4),
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
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.errorContainer,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(Icons.cloud_off_rounded, size: 30, color: Theme.of(context).colorScheme.onErrorContainer),
                  ),
                  const SizedBox(height: 16),
                  Text(_error!, textAlign: TextAlign.center, style: TextStyle(color: _muted, height: 1.45)),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Try again')),
                ],
              ),
            ),
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                _inviteCard(),
                const SizedBox(height: 26),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Your network', style: TextStyle(color: _ink, fontSize: 21, fontWeight: FontWeight.w900, letterSpacing: -.4)),
                          const SizedBox(height: 5),
                          Text('Explore your referral tiers and earnings.', style: TextStyle(color: _muted, fontSize: 12, height: 1.4)),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
                      decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(30)),
                      child: Text('${_tiers.length} ${_tiers.length == 1 ? 'tier' : 'tiers'}', style: TextStyle(color: Theme.of(context).colorScheme.onPrimaryContainer, fontSize: 11, fontWeight: FontWeight.w800)),
                    ),
                  ],
                ),
                const SizedBox(height: 15),
                if (_tiers.isEmpty)
                  Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerLow, borderRadius: BorderRadius.circular(22), border: Border.all(color: _line)),
                    child: Column(children: [Icon(Icons.groups_2_outlined, size: 38, color: _muted), const SizedBox(height: 10), Text('Your team is just getting started', style: TextStyle(color: _ink, fontWeight: FontWeight.w800)), const SizedBox(height: 5), Text('Share your referral code to start growing your network.', textAlign: TextAlign.center, style: TextStyle(color: _muted, fontSize: 12, height: 1.4))]),
                  )
                else
                  ..._tiers.asMap().entries.map((entry) => _tierCard(entry.value, entry.key)),
              ],
            ),
          ),
  );

  Widget _inviteCard() {
    final colors = Theme.of(context).colorScheme;
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [colors.primary, Color.lerp(colors.primary, const Color(0xFF17143D), .42)!],
        ),
        borderRadius: BorderRadius.circular(27),
        boxShadow: [BoxShadow(color: colors.primary.withOpacity(.23), blurRadius: 24, offset: const Offset(0, 12))],
      ),
      child: Stack(
        children: [
          Positioned(right: -28, top: -34, child: Container(width: 150, height: 150, decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: Colors.white.withOpacity(.10), width: 25)))),
          Positioned(right: 30, bottom: -65, child: Container(width: 125, height: 125, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withOpacity(.045)))),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(width: 42, height: 42, decoration: BoxDecoration(color: Colors.white.withOpacity(.16), borderRadius: BorderRadius.circular(14), border: Border.all(color: Colors.white.withOpacity(.16))), child: const Icon(Icons.card_giftcard_rounded, color: Colors.white, size: 22)),
                const SizedBox(width: 12),
                const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Grow your circle', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w900)), SizedBox(height: 3), Text('Invite friends. Build your team.', style: TextStyle(color: Colors.white70, fontSize: 11))])),
                Icon(Icons.auto_awesome_rounded, color: Colors.amber.shade200, size: 23),
              ]),
              const SizedBox(height: 22),
              Text('YOUR REFERRAL CODE', style: TextStyle(color: Colors.white.withOpacity(.72), fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 1.8)),
              const SizedBox(height: 8),
              Row(children: [
                Expanded(child: SelectableText(widget.referralCode, style: const TextStyle(color: Colors.white, fontSize: 29, fontWeight: FontWeight.w900, letterSpacing: 2.0))),
                Material(color: Colors.white, borderRadius: BorderRadius.circular(13), child: InkWell(borderRadius: BorderRadius.circular(13), onTap: () => _copy(widget.referralCode, 'Referral code copied.'), child: Padding(padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12), child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.copy_rounded, color: colors.primary, size: 16), const SizedBox(width: 7), Text('Copy', style: TextStyle(color: colors.primary, fontSize: 12, fontWeight: FontWeight.w900))])))),
              ]),
              const SizedBox(height: 18),
              Container(height: 1, color: Colors.white.withOpacity(.17)),
              const SizedBox(height: 14),
              Text('YOUR INVITE LINK', style: TextStyle(color: Colors.white.withOpacity(.72), fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 1.6)),
              const SizedBox(height: 8),
              Row(children: [
                Expanded(child: Text(widget.referralLink, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 11, height: 1.4))),
                const SizedBox(width: 10),
                IconButton.filledTonal(tooltip: 'Copy invite link', onPressed: () => _copy(widget.referralLink, 'Referral link copied.'), style: IconButton.styleFrom(backgroundColor: Colors.white.withOpacity(.16), foregroundColor: Colors.white), icon: const Icon(Icons.link_rounded)),
              ]),
            ]),
          ),
        ],
      ),
    );
  }

  Future<void> _copy(String value, String message) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (mounted)
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
  }

  Widget _tierCard(Map<String, dynamic> tier, int index) {
    final level = _int(tier['level']);
    final count = _int(tier['member_count']);
    final paid = _convertPaid(tier['paid_amounts']);
    final totalRewards = _rewardCount(tier['paid_amounts']);
    final colors = Theme.of(context).colorScheme;
    final accents = <Color>[colors.primary, colors.tertiary, const Color(0xFFB7791F), const Color(0xFF4776C5)];
    final accent = accents[index % accents.length];
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 0,
      color: colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22), side: BorderSide(color: _line.withOpacity(.8))),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => _ReferralTierMembersScreen(api: widget.api, token: widget.token, level: level))),
        child: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 14, 15),
            child: Row(children: [
              Container(width: 52, height: 52, decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [accent.withOpacity(.18), accent.withOpacity(.07)]), borderRadius: BorderRadius.circular(17), border: Border.all(color: accent.withOpacity(.28))), child: Center(child: Text('T$level', style: TextStyle(color: accent, fontSize: 17, fontWeight: FontWeight.w900)))),
              const SizedBox(width: 13),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Tier $level', style: TextStyle(color: _ink, fontSize: 15, fontWeight: FontWeight.w900)), const SizedBox(height: 5), Row(children: [Icon(Icons.people_alt_rounded, color: _muted, size: 14), const SizedBox(width: 5), Text('$count ${count == 1 ? 'member' : 'members'}', style: TextStyle(color: _muted, fontSize: 11, fontWeight: FontWeight.w500))])])),
              Container(padding: const EdgeInsets.all(9), decoration: BoxDecoration(color: colors.surfaceContainerHighest, shape: BoxShape.circle), child: Icon(Icons.arrow_forward_rounded, color: accent, size: 18)),
            ]),
          ),
          Container(height: 1, color: _line.withOpacity(.65)),
          Padding(padding: const EdgeInsets.all(14), child: Row(children: [
            Expanded(child: _tierMetric(icon: Icons.redeem_rounded, label: 'Paid rewards', value: '$totalRewards', accent: accent)),
            Container(width: 1, height: 38, color: _line),
            Expanded(child: _tierMetric(icon: Icons.account_balance_wallet_rounded, label: 'Commission paid', value: formatMajorMoney(paid, widget.api.preferredCurrency), accent: accent)),
          ])),
        ]),
      ),
    );
  }

  Widget _tierMetric({required IconData icon, required String label, required String value, required Color accent}) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 5),
    child: Row(children: [
      Container(width: 34, height: 34, decoration: BoxDecoration(color: accent.withOpacity(.10), borderRadius: BorderRadius.circular(11)), child: Icon(icon, size: 17, color: accent)),
      const SizedBox(width: 9),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: _muted, fontSize: 9, fontWeight: FontWeight.w600)), const SizedBox(height: 4), Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: _ink, fontSize: 12, fontWeight: FontWeight.w900))])),
    ]),
  );

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
      title: Text('Tier ${widget.level} members', style: const TextStyle(fontWeight: FontWeight.w800)),
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
                    padding: const EdgeInsets.fromLTRB(22, 30, 22, 32),
                    children: [
                      const SizedBox(height: 28),
                      _emptyTierState(),
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

  Widget _emptyTierState() {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.fromLTRB(22, 28, 22, 24),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: _line.withOpacity(.75)),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withOpacity(.045),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            width: 104,
            height: 104,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [colors.primaryContainer, colors.secondaryContainer],
              ),
            ),
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(Icons.groups_rounded, size: 48, color: colors.onPrimaryContainer),
                Positioned(
                  right: 8,
                  bottom: 8,
                  child: Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      color: colors.surface,
                      shape: BoxShape.circle,
                      border: Border.all(color: _line.withOpacity(.65)),
                    ),
                    child: Icon(Icons.add_rounded, size: 19, color: _primary),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: colors.primaryContainer.withOpacity(.65),
              borderRadius: BorderRadius.circular(30),
            ),
            child: Text(
              'TIER ${widget.level}',
              style: TextStyle(
                color: colors.onPrimaryContainer,
                fontSize: 10,
                fontWeight: FontWeight.w900,
                letterSpacing: 1.3,
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'Your team is just getting started',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: _ink,
              fontSize: 21,
              height: 1.2,
              fontWeight: FontWeight.w900,
              letterSpacing: -.35,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            'There are no members recorded in Tier ${widget.level} yet. When referrals are attributed to this tier, their profiles, status, rewards and paid commissions will appear here.',
            textAlign: TextAlign.center,
            style: TextStyle(color: _muted, fontSize: 13, height: 1.65),
          ),
          const SizedBox(height: 22),
          Container(
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: colors.surfaceContainerHighest.withOpacity(.55),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.lightbulb_outline_rounded, color: _primary, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Tip: head back to your referral team and share your invite code or link to help grow your network.',
                    style: TextStyle(color: _ink, fontSize: 12, height: 1.5),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 22),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: () => Navigator.of(context).pop(),
              icon: const Icon(Icons.rocket_launch_rounded, size: 18),
              label: const Text('Back to referral team'),
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
              ),
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded, size: 17),
            label: const Text('Refresh members'),
          ),
        ],
      ),
    );
  }

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
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _line.withOpacity(.8)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(.025), blurRadius: 14, offset: const Offset(0, 5))],
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
