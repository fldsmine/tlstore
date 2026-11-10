import 'dart:math';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../core/links/referral_link.dart';
import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import '../widgets/referral_card_share_dialog.dart';

class ReferralsScreen extends StatefulWidget {
  const ReferralsScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

  @override
  State<ReferralsScreen> createState() => _ReferralsScreenState();
}

class _ReferralsScreenState extends State<ReferralsScreen> {
  static const _cardBackgrounds = [
    'assets/cards/card-01.png',
    'assets/cards/card-02.png',
    'assets/cards/card-03.jpg',
    'assets/cards/card-04.jpg',
    'assets/cards/card-05.jpg',
    'assets/cards/card-06.jpg',
    'assets/cards/card-07.jpg',
    'assets/cards/card-08.png',
    'assets/cards/card-10.png',
  ];

  static const _green = Color(0xFF176342);
  static const _deepGreen = Color(0xFF104B32);
  static const _paleGreen = Color(0xFFEAF4ED);
  static const _ink = Color(0xFF17231D);
  static const _muted = Color(0xFF718078);
  static const _line = Color(0xFFE7ECE8);
  static const _canvas = Color(0xFFF6F8F6);

  bool _loading = true;
  String? _error;
  String? _summaryError;
  String? _referralsError;
  Map<String, dynamic> _summary = const {};
  List<Map<String, dynamic>> _referrals = const [];
  int? _totalReferrals;
  int? _currentPage;
  int? _lastPage;

  String? get _referralCode {
    final value = _summary['code']?.toString().trim();
    return value == null || value.isEmpty ? null : value;
  }

  String? get _referralUri => _referralCode == null
      ? null
      : ReferralLink.forCode(_referralCode!).toString();

  int get _invitedCount => _asInt(_summary['attributed_signups']);
  int get _qualifiedCount => _asInt(_summary['qualified_signups']);
  int get _pendingCount => (_invitedCount - _qualifiedCount).clamp(0, 1 << 30);

  int get _paidRewardsCount => _referrals.fold<int>(0, (total, referral) {
    final rewards = referral['rewards'];
    if (rewards is! List) return total;
    return total +
        rewards.where((reward) {
          return reward is Map &&
              reward['status']?.toString().toLowerCase() == 'paid';
        }).length;
  });

  double get _paidRewardTotal => _referrals.fold<num>(0, (total, referral) {
    final rewards = referral['rewards'];
    if (rewards is! List) return total;
    return total +
        rewards
            .whereType<Map>()
            .where((reward) {
              return reward['status']?.toString().toLowerCase() == 'paid';
            })
            .fold<num>(0, (sum, reward) {
              final currency = reward['currency']?.toString() ?? 'USD';
              final converted = widget.api.convertMajor(
                _asNum(reward['amount_minor']) /
                    math.pow(10, currencyPrecision(currency)),
                currency,
                widget.api.preferredCurrency,
              );
              return sum +
                  (converted ??
                      _asNum(reward['amount_minor']) /
                          math.pow(10, currencyPrecision(currency)));
            });
  }).toDouble();

  Future<void> _copyReferralCode() async {
    final code = _referralCode;
    if (code == null) return;
    await Clipboard.setData(ClipboardData(text: code));
    if (mounted) showAuthMessage(context, 'Referral code copied.');
  }

  Future<void> _copyReferralLink() async {
    final link = _referralUri;
    if (link == null) return;
    await Clipboard.setData(ClipboardData(text: link));
    if (mounted) showAuthMessage(context, 'Referral link copied.');
  }

  Future<void> _shareReferralLink() async {
    final link = _referralUri;
    if (link == null) return;
    await SharePlus.instance.share(
      ShareParams(text: 'Join me on TL Store: $link', subject: 'Join TL Store'),
    );
  }

