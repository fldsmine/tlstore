import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../core/links/referral_link.dart';
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

  bool _loading = true;
  String? _error;
  String? _summaryError;
  String? _referralsError;
  Map<String, dynamic> _summary = const {};
  List<Map<String, dynamic>> _referrals = const [];

  String? get _referralCode {
    final value = _summary['code']?.toString().trim();
    return value == null || value.isEmpty ? null : value;
  }

  String? get _referralUri => _referralCode == null
      ? null
      : ReferralLink.forCode(_referralCode!).toString();

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
    String? summaryError;
    String? referralsError;
    final summaryRequest = widget.api.get('me/referral-code', widget.token);
    final referralsRequest = widget.api.get('me/referrals', widget.token);

    try {
      final response = await summaryRequest;
      final data = response['data'];
      if (data is Map<String, dynamic>) {
        summary = data;
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
    final children = <Widget>[
      Container(
        padding: const EdgeInsets.all(19),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF145A3D), Color(0xFF28704F)],
          ),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Invite friends to TrustLens',
              style: TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 7),
            const Text(
              'Share your referral code. Track your invited and qualified signups here.',
              style: TextStyle(
                color: Colors.white70,
                fontSize: 12,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 17),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: SelectableText(
                      _referralCode ?? '—',
                      style: const TextStyle(
                        color: Color(0xFF145A3D),
                        fontSize: 19,
                        letterSpacing: 2,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () async {
                      final code = _referralCode;
                      if (code != null) {
                        await Clipboard.setData(ClipboardData(text: code));
                        if (context.mounted)
                          showAuthMessage(context, 'Referral code copied.');
                      }
                    },
                    icon: const Icon(
                      Icons.copy_rounded,
                      color: Color(0xFF145A3D),
                    ),
                  ),
                ],
              ),
            ),
            if (_referralUri != null) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .96),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Your invite link',
                      style: TextStyle(
                        color: Color(0xFF145A3D),
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 5),
                    SelectableText(
                      _referralUri!,
                      style: const TextStyle(
                        color: Color(0xFF425048),
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        TextButton.icon(
                          onPressed: _copyReferralLink,
                          icon: const Icon(Icons.copy_rounded, size: 18),
                          label: const Text('Copy link'),
                        ),
                        const SizedBox(width: 6),
                        FilledButton.tonalIcon(
                          onPressed: _shareReferralLink,
                          // The app-wide filled-button theme uses an infinite
                          // minimum width for full-width form buttons. This
                          // button sits in a Row, so give it a finite width.
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(0, 50),
                          ),
                          icon: const Icon(Icons.share_rounded, size: 18),
                          label: const Text('Share'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
      const SizedBox(height: 14),
      Row(
        children: [
          Expanded(
            child: _stat(
              'Invited signups',
              _summary['attributed_signups']?.toString() ?? '0',
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _stat(
              'Qualified',
              _summary['qualified_signups']?.toString() ?? '0',
            ),
          ),
        ],
      ),
      const SizedBox(height: 22),
      const Text(
        'Your referrals',
        style: TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 17),
      ),
      const SizedBox(height: 10),
    ];
    if (_summaryError != null || _referralsError != null) {
      children.add(
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: _card(
            Row(
              children: [
                const Icon(Icons.info_outline_rounded, color: brandGreen),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    _summaryError != null && _referralsError != null
                        ? 'Referral details could not be refreshed.'
                        : _summaryError != null
                        ? 'Your invite code could not be refreshed.'
                        : 'Your referral list could not be refreshed.',
                    style: const TextStyle(color: mutedInk, fontSize: 12),
                  ),
                ),
                TextButton(onPressed: _load, child: const Text('Retry')),
              ],
            ),
          ),
        ),
      );
    }
    if (_referrals.isEmpty) {
      children.add(
        _card(
          const Text(
            'Invited friends will appear here after they sign up.',
            style: TextStyle(color: mutedInk, fontSize: 12),
          ),
        ),
      );
    } else {
      children.addAll(
        _referrals.map((ref) {
          final user = ref['referred_user'];
          final rewards = ref['rewards'] is List
              ? (ref['rewards'] as List).length
              : 0;
          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: _card(
              Row(
                children: [
                  const CircleAvatar(
                    backgroundColor: Color(0xFFE8F1E9),
                    child: Icon(Icons.person_outline, color: brandGreen),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user is Map
                              ? (user['username']?.toString() ?? 'Member')
                              : 'Member',
                          style: const TextStyle(
                            color: ink,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          rewards > 0
                              ? '$rewards reward${rewards == 1 ? '' : 's'} earned'
                              : 'Joined ${ref['attributed_at']?.toString() ?? ''}',
                          style: const TextStyle(color: mutedInk, fontSize: 10),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    _pretty(ref['status']?.toString() ?? 'pending'),
                    style: const TextStyle(
                      color: brandGreen,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
      );
    }
    final body = _loading && _summary.isEmpty && _referrals.isEmpty
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_error!, style: const TextStyle(color: mutedInk)),
                TextButton(onPressed: _load, child: const Text('Retry')),
              ],
            ),
          )
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              children: children,
            ),
          );
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text(
          'Refer & earn',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      ),
      body: body,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _referralCode == null ? null : _showPromotionalCard,
        backgroundColor: brandGreen,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.auto_awesome_rounded),
        label: const Text('Create promo card'),
      ),
    );
  }
}

Widget _stat(String title, String value) => _card(
  Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        value,
        style: const TextStyle(
          color: brandGreen,
          fontSize: 22,
          fontWeight: FontWeight.w900,
        ),
      ),
      const SizedBox(height: 3),
      Text(title, style: const TextStyle(color: mutedInk, fontSize: 11)),
    ],
  ),
);
Widget _card(Widget child) => Container(
  width: double.infinity,
  padding: const EdgeInsets.all(14),
  decoration: BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: const Color(0xFFE5E9E4)),
  ),
  child: child,
);
String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');
