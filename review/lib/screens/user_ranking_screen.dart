import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../services/media_url.dart';

class UserRankingScreen extends StatefulWidget {
  const UserRankingScreen({super.key, required this.api, required this.token});

  final AuthApi api;
  final String token;

  @override
  State<UserRankingScreen> createState() => _UserRankingScreenState();
}

class _UserRankingScreenState extends State<UserRankingScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = true;

  Color get _primary => Theme.of(context).colorScheme.primary;
  Color get _dark => Theme.of(context).colorScheme.onSurface;
  Color get _background => Theme.of(context).scaffoldBackgroundColor;
  Color get _green => Theme.of(context).colorScheme.tertiary;

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
      });
    }

    try {
      await widget.api.loadExchangeRates(widget.token);
      final response = await widget.api.get('me/ranking', widget.token);
      final data = response['data'];

      if (mounted) {
        setState(() {
          _data = data is Map<String, dynamic> ? data : {};
        });
      }
    } on AuthException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Something went wrong. Please try again.';
        });
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _background,
      appBar: AppBar(
        backgroundColor: _background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        title: Text(
          'Rank Rewards',
          style: TextStyle(
            fontSize: 21,
            fontWeight: FontWeight.w800,
            color: _dark,
          ),
        ),
        actions: [
          IconButton(
            onPressed: _load,
            icon: Icon(Icons.refresh_rounded, color: _dark),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: _loading
          ? Center(child: CircularProgressIndicator(color: _primary))
          : _error != null
          ? _errorView()
          : _content(),
    );
  }

  Widget _content() {
    final data = _data ?? {};
    final enabled = data['enabled'] == true;

    final levels = (data['levels'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .toList();

    final qualifications = (data['qualifications'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .toList();

    final progress = data['progress'] is Map<String, dynamic>
        ? data['progress'] as Map<String, dynamic>
        : <String, dynamic>{};

    final eligibleLevels = levels
        .where((level) => level['eligible'] == true)
        .length;

    return RefreshIndicator(
      color: _primary,
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
        children: [
          _heroSection(enabled, eligibleLevels, levels.length),
          const SizedBox(height: 22),

          _sectionHeader(
            'Your performance',
            'Your current referral statistics',
          ),
          const SizedBox(height: 12),

          _performanceGrid(progress),
          const SizedBox(height: 26),

          if (qualifications.isNotEmpty) ...[
            _sectionHeader(
              'Your qualified rewards',
              'Rewards you have successfully unlocked',
            ),
            const SizedBox(height: 12),
            for (final item in qualifications) _qualificationCard(item),
            const SizedBox(height: 22),
          ],

          _sectionHeader(
            'Ranking levels',
            'Complete the requirements to unlock rewards',
          ),
          const SizedBox(height: 12),

          if (levels.isEmpty)
            _emptyState()
          else ...[
            // The first level that is not yet qualified is the only
            // level currently in progress. Every level after it stays locked.
            for (int i = 0; i < levels.length; i++)
              _levelCard(levels[i], i, locked: i > _currentLevelIndex(levels)),
          ],

          const SizedBox(height: 20),
          _footerNote(),
        ],
      ),
    );
  }

  // HERO SECTION

  Widget _heroSection(bool enabled, int eligibleLevels, int totalLevels) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF28235D), Color(0xFF5146C9), Color(0xFF7568FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: _primary.withOpacity(0.20),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -15,
            top: -18,
            child: Icon(
              Icons.workspace_premium_rounded,
              size: 125,
              color: Colors.white.withOpacity(0.09),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 11,
                      vertical: 7,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(30),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          enabled
                              ? Icons.stars_rounded
                              : Icons.pause_circle_outline,
                          size: 15,
                          color: Colors.white,
                        ),
                        const SizedBox(width: 6),
                        Text(
                          enabled ? 'REWARDS PROGRAM ACTIVE' : 'PROGRAM PAUSED',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 22),
              Text(
                'Build your referral rank',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 25,
                  fontWeight: FontWeight.w900,
                  height: 1.2,
                ),
              ),
              const SizedBox(height: 9),
              Text(
                enabled
                    ? 'Grow your referrals, meet your targets and earn monthly bonus wallet rewards.'
                    : 'The ranking rewards program is currently paused.',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.82),
                  fontSize: 13,
                  height: 1.6,
                ),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: _heroStat(
                      Icons.workspace_premium_rounded,
                      '$eligibleLevels',
                      'Ranks unlocked',
                    ),
                  ),
                  Container(
                    width: 1,
                    height: 42,
                    color: Colors.white.withOpacity(0.2),
                  ),
                  const SizedBox(width: 18),
                  Expanded(
                    child: _heroStat(
                      Icons.card_giftcard_rounded,
                      '$totalLevels',
                      'Available levels',
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _heroStat(IconData icon, String value, String label) {
    return Row(
      children: [
        Icon(icon, color: const Color(0xFFFFD77A), size: 25),
        const SizedBox(width: 10),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 21,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                label,
                style: TextStyle(
                  color: Colors.white.withOpacity(0.75),
                  fontSize: 10,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // SECTION HEADER

  Widget _sectionHeader(String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            color: _dark,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12),
        ),
      ],
    );
  }

  // PERFORMANCE STATISTICS

  Widget _performanceGrid(Map<String, dynamic> progress) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _statCard(
                title: 'Subscribed referrals',
                value: '${progress['subscribed_referrals'] ?? 0}',
                icon: Icons.people_alt_rounded,
                color: Theme.of(context).colorScheme.primary,
                background: Theme.of(context).colorScheme.primaryContainer,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _statCard(
                title: 'Referral cash inflow',
                value: _usd(progress['referral_cash_inflow_usd']),
                icon: Icons.groups_rounded,
                color: Theme.of(context).colorScheme.tertiary,
                background: Theme.of(context).colorScheme.tertiaryContainer,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        _statCard(
          title: 'Your personal cash inflow',
          value: _usd(progress['user_cash_inflow_usd']),
          icon: Icons.account_balance_wallet_rounded,
          color: Theme.of(context).colorScheme.secondary,
          background: Theme.of(context).colorScheme.secondaryContainer,
          horizontal: true,
        ),
      ],
    );
  }

  Widget _statCard({
    required String title,
    required String value,
    required IconData icon,
    required Color color,
    required Color background,
    bool horizontal = false,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: horizontal
          ? Row(
              children: [
                _statIcon(icon, color, background),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontSize: 11,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        value,
                        style: TextStyle(
                          color: _dark,
                          fontSize: 21,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _statIcon(icon, color, background),
                const SizedBox(height: 14),
                Text(
                  title,
                  style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 11),
                ),
                const SizedBox(height: 6),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    value,
                    style: TextStyle(
                      color: _dark,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _statIcon(IconData icon, Color color, Color background) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Icon(icon, color: color, size: 21),
    );
  }

  // RANK CARDS

  // RANK CARDS

  int _currentLevelIndex(List<Map<String, dynamic>> levels) {
    final index = levels.indexWhere((level) => level['eligible'] != true);
    return index == -1 ? levels.length : index;
  }

  Widget _levelCard(
    Map<String, dynamic> level,
    int index, {
    required bool locked,
  }) {
    final requirements = level['requirements'] is Map<String, dynamic>
        ? level['requirements'] as Map<String, dynamic>
        : <String, dynamic>{};

    final progress = level['progress'] is Map<String, dynamic>
        ? level['progress'] as Map<String, dynamic>
        : <String, dynamic>{};

    final active = level['is_active'] == true;
    final eligible = level['eligible'] == true;

    final referralGoal = _asDouble(requirements['subscribed_referrals']);
    final referralCurrent = _asDouble(progress['subscribed_referrals']);

    final referralCashGoal = _asDouble(
      requirements['referral_cash_inflow_usd'],
    );
    final referralCashCurrent = _asDouble(progress['referral_cash_inflow_usd']);

    final ownGoal = _asDouble(requirements['user_cash_inflow_usd']);
    final ownCurrent = _asDouble(progress['user_cash_inflow_usd']);

    final color = _rankColor(index);

    final totalProgress =
        [
          _fraction(referralCurrent, referralGoal),
          _fraction(referralCashCurrent, referralCashGoal),
          _fraction(ownCurrent, ownGoal),
        ].reduce((a, b) => a + b) /
        3;

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: eligible ? _green.withOpacity(0.4) : Theme.of(context).colorScheme.outlineVariant,
          width: eligible ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
          color: Theme.of(context).colorScheme.shadow.withOpacity(0.025),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(17),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _rankIcon(level['icon']?.toString(), color),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        level['name']?.toString() ?? 'Rank',
                        style: TextStyle(
                          color: _dark,
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        'LEVEL ${level['level'] ?? index + 1}',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1,
                        ),
                      ),
                    ],
                  ),
                ),
                _statusBadge(active, eligible, locked: locked),
                if (locked) ...[
                  const SizedBox(width: 7),
                  Icon(
                    Icons.lock_rounded,
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    size: 19,
                  ),
                ],
              ],
            ),

            const SizedBox(height: 17),

            Container(
              padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
              decoration: BoxDecoration(
                color: color.withOpacity(0.07),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Row(
                children: [
                  Icon(Icons.card_giftcard_rounded, color: color, size: 20),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'MONTHLY REWARD',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          _usd(level['monthly_reward_usd']),
                          style: TextStyle(
                            color: color,
                            fontSize: 19,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    height: 32,
                    width: 1,
                    color: color.withOpacity(0.15),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        'DURATION',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        '${level['reward_duration_months'] ?? 0} months',
                        style: TextStyle(
                          color: _dark,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            if (!locked) ...[
              const SizedBox(height: 17),

              Row(
                children: [
                  Expanded(
                    child: Text(
                      'REQUIREMENTS PROGRESS',
                      style: TextStyle(
                        color: _dark,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.7,
                      ),
                    ),
                  ),
                  Text(
                    '${(totalProgress * 100).round()}%',
                    style: TextStyle(
                      color: color,
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              _goal(
                'Subscribed referrals',
                referralCurrent,
                referralGoal,
                color: color,
                integer: true,
              ),

              const SizedBox(height: 13),

              _goal(
                'Referral cash inflow',
                referralCashCurrent,
                referralCashGoal,
                color: color,
              ),

              const SizedBox(height: 13),

              _goal('Your cash inflow', ownCurrent, ownGoal, color: color),

              if (eligible && !locked) ...[
                const SizedBox(height: 14),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primaryContainer,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.check_circle_rounded,
                        color: Theme.of(context).colorScheme.tertiary,
                        size: 17,
                      ),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Congratulations! You qualify for this rank.',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.tertiary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              if (locked) ...[
                const SizedBox(height: 14),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surfaceContainerHighest,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.lock_outline_rounded,
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                        size: 17,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Unlock this level by qualifying for the previous rank.',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurface,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              if (!active && !locked) ...[
                const SizedBox(height: 12),
                Text(
                  'This ranking level is currently inactive.',
                  style: TextStyle(color: Theme.of(context).colorScheme.error, fontSize: 11),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }

  Widget _goal(
    String title,
    num current,
    num target, {
    required Color color,
    bool integer = false,
  }) {
    final fraction = _fraction(current, target);

    final currentText = integer ? _asInt(current).toString() : _usd(current);

    final targetText = integer ? _asInt(target).toString() : _usd(target);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurface,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            Text(
              '$currentText / $targetText',
              style: TextStyle(
                color: _dark,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        const SizedBox(height: 7),
        ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: LinearProgressIndicator(
            value: fraction,
            minHeight: 7,
            backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
            valueColor: AlwaysStoppedAnimation<Color>(color),
          ),
        ),
      ],
    );
  }

  Widget _statusBadge(bool active, bool eligible, {bool locked = false}) {
    final color = locked
        ? Theme.of(context).colorScheme.onSurfaceVariant
        : !active
        ? Theme.of(context).colorScheme.error
        : eligible
        ? _green
        : Theme.of(context).colorScheme.secondary;

    final label = locked
        ? 'Locked'
        : !active
        ? 'Inactive'
        : eligible
        ? 'Qualified'
        : 'In progress';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: color.withOpacity(0.10),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  Widget _rankIcon(String? path, Color color) {
    Widget fallback() =>
        Icon(Icons.military_tech_rounded, color: color, size: 29);

    if (path != null && path.startsWith('assets/ranks/')) {
      return Container(
        width: 53,
        height: 53,
        padding: const EdgeInsets.all(7),
        decoration: BoxDecoration(
          color: color.withOpacity(0.09),
          borderRadius: BorderRadius.circular(15),
        ),
        child: SvgPicture.asset(
          path,
          fit: BoxFit.contain,
          placeholderBuilder: (_) => fallback(),
        ),
      );
    }

    final url = MediaUrl.resolve(path);

    return Container(
      width: 53,
      height: 53,
      decoration: BoxDecoration(
        color: color.withOpacity(0.09),
        borderRadius: BorderRadius.circular(15),
      ),
      child: url == null
          ? fallback()
          : ClipRRect(
              borderRadius: BorderRadius.circular(15),
              child: Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => fallback(),
              ),
            ),
    );
  }

  Color _rankColor(int index) {
    final palette = Theme.of(context).colorScheme;
    final colors = [
      palette.tertiary,
      palette.secondary,
      palette.outline,
      palette.primary,
      palette.secondary,
      palette.primary,
    ];

    return colors[index % colors.length];
  }

  // QUALIFIED REWARDS

  Widget _qualificationCard(Map<String, dynamic> item) {
    final level = item['level'] is Map<String, dynamic>
        ? item['level'] as Map<String, dynamic>
        : <String, dynamic>{};

    final total = _asInt(
      item['reward_duration_months'] ?? level['reward_duration_months'],
    );

    final paid = _asInt(item['rewards_paid']);
    final completed = item['status'] == 'completed';

    final reward = item['monthly_reward_usd'] ?? level['monthly_reward_usd'];

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Row(
        children: [
          _rankIcon(level['icon']?.toString(), _primary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  level['name']?.toString() ?? 'Rank',
                  style: TextStyle(
                    color: _dark,
                    fontWeight: FontWeight.w900,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_usd(reward)} / month',
                  style: TextStyle(
                    color: _primary,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  completed
                      ? 'Reward period complete'
                      : 'Next payment: ${_date(item['next_reward_at'])}',
                  style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 10),
                ),
                const SizedBox(height: 9),
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: LinearProgressIndicator(
                    value: total <= 0 ? 0 : (paid / total).clamp(0.0, 1.0),
                    minHeight: 5,
                    backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
                    valueColor: AlwaysStoppedAnimation(_green),
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '$paid of $total payments',
                  style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 10),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Icon(
            completed ? Icons.check_circle_rounded : Icons.schedule_rounded,
            color: completed ? _green : Theme.of(context).colorScheme.tertiary,
            size: 22,
          ),
        ],
      ),
    );
  }

  // EMPTY STATE

  Widget _emptyState() {
    return Container(
      padding: const EdgeInsets.all(30),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Icon(
            Icons.emoji_events_outlined,
            size: 48,
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
          const SizedBox(height: 12),
          Text(
            'No ranking levels available',
            style: TextStyle(color: _dark, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          Text(
            'Please check back later.',
            style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _footerNote() {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline_rounded, color: _primary, size: 20),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Meet all the requirements of a ranking level to qualify for its monthly bonus wallet rewards. Your progress updates as your referral activity grows.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onPrimaryContainer,
                fontSize: 11,
                height: 1.6,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ERROR VIEW

  Widget _errorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(25),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off_rounded,
              size: 55,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 15),
            Text(
              'Unable to load rankings',
              style: TextStyle(
                color: _dark,
                fontSize: 17,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _error ?? 'Please try again.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 12),
            ),
            const SizedBox(height: 18),
            ElevatedButton.icon(
              onPressed: _load,
              icon: Icon(Icons.refresh_rounded),
              label: Text('Try again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                foregroundColor: Theme.of(context).colorScheme.onPrimary,
                padding: const EdgeInsets.symmetric(
                  horizontal: 22,
                  vertical: 12,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // HELPERS

  double _fraction(num current, num target) {
    if (target <= 0) return 1.0;
    return (current / target).clamp(0.0, 1.0).toDouble();
  }

  int _asInt(dynamic value) {
    return value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  }

  double _asDouble(dynamic value) {
    return value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
  }

  String _number(dynamic value) {
    return _asDouble(value).toStringAsFixed(2);
  }

  String _usd(dynamic value) => moneyConverted(
    widget.api,
    value,
    'USD',
    2,
    widget.api.preferredCurrency,
    isMinor: false,
  );

  String _date(dynamic value) {
    if (value == null) return 'Pending';

    return DateTime.tryParse(value.toString())
            ?.toLocal()
            .toString()
            .split('.')
            .first ??
        'Pending';
  }
}