  Future<void> _showPromotionalCard() async {
    final code = _referralCode;
    final link = _referralUri;
    if (code == null || link == null) {
      showAuthMessage(context, 'Your referral code is not available yet.');
      return;
    }
    final name = widget.user['full_name']?.toString().trim();
    final username = widget.user['username']?.toString().trim() ?? '';
    await ReferralCardShareDialog.show(
      context,
      name: name == null || name.isEmpty ? 'TL Store Member' : name,
      username: username,
      referralCode: code,
      referralLink: link,
      backgroundAsset:
          _cardBackgrounds[Random().nextInt(_cardBackgrounds.length)],
    );
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    await widget.api.loadExchangeRates(widget.token);
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
        _summaryError = null;
        _referralsError = null;
      });
    }

    Map<String, dynamic> summary = const {};
    List<Map<String, dynamic>> referrals = const [];
    int? total;
    int? currentPage;
    int? lastPage;
    String? summaryError;
    String? referralsError;

    final summaryRequest = widget.api.get('me/referral-code', widget.token);
    final referralsRequest = widget.api.get('me/referrals', widget.token);

    try {
      final response = await summaryRequest;
      final data = response['data'];
      if (data is Map<String, dynamic>) {
        summary = data;
      } else if (data is Map) {
        summary = Map<String, dynamic>.from(data);
      } else {
        summaryError = 'The referral code response was incomplete.';
      }
    } catch (error) {
      summaryError = _messageFor(error);
    }

    try {
      final response = await referralsRequest;
      final paginated = response['data'];
      final rows = paginated is Map ? paginated['data'] : paginated;
      if (rows is List) {
        referrals = rows
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList(growable: false);
        if (paginated is Map) {
          total = _asNullableInt(paginated['total']);
          currentPage = _asNullableInt(paginated['current_page']);
          lastPage = _asNullableInt(paginated['last_page']);
        }
      } else {
        referralsError = 'The referrals response was incomplete.';
      }
    } catch (error) {
      referralsError = _messageFor(error);
    }

    if (!mounted) return;
    setState(() {
      _summary = summary;
      _referrals = referrals;
      _totalReferrals = total;
      _currentPage = currentPage;
      _lastPage = lastPage;
      _summaryError = summaryError;
      _referralsError = referralsError;
      _error = summaryError != null && referralsError != null
          ? 'Referral information could not be loaded.'
          : null;
      _loading = false;
    });
  }

  String _messageFor(Object error) => error is AuthException
      ? error.message
      : 'A network or response error occurred. Please retry.';

  @override
  Widget build(BuildContext context) {
    final body = _loading && _summary.isEmpty && _referrals.isEmpty
        ? const Center(child: CircularProgressIndicator(color: _green))
        : _error != null
        ? _loadError()
        : RefreshIndicator(
            color: _green,
            onRefresh: _load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 112),
              children: [
                _buildInviteHero(),
                const SizedBox(height: 16),
                _buildStats(),
                const SizedBox(height: 25),
                _buildSectionHeading(),
                if (_summaryError != null || _referralsError != null) ...[
                  const SizedBox(height: 12),
                  _buildPartialError(),
                ],
                const SizedBox(height: 12),
                if (_referrals.isEmpty)
                  _buildEmptyState()
                else
                  ..._referrals.map(_buildReferralTile),
                if (_totalReferrals != null && _totalReferrals! > 0) ...[
                  const SizedBox(height: 6),
                  _buildPaginationSummary(),
                ],
              ],
            ),
          );

    return Scaffold(
      backgroundColor: _canvas,
      appBar: AppBar(
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: _canvas,
        foregroundColor: _ink,
        titleSpacing: 20,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Refer & earn',
              style: TextStyle(
                color: _ink,
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
            SizedBox(height: 1),
            Text(
              'Grow your network and track rewards',
              style: TextStyle(
                color: _muted,
                fontSize: 11,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh referrals',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: SafeArea(top: false, child: body),
      floatingActionButton: _referralCode == null
          ? null
          : FloatingActionButton.extended(
              onPressed: _showPromotionalCard,
              backgroundColor: _green,
              foregroundColor: Colors.white,
              elevation: 3,
              icon: const Icon(Icons.auto_awesome_rounded),
              label: const Text(
                'Create promo card',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
    );
  }

  Widget _loadError() => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              color: _paleGreen,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.cloud_off_rounded, color: _green, size: 30),
          ),
          const SizedBox(height: 16),
          const Text(
            'We couldn’t load your referrals',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: _ink,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            _error!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: _muted, fontSize: 12),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _load,
            style: FilledButton.styleFrom(
              backgroundColor: _green,
              foregroundColor: Colors.white,
            ),
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try again'),
          ),
        ],
      ),
    ),
  );

  Widget _buildInviteHero() => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(19),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [_deepGreen, Color(0xFF24744F), Color(0xFF36835D)],
      ),
      borderRadius: BorderRadius.circular(23),
      boxShadow: [
        BoxShadow(
          color: _deepGreen.withValues(alpha: .16),
          blurRadius: 20,
          offset: const Offset(0, 9),
        ),
      ],
    ),
    child: Stack(
      children: [
        Positioned(
          right: -20,
          top: -28,
          child: Icon(
            Icons.people_alt_rounded,
            size: 150,
            color: Colors.white.withValues(alpha: .07),
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .13),
                borderRadius: BorderRadius.circular(30),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.card_giftcard_rounded,
                    color: Color(0xFFD9F3DF),
                    size: 14,
                  ),
                  SizedBox(width: 6),
                  Text(
                    'YOUR REFERRAL REWARDS',
                    style: TextStyle(
                      color: Color(0xFFD9F3DF),
                      fontSize: 9,
                      letterSpacing: 1.1,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Good things are\nbetter when shared.',
              style: TextStyle(
                color: Colors.white,
                fontSize: 25,
                height: 1.12,
                fontWeight: FontWeight.w900,
                letterSpacing: -.5,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Invite friends to TL Store and follow their journey from signup to qualified referral.',
              style: TextStyle(
                color: Colors.white.withValues(alpha: .78),
                fontSize: 12,
                height: 1.45,
              ),
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.fromLTRB(13, 11, 7, 11),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'YOUR INVITE CODE',
                          style: TextStyle(
                            color: _muted,
                            fontSize: 9,
                            letterSpacing: .9,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        SelectableText(
                          _referralCode ?? 'Not available',
                          style: const TextStyle(
                            color: _deepGreen,
                            fontSize: 21,
                            letterSpacing: 2.2,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton.filledTonal(
                    onPressed: _referralCode == null ? null : _copyReferralCode,
                    style: IconButton.styleFrom(
                      backgroundColor: _paleGreen,
                      foregroundColor: _green,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(11),
                      ),
                    ),
                    icon: const Icon(Icons.copy_rounded, size: 19),
                    tooltip: 'Copy referral code',
                  ),
                ],
              ),
            ),
            if (_referralUri != null) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.fromLTRB(13, 11, 8, 8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .10),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: .16),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'YOUR PERSONAL INVITE LINK',
                      style: TextStyle(
                        color: Color(0xFFD9F3DF),
                        fontSize: 9,
                        letterSpacing: .8,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    SelectableText(
                      _referralUri!,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        height: 1.3,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Spacer(),
                        TextButton.icon(
                          onPressed: _copyReferralLink,
                          style: TextButton.styleFrom(
                            foregroundColor: Colors.white,
                            visualDensity: VisualDensity.compact,
                          ),
                          icon: const Icon(Icons.copy_rounded, size: 15),
                          label: const Text('Copy link'),
                        ),
                        const SizedBox(width: 5),
                        FilledButton.icon(
                          onPressed: _shareReferralLink,
                          style: FilledButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: _deepGreen,
                            minimumSize: const Size(0, 38),
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          icon: const Icon(Icons.share_rounded, size: 15),
                          label: const Text(
                            'Share',
                            style: TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ],
    ),
  );

  Widget _buildStats() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Padding(
        padding: EdgeInsets.only(left: 2, bottom: 10),
        child: Text(
          'YOUR PERFORMANCE',
          style: TextStyle(
            color: _muted,
            fontSize: 10,
            letterSpacing: 1,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      Row(
        children: [
          Expanded(
            child: _statCard(
              title: 'Invited',
              value: _invitedCount.toString(),
              caption: 'Signups attributed',
              icon: Icons.person_add_alt_1_rounded,
              tint: const Color(0xFFEAF1FF),
              iconColor: const Color(0xFF4169A8),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _statCard(
              title: 'Qualified',
              value: _qualifiedCount.toString(),
              caption: 'Successful referrals',
              icon: Icons.verified_rounded,
              tint: _paleGreen,
              iconColor: _green,
            ),
          ),
        ],
      ),
      const SizedBox(height: 10),
      Row(
        children: [
          Expanded(
            child: _statCard(
              title: 'In progress',
              value: _pendingCount.toString(),
              caption: 'Awaiting qualification',
              icon: Icons.hourglass_top_rounded,
              tint: const Color(0xFFFFF4E4),
              iconColor: const Color(0xFFB77A21),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _statCard(
              title: 'Rewards paid',
              value: _paidRewardsCount.toString(),
              caption: formatMajorMoney(
                _paidRewardTotal,
                widget.api.preferredCurrency,
              ),
              icon: Icons.account_balance_wallet_rounded,
              tint: const Color(0xFFF1EBFA),
              iconColor: const Color(0xFF7854A6),
            ),
          ),
        ],
      ),
    ],
  );

  Widget _statCard({
    required String title,
    required String value,
    required String caption,
    required IconData icon,
    required Color tint,
    required Color iconColor,
  }) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: _line),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              height: 31,
              width: 31,
              decoration: BoxDecoration(
                color: tint,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: iconColor, size: 17),
            ),
            const Spacer(),
            Text(
              value,
              style: const TextStyle(
                color: _ink,
                fontSize: 23,
                fontWeight: FontWeight.w900,
                letterSpacing: -.5,
              ),
            ),
          ],
        ),
        const SizedBox(height: 11),
        Text(
          title,
          style: const TextStyle(
            color: _ink,
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          caption,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: _muted, fontSize: 9.5),
        ),
      ],
    ),
  );

  Widget _buildSectionHeading() => Row(
    crossAxisAlignment: CrossAxisAlignment.end,
    children: [
      const Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Your referrals',
              style: TextStyle(
                color: _ink,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            SizedBox(height: 3),
            Text(
              'See who joined using your invite',
              style: TextStyle(color: _muted, fontSize: 11),
            ),
          ],
        ),
      ),
      if (_totalReferrals != null)
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: _paleGreen,
            borderRadius: BorderRadius.circular(30),
          ),
          child: Text(
            '$_totalReferrals total',
            style: const TextStyle(
              color: _green,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
    ],
  );

  Widget _buildPartialError() {
    final message = _summaryError != null && _referralsError != null
        ? 'Some referral information could not be refreshed.'
        : _summaryError != null
        ? 'Your invite code could not be refreshed.'
        : 'Your referral list could not be refreshed.';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7E9),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: const Color(0xFFF3E2C2)),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.info_outline_rounded,
            color: Color(0xFFAE7625),
            size: 19,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: Color(0xFF785B2F), fontSize: 11),
            ),
          ),
          TextButton(
            onPressed: _load,
            style: TextButton.styleFrom(foregroundColor: _green),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(22, 27, 22, 25),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(19),
      border: Border.all(color: _line),
    ),
    child: Column(
      children: [
        Container(
          height: 62,
          width: 62,
          decoration: const BoxDecoration(
            color: _paleGreen,
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.group_add_rounded, color: _green, size: 29),
        ),
        const SizedBox(height: 13),
        const Text(
          'Your network starts here',
          style: TextStyle(
            color: _ink,
            fontSize: 15,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        const Text(
          'Share your invite link with friends. When they sign up, their referral activity will appear here.',
          textAlign: TextAlign.center,
          style: TextStyle(color: _muted, fontSize: 11, height: 1.5),
        ),
        if (_referralUri != null) ...[
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: _shareReferralLink,
            style: OutlinedButton.styleFrom(
              foregroundColor: _green,
              side: const BorderSide(color: _green),
            ),
            icon: const Icon(Icons.share_rounded, size: 17),
            label: const Text('Invite someone'),
          ),
        ],
      ],
    ),
  );

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
          _asNum(reward['amount_minor']) /
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
    final isQualified = status.toLowerCase() == 'qualified';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
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
                  color: isQualified ? _paleGreen : const Color(0xFFFFF4E4),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  Icons.person_rounded,
                  color: isQualified ? _green : const Color(0xFFB77A21),
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
                      style: const TextStyle(
                        color: _ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Invited $date',
                      style: const TextStyle(color: _muted, fontSize: 10),
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
          if (rewards.isNotEmpty) ...[
            const SizedBox(height: 10),
            Align(
              alignment: Alignment.centerLeft,
              child: Wrap(
                spacing: 6,
                runSpacing: 6,
                children: rewards.map((reward) {
                  final rewardStatus =
                      reward['status']?.toString() ?? 'pending';
                  final rewardType = _pretty(
                    reward['reward_type']?.toString() ?? 'reward',
                  );
                  final paid = rewardStatus.toLowerCase() == 'paid';
                  return Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: paid ? _paleGreen : const Color(0xFFF2F4F2),
                      borderRadius: BorderRadius.circular(30),
                    ),
                    child: Text(
                      '$rewardType · ${_pretty(rewardStatus)}',
                      style: TextStyle(
                        color: paid ? _green : _muted,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ],
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
            Text(label, style: const TextStyle(color: _muted, fontSize: 9)),
            const SizedBox(height: 2),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
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
    final color = qualified ? _green : const Color(0xFFAD7625);
    final background = qualified ? _paleGreen : const Color(0xFFFFF4E4);
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

  Widget _buildPaginationSummary() {
    final total = _totalReferrals ?? _referrals.length;
    final page = _currentPage ?? 1;
    final last = _lastPage ?? 1;
    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 8),
      child: Center(
        child: Text(
          'Showing ${_referrals.length} of $total referrals · Page $page of $last',
          style: const TextStyle(color: _muted, fontSize: 10),
        ),
      ),
    );
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int? _asNullableInt(dynamic value) {
    if (value == null) return null;
    return int.tryParse(value.toString());
  }

  static num _asNum(dynamic value) {
    if (value is num) return value;
    return num.tryParse(value?.toString() ?? '') ?? 0;
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
}
