import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:review/core/theme/app_colors.dart';

import '../../../../core/currency/money.dart';
import '../../../../services/auth_api.dart';

import 'package:review/widgets/quick_slideshow.dart';

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _rows(Object? value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : <Map<String, dynamic>>[];

Map<String, dynamic>? _firstWhereOrNull(
  Iterable<Map<String, dynamic>> rows,
  bool Function(Map<String, dynamic>) test,
) {
  for (final row in rows) {
    if (test(row)) return row;
  }
  return null;
}

String _label(String value) => value.isEmpty
    ? value
    : value
          .split(RegExp(r'[_\-]+'))
          .map((s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}')
          .join(' ');

String _minorMoney(
  AuthApi api,
  Object? amount,
  String sourceCurrency,
  Object? precision,
  String preferredCurrency,
) => moneyConverted(
  api,
  amount,
  sourceCurrency,
  precision,
  preferredCurrency,
  isMinor: true,
);

const _monthNames = <String>[
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

String _relativeTime(Object? value) {
  final text = '${value ?? ''}';
  if (text.isEmpty) return '';
  final parsed = DateTime.tryParse(text);
  if (parsed == null) return '';
  final local = parsed.toLocal();
  final difference = DateTime.now().difference(local);
  if (difference.isNegative || difference.inSeconds < 60) return 'Just now';
  if (difference.inMinutes < 60) return '${difference.inMinutes}m ago';
  if (difference.inHours < 24) return '${difference.inHours}h ago';
  if (difference.inDays == 1) return 'Yesterday';
  if (difference.inDays < 7) return '${difference.inDays}d ago';
  return '${local.day} ${_monthNames[local.month - 1]}';
}

String _formatCacheAge(Duration? age) {
  if (age == null || age.isNegative) return 'Live rates';
  if (age.inMinutes < 1) return 'Synced just now';
  if (age.inMinutes < 60) return 'Synced ${age.inMinutes}m ago';
  return 'Synced ${age.inHours}h ago';
}

String? _remoteImage(Map<String, dynamic> data) {
  for (final key in const ['image', 'logo', 'logo_url', 'image_url', 'icon']) {
    final value = data[key]?.toString().trim();
    if (value == null || value.isEmpty) continue;
    if (value.startsWith('http://') || value.startsWith('https://'))
      return value;
  }
  return null;
}

// ---------------------------------------------------------------------------
// Brand palette + product artwork
// ---------------------------------------------------------------------------

class _Brand {
  const _Brand(this.start, this.end, this.accent, this.icon);

  final Color start;
  final Color end;
  final Color accent;
  final IconData icon;

  LinearGradient get gradient => LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [start, end],
  );
}

_Brand _brandFor(String key) {
  switch (key) {
    case 'airtime':
      return const _Brand(
        Color(0xFFF44735),
        Color(0xFFB64B30),
        Color(0xFF145A3D),
        Icons.phone_iphone_rounded,
      );
    case 'data':
      return const _Brand(
        Color(0xFF6366F1),
        Color(0xFFA855F7),
        Color(0xFF6D5AE8),
        Icons.wifi_rounded,
      );
    case 'tv':
      return const _Brand(
        Color(0xFF2563EB),
        Color(0xFF06B6D4),
        Color(0xFF1D7FD8),
        Icons.live_tv_rounded,
      );
    case 'electricity':
      return const _Brand(
        Color(0xFF145A3D),
        Color(0xFFF97316),
        Color(0xFFDE8600),
        Icons.bolt_rounded,
      );
    case 'education':
      return const _Brand(
        Color(0xFF34D399),
        Color(0xFF0D9488),
        Color(0xFF0E9F6E),
        Icons.school_rounded,
      );
    case 'foreign-airtime':
      return const _Brand(
        Color(0xFF8B5CF6),
        Color(0xFFEC4899),
        Color(0xFFA855F7),
        Icons.public_rounded,
      );
    default:
      return const _Brand(
        Color(0xFF6366F1),
        Color(0xFF0EA5E9),
        Color(0xFF4F7CF7),
        Icons.bolt_rounded,
      );
  }
}

String _serviceTagline(String key) {
  switch (key) {
    case 'airtime':
      return 'Instant phone recharge';
    case 'data':
      return 'Bundles for every network';
    case 'tv':
      return 'DSTV, GOtv & more';
    case 'electricity':
      return 'Prepaid & postpaid meters';
    case 'education':
      return 'Exam pins & result checkers';
    case 'foreign-airtime':
      return 'Top up international lines';
    default:
      return 'Pay in a few taps';
  }
}

const _networkArt = <String>[
  'assets/vas/mtn.jpg',
  'assets/vas/glo.jpg',
  'assets/vas/airtel.jpg',
  'assets/vas/9mobile.png',
];
const _tvArt = <String>[
  'assets/vas/dstv.jpg',
  'assets/vas/startimes.jpg',
  'assets/vas/smile.jpg',
];
const _electricityArt = 'assets/vas/electricity.jpg';
const _ikejaArt = 'assets/vas/phcn.jpg';
const _tvGenericArt = 'assets/vas/tv.jpg';
const _networkGenericArt = 'assets/vas/network.jpg';
const _educationArt = 'assets/vas/education.jpg';
const _foreignArt = 'assets/vas/foreign-airtime.jpg';

List<String> _serviceArt(String key) {
  if (key == 'airtime' || key == 'data') return _networkArt;
  if (key == 'tv') return _tvArt;
  if (key == 'electricity') return const [_electricityArt];
  if (key == 'education') return const [_educationArt];
  if (key == 'foreign-airtime') return const [_foreignArt];
  return const <String>[];
}

String? _providerArt(String value, {String? serviceKey}) {
  final id = value.toLowerCase().trim();
  if (id.isEmpty) return null;
  if (id.contains('mtn')) return 'assets/vas/mtn.jpg';
  if (id.contains('glo')) return 'assets/vas/glo.jpg';
  if (id.contains('airtel')) return 'assets/vas/airtel.jpg';
  if (id.contains('9mobile') || id.contains('etisalat')) {
    return 'assets/vas/9mobile.png';
  }
  if (id.contains('dstv')) return 'assets/vas/dstv.jpg';
  if (id.contains('gotv') || id.contains('go-tv')) return _tvGenericArt;
  if (id.contains('startimes') || id.contains('star-times')) {
    return 'assets/vas/startimes.jpg';
  }
  if (id.contains('smile')) return 'assets/vas/smile.jpg';
  if (id.contains('foreign')) return _foreignArt;
  if (id.contains('waec') ||
      id.contains('neco') ||
      id.contains('jamb') ||
      id.contains('nabteb')) {
    return _educationArt;
  }
  if (serviceKey == 'education') return _educationArt;
  // Ikeja Electric is the only DisCo with supplied brand artwork.
  if (id.contains('ikeja') || id.contains('ikedc')) return _ikejaArt;
  if (serviceKey == 'electricity' ||
      id.contains('electric') ||
      id.contains('phed') ||
      id.contains('ekedc') ||
      id.contains('aedc') ||
      id.contains('ibedc') ||
      id.contains('kaedco') ||
      id.contains('eedc') ||
      id.contains('bedc') ||
      id.contains('jed')) {
    return _electricityArt;
  }
  if (serviceKey == 'tv') return _tvGenericArt;
  if (serviceKey == 'foreign-airtime') return _foreignArt;
  if (serviceKey == 'airtime' || serviceKey == 'data') {
    return _networkGenericArt;
  }
  return null;
}

/// The six products featured in the Quick recharge slideshow.
///
/// Every entry uses a service icon and short, single-line copy: the card clamps
/// eyebrow, title and subtitle to one
/// line each and truncates with an ellipsis, so keep strings tight.
/// Swap, reorder or extend this list — [QuickSlideShow] adapts to any length.

const _quickSlides = <QuickySlide>[
  QuickySlide(
    serviceKey: 'airtime',
    providerId: 'mtn',
    icon: Icons.phone_android_rounded,
    eyebrow: 'MTN · Instant airtime',
    title: 'Stay connected, every time',
    subtitle: 'Top up your MTN line in seconds, anytime.',
    ctaLabel: 'Recharge MTN',
  ),
  QuickySlide(
    serviceKey: 'data',
    providerId: 'glo',
    icon: Icons.wifi_rounded,
    eyebrow: 'Glo · Data bundles',
    title: 'Data that keeps up with you',
    subtitle: 'From 1.5GB to 75GB — stay online all month.',
    ctaLabel: 'Buy Glo data',
  ),
  QuickySlide(
    serviceKey: 'data',
    providerId: 'airtel',
    icon: Icons.wifi_rounded,
    eyebrow: 'Airtel · Data bundles',
    title: 'Stream, scroll, repeat',
    subtitle: 'Big bundles at small prices, delivered instantly.',
    ctaLabel: 'Buy Airtel data',
  ),
  QuickySlide(
    serviceKey: 'tv',
    providerId: 'dstv',
    icon: Icons.live_tv_rounded,
    eyebrow: 'DStv · TV subscription',
    title: 'Your shows, uninterrupted',
    subtitle: 'Renew DStv, GOtv or StarTimes from home.',
    ctaLabel: 'Renew DStv',
  ),
  QuickySlide(
    serviceKey: 'electricity',
    providerId: 'ikeja-electric',
    icon: Icons.bolt_rounded,
    eyebrow: 'Ikeja Electric · Prepaid',
    title: 'Power up in one tap',
    subtitle: 'Grab your prepaid token in seconds.',
    ctaLabel: 'Buy power',
  ),
  QuickySlide(
    serviceKey: 'education',
    icon: Icons.school_rounded,
    eyebrow: 'Education · Exam pins',
    title: 'Exam pins, sorted',
    subtitle: 'WAEC, NECO and JAMB pins, delivered fast.',
    ctaLabel: 'Buy exam pin',
  ),
];

const _quickRechargePicks = <(String, String)>[
  ('airtime', 'mtn'),
  ('data', 'glo'),
  ('data', 'airtel'),
  ('tv', 'dstv'),
  ('electricity', 'ikeja'),
  ('education', 'waec'),
];

class _ArtImage extends StatelessWidget {
  const _ArtImage({required this.path});

  final String path;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Image.asset(
      path,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => ColoredBox(
        color: scheme.surface,
        child: Icon(
          Icons.image_outlined,
          color: scheme.onSurfaceVariant,
          size: 18,
        ),
      ),
    );
  }
}

/// Last-resort tile for a provider with no artwork yet. It intentionally shows
/// a category icon rather than initials, so no product is ever represented by
/// an abbreviation.
class _ArtworkPlaceholder extends StatelessWidget {
  const _ArtworkPlaceholder({this.serviceKey, this.size = 46});

  final String? serviceKey;
  final double size;

  IconData get _icon {
    switch (serviceKey) {
      case 'airtime':
        return Icons.phone_iphone_rounded;
      case 'data':
        return Icons.wifi_rounded;
      case 'tv':
        return Icons.live_tv_rounded;
      case 'electricity':
        return Icons.bolt_rounded;
      case 'education':
        return Icons.school_rounded;
      case 'foreign-airtime':
        return Icons.public_rounded;
      default:
        return Icons.image_outlined;
    }
  }

  @override
  Widget build(BuildContext context) {
    final brand = _brandFor(serviceKey ?? '');
    return Container(
      decoration: BoxDecoration(gradient: brand.gradient),
      alignment: Alignment.center,
      child: Icon(_icon, color: Colors.white, size: size * 0.36),
    );
  }
}

class _ProviderLogo extends StatelessWidget {
  const _ProviderLogo({
    required this.provider,
    this.serviceKey,
    this.size = 46,
    this.radius = 14,
  });

  final Map<String, dynamic> provider;
  final String? serviceKey;
  final double size;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final label =
        '${provider['label'] ?? provider['name'] ?? provider['id'] ?? ''}';
    final remote = _remoteImage(provider);
    final asset =
        _providerArt(
          '${provider['id'] ?? provider['code'] ?? ''}',
          serviceKey: serviceKey,
        ) ??
        _providerArt('$label', serviceKey: serviceKey);
    final fallback = _ArtworkPlaceholder(serviceKey: serviceKey, size: size);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .6)),
      ),
      clipBehavior: Clip.antiAlias,
      child: remote != null
          ? Image.network(
              remote,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => fallback,
            )
          : asset != null
          ? Image.asset(
              asset,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => fallback,
            )
          : fallback,
    );
  }
}

class _ServiceArt extends StatelessWidget {
  const _ServiceArt({
    required this.serviceKey,
    this.imageUrl,
    this.size = 52,
    this.radius = 15,
  });

  final String serviceKey;
  final String? imageUrl;
  final double size;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = _brandFor(serviceKey);
    final art = _serviceArt(serviceKey);
    final Widget content;
    if (imageUrl != null && imageUrl!.isNotEmpty) {
      content = Image.network(
        imageUrl!,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => _iconTile(),
      );
    } else if (art.isEmpty) {
      content = _iconTile();
    } else if (art.length == 1) {
      content = _ArtImage(path: art.first);
    } else {
      content = _collage(art);
    }
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: brand.accent.withValues(alpha: .22)),
        boxShadow: [
          BoxShadow(
            color: brand.accent.withValues(alpha: .18),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: content,
    );
  }

  Widget _iconTile() {
    final brand = _brandFor(serviceKey);
    return Container(
      decoration: BoxDecoration(gradient: brand.gradient),
      alignment: Alignment.center,
      child: Icon(brand.icon, color: Colors.white, size: size * 0.5),
    );
  }

  Widget _collage(List<String> paths) {
    final items = paths.take(4).toList();
    return ColoredBox(
      color: Colors.white,
      child: Column(
        children: [
          for (var row = 0; row < 2; row++)
            Expanded(
              child: Row(
                children: [
                  for (var column = 0; column < 2; column++)
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.all(1),
                        child: (row * 2 + column) < items.length
                            ? _ArtImage(path: items[row * 2 + column])
                            : const ColoredBox(color: Colors.white),
                      ),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Small shared widgets
// ---------------------------------------------------------------------------

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, this.subtitle, this.action});

  final String title;
  final String? subtitle;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.3,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (action != null) action!,
        ],
      ),
    );
  }
}

class _NoticeCard extends StatelessWidget {
  const _NoticeCard({
    required this.icon,
    required this.title,
    required this.text,
    this.color,
    this.action,
  });

  final IconData icon;
  final String title;
  final String text;
  final Color? color;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final tone = color ?? scheme.primary;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: tone.withValues(alpha: .16)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: tone.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: tone),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  text,
                  style: Theme.of(context).textTheme.bodyMedium
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
                if (action != null) ...[const SizedBox(height: 10), action!],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.text,
    this.accent,
    this.action,
  });

  final IconData icon;
  final String title;
  final String text;
  final Color? accent;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final tone = accent ?? scheme.primary;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 26),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: tone.withValues(alpha: .16)),
      ),
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  tone.withValues(alpha: .22),
                  tone.withValues(alpha: .08),
                ],
              ),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Icon(icon, color: tone, size: 28),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          Text(
            text,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: scheme.onSurfaceVariant),
          ),
          if (action != null) ...[const SizedBox(height: 14), action!],
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final tone = _statusColor(status, scheme);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: .13),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 5,
            height: 5,
            decoration: BoxDecoration(color: tone, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Text(
            _label(status),
            style: TextStyle(
              color: tone,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

Color _statusColor(String status, ColorScheme scheme) {
  switch (status) {
    case 'delivered':
    case 'successful':
    case 'completed':
    case 'success':
      return const Color(0xFF0F9D58);
    case 'pending':
    case 'processing':
      return const Color(0xFFE0A106);
    case 'failed':
    case 'reversed':
    case 'refunded':
      return scheme.error;
    default:
      return scheme.primary;
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value, this.trailing});

  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
          ),
          if (trailing != null) ...[const SizedBox(width: 4), trailing!],
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Home
// ---------------------------------------------------------------------------

class _QuickSnapshot {
  const _QuickSnapshot({
    required this.token,
    required this.enabled,
    required this.providerReady,
    required this.services,
    required this.orders,
    required this.wallet,
  });

  final String token;
  final bool enabled;
  final bool providerReady;
  final List<Map<String, dynamic>> services;
  final List<Map<String, dynamic>> orders;
  final Map<String, dynamic> wallet;

  static _QuickSnapshot? _current;

  static _QuickSnapshot? read(String token) {
    final snapshot = _current;
    if (snapshot == null || snapshot.token != token) return null;
    return snapshot;
  }

  static void save(_QuickSnapshot snapshot) => _current = snapshot;
}

class VasHomeScreen extends StatefulWidget {
  const VasHomeScreen({
    super.key,
    required this.api,
    required this.token,
    required this.currency,
    this.securityPinSet = false,
  });

  final AuthApi api;
  final String token;
  final String currency;
  final bool securityPinSet;

  @override
  State<VasHomeScreen> createState() => _VasHomeScreenState();
}

class _VasHomeScreenState extends State<VasHomeScreen> {
  bool _loading = true;
  bool _refreshing = false;
  String? _error;
  bool _enabled = false;
  bool _providerReady = false;
  List<Map<String, dynamic>> _services = [];
  List<Map<String, dynamic>> _orders = [];
  Map<String, dynamic> _topupWallet = {};

  @override
  void initState() {
    super.initState();
    final snapshot = _QuickSnapshot.read(widget.token);
    if (snapshot != null) {
      _enabled = snapshot.enabled;
      _providerReady = snapshot.providerReady;
      _services = snapshot.services;
      _orders = snapshot.orders;
      _topupWallet = snapshot.wallet;
      _loading = false;
    }
    _load();
  }

  Future<void> _load({bool forceRefresh = false}) async {
    if (!mounted) return;
    setState(() {
      _error = null;
      if (_services.isEmpty) {
        _loading = true;
      } else {
        _refreshing = true;
      }
    });

    String? failure;
    Future<T?> attempt<T>(Future<T> future) async {
      try {
        return await future;
      } catch (error) {
        failure ??= error.toString();
        return null;
      }
    }

    final catalogFuture = attempt(
      widget.api.vasCatalog(widget.token, forceRefresh: forceRefresh),
    );
    final walletFuture = attempt(
      widget.api.wallets(widget.token, forceRefresh: forceRefresh),
    );
    final ordersFuture = attempt(
      widget.api.vasOrders(
        widget.token,
        perPage: 4,
        forceRefresh: forceRefresh,
      ),
    );

    final catalog = await catalogFuture;
    final wallets = await walletFuture;
    final orders = await ordersFuture;
    if (!mounted) return;

    setState(() {
      _loading = false;
      _refreshing = false;
      _error = failure;
      if (catalog != null) {
        final data = _map(catalog['data']);
        _enabled = data['enabled'] == true;
        _providerReady = data['provider_ready'] == true;
        _services = _rows(data['services']);
      }
      if (wallets != null) {
        _topupWallet =
            _firstWhereOrNull(wallets, (w) => '${w['type']}' == 'topup_fund') ??
            (wallets.isNotEmpty ? wallets.first : <String, dynamic>{});
      }
      if (orders != null) _orders = _rows(orders['data']);
    });

    _QuickSnapshot.save(
      _QuickSnapshot(
        token: widget.token,
        enabled: _enabled,
        providerReady: _providerReady,
        services: _services,
        orders: _orders,
        wallet: _topupWallet,
      ),
    );
  }

  String get _balanceText {
    if (_topupWallet.isEmpty) return '—';
    final currency = '${_topupWallet['currency'] ?? widget.currency}';
    final precision =
        (_topupWallet['currency_minor_units'] as num?)?.toInt() ??
        currencyPrecision(currency);
    final amount =
        ((_topupWallet['balance_minor'] as num?)?.toDouble() ?? 0) /
        pow(10, precision);
    final converted = widget.api.convertMajor(
      amount,
      currency,
      widget.currency,
    );
    return converted == null
        ? formatMajorMoney(amount, currency)
        : formatMajorMoney(converted, widget.currency);
  }

  Future<void> _openSlide(int index, QuickySlide slide) async {
    final key = slide.serviceKey;
    final service = key == null
        ? null
        : _firstWhereOrNull(_services, (item) => '${item['key']}' == key);
    if (service == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${slide.title} is not available right now.')),
      );
      return;
    }
    final provider = _matchProvider(
      _rows(service['providers']),
      slide.providerId,
    );
    await _openService(
      service,
      providerId: provider == null ? null : '${provider['id']}',
    );
  }

  Map<String, dynamic>? _matchProvider(
    List<Map<String, dynamic>> providers,
    String? wanted,
  ) {
    if (wanted == null || wanted.isEmpty) return null;
    String normalise(String value) =>
        value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');
    final target = normalise(wanted);
    for (final provider in providers) {
      final id = normalise('${provider['id'] ?? ''}');
      final label = normalise('${provider['label'] ?? provider['name'] ?? ''}');
      if ((id.isNotEmpty && (id == target || id.contains(target))) ||
          (label.isNotEmpty && label.contains(target))) {
        return provider;
      }
    }
    return null;
  }

  List<Map<String, dynamic>> get _quickServices {
    final picks = <Map<String, dynamic>>[];
    for (final (serviceKey, providerKey) in _quickRechargePicks) {
      final service = _firstWhereOrNull(
        _services,
        (item) => '${item['key']}' == serviceKey,
      );
      final providers = service == null ? <Map<String, dynamic>>[] : _rows(service['providers']);
      final provider = _matchProvider(providers, providerKey) ??
          (providers.isEmpty ? null : providers.first);
      picks.add({
        'service_key': serviceKey,
        'service': service ?? <String, dynamic>{'key': serviceKey},
        'provider': provider ?? <String, dynamic>{'id': providerKey, 'label': _label(providerKey)},
      });
    }
    return picks;
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Airtime & bills'),
        actions: [
          IconButton(
            onPressed: _openHistory,
            icon: const Icon(Icons.receipt_long_rounded),
            tooltip: 'My purchases',
          ),
          IconButton(
            onPressed: _refreshing ? null : () => _load(forceRefresh: true),
            icon: _refreshing
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh prices',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _load(forceRefresh: true),
        child: _loading
            ? const _HomeSkeleton()
            : ListView(
                padding: const EdgeInsets.fromLTRB(18, 4, 18, 32),
                children: [
                  _VasHero(
                    balanceText: _balanceText,
                    syncedLabel: _syncedLabel,
                    onHistory: _openHistory,
                    refreshing: _refreshing,
                  ),
                  const SizedBox(height: 18),
                  if (!_enabled || !_providerReady)
                    _NoticeCard(
                      icon: !_enabled
                          ? Icons.pause_circle_outline_rounded
                          : Icons.cloud_off_rounded,
                      title: !_enabled
                          ? 'Services coming soon'
                          : 'Service temporarily unavailable',
                      text: !_enabled
                          ? 'Airtime, data and bill payments will appear here when they are enabled.'
                          : 'The payment provider is not ready. Please check again later.',
                      color: scheme.tertiary,
                    )
                  else ...[
                    if (_quickServices.isNotEmpty) ...[
                      _QuickRecharge(
                        services: _quickServices,
                        onSelected: (selection) {
                          final serviceKey = selection['service_key']?.toString();
                          final service = _firstWhereOrNull(
                            _services,
                            (item) => '${item['key']}' == serviceKey,
                          );
                          if (service == null) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('${_label(serviceKey ?? 'Service')} is unavailable right now.')),
                            );
                            return;
                          }
                          final provider = _matchProvider(
                            _rows(service['providers']),
                            '${(selection['provider'] as Map)['id'] ?? ''}',
                          );
                          _openService(
                            service,
                            providerId: provider == null ? null : '${provider['id']}',
                          );
                        },
                      ),
                      const SizedBox(height: 18),
                    ],
                    /*QuickSlideShow(
                      slides: _quickSlides,
                      // Page-owned height: the card never grows with its copy.
                      height: kSlideShowHeight,
                      onSlideTap: _openSlide,
                    ),
                    const SizedBox(height: 22),*/
                    _SectionHeading(
                      title: 'Pay a bill',
                      subtitle: 'Airtime, data, TV and utilities',
                      action: Icon(
                        Icons.auto_awesome_rounded,
                        color: scheme.primary,
                        size: 20,
                      ),
                    ),
                    _serviceGrid(),
                    const SizedBox(height: 24),
                    _SectionHeading(
                      title: 'Recent activity',
                      subtitle: 'Your latest top-ups and bills',
                      action: TextButton.icon(
                        onPressed: _openHistory,
                        icon: const Icon(Icons.arrow_forward_rounded, size: 17),
                        label: const Text('All orders'),
                      ),
                    ),
                    if (_orders.isEmpty)
                      _EmptyState(
                        icon: Icons.receipt_long_rounded,
                        title: 'No purchases yet',
                        text: 'Your airtime and bill payment receipts will show here.',
                        accent: scheme.primary,
                      )
                    else
                      ..._orders.map(
                        (order) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _OrderTile(
                            order: order,
                            currency: widget.currency,
                            api: widget.api,
                            onTap: () => _showOrder(order),
                          ),
                        ),
                      ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 16),
                    _NoticeCard(
                      icon: Icons.wifi_off_rounded,
                      title: 'Showing saved data',
                      text: _error!,
                      color: scheme.error,
                      action: TextButton(
                        onPressed: () => _load(forceRefresh: true),
                        child: const Text('Try again'),
                      ),
                    ),
                  ],
                ],
              ),
      ),
    );
  }

  String get _syncedLabel => _formatCacheAge(widget.api.vasCacheAge());

  Widget _serviceGrid() {
    if (_services.isEmpty) {
      return const _NoticeCard(
        icon: Icons.widgets_outlined,
        title: 'No services available',
        text: 'There are no active VAS services right now.',
      );
    }
    final wideService = _firstWhereOrNull(
          _services,
          (service) => '${service['key']}' == 'education',
        ) ??
        (_services.length > 4 ? _services[4] : null);
    final gridServices = _services
        .where((service) => !identical(service, wideService))
        .toList(growable: false);

    return Column(
      children: [
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth > 700
                ? 4
                : (constraints.maxWidth > 480 ? 3 : 2);
            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: columns,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: columns == 2 ? 0.86 : 1.0,
              ),
              itemCount: gridServices.length,
              itemBuilder: (context, index) {
                final service = gridServices[index];
                return _ServiceCard(
                  service: service,
                  onTap: () => _openService(service),
                );
              },
            );
          },
        ),
        if (wideService != null) ...[
          const SizedBox(height: 12),
          _ServiceListCard(
            service: wideService,
            onTap: () => _openService(wideService),
          ),
        ],
      ],
    );
  }

  Future<void> _openService(
    Map<String, dynamic> service, {
    String? providerId,
  }) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => VasPurchaseScreen(
          api: widget.api,
          token: widget.token,
          service: service,
          currency: widget.currency,
          securityPinSet: widget.securityPinSet,
          initialProviderId: providerId,
        ),
      ),
    );
    if (mounted) _load();
  }

  void _openHistory() => Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => VasOrderHistoryScreen(
        api: widget.api,
        token: widget.token,
        currency: widget.currency,
      ),
    ),
  );

  void _showOrder(Map<String, dynamic> order) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _OrderDetails(
      order: order,
      currency: widget.currency,
      api: widget.api,
      token: widget.token,
      onChanged: _load,
    ),
  );
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    Widget block(double height, double radius) => Container(
      margin: const EdgeInsets.only(bottom: 14),
      height: height,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .5)),
      ),
    );
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 4, 18, 32),
      children: [
        block(238, 30),
        Row(
          children: [
            Expanded(child: block(158, 24)),
            const SizedBox(width: 12),
            Expanded(child: block(158, 24)),
          ],
        ),
        Row(
          children: [
            Expanded(child: block(158, 24)),
            const SizedBox(width: 12),
            Expanded(child: block(158, 24)),
          ],
        ),
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const SizedBox(
                width: 14,
                height: 14,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
              const SizedBox(width: 8),
              Text(
                'Syncing your services…',
                style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Glow extends StatelessWidget {
  const _Glow({required this.size, this.opacity = .16});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: Colors.white.withValues(alpha: opacity),
    ),
  );
}

class _HeroPill extends StatelessWidget {
  const _HeroPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: scheme.onPrimary.withValues(alpha: .17),
        borderRadius: BorderRadius.circular(40),
        border: Border.all(color: scheme.onPrimary.withValues(alpha: .14)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: scheme.onPrimary),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: scheme.onPrimary,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _VasHero extends StatelessWidget {
  const _VasHero({
    required this.balanceText,
    required this.syncedLabel,
    required this.onHistory,
    required this.refreshing,
  });

  final String balanceText;
  final String syncedLabel;
  final VoidCallback onHistory;
  final bool refreshing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(0),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(AppColors.gradientStart),
            Color(AppColors.gradientCenter),
            Color(AppColors.gradientMiddel),
            Color(AppColors.gradientEnd),
          ],
        ),
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: scheme.primary.withValues(alpha: .28),
            blurRadius: 26,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(right: -30, top: -40, child: _Glow(size: 160)),
          const Positioned(
            right: 30,
            bottom: -46,
            child: _Glow(size: 120, opacity: .1),
          ),
          Padding(
            padding: const EdgeInsets.all(15),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: scheme.onPrimary.withValues(alpha: .18),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            Icons.account_balance_wallet_rounded,
                            color: scheme.onPrimary,
                            size: 18,
                          ),
                        ),
                        const SizedBox(width: 9),
                        Text(
                          'TOP-UP FUND',
                          style: TextStyle(
                            color: scheme.onPrimary.withValues(alpha: .78),
                            fontWeight: FontWeight.w800,
                            fontSize: 11,
                            letterSpacing: 1.1,
                          ),
                        ),
                        const Spacer(),
                        if (refreshing)
                          SizedBox(
                            width: 15,
                            height: 15,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: scheme.onPrimary,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(
                        balanceText,
                        style: TextStyle(
                          color: scheme.onPrimary,
                          fontSize: 36,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -1.2,
                          height: 1,
                        ),
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      'Ready for airtime, data and bills',
                      style: TextStyle(
                        color: scheme.onPrimary.withValues(alpha: .82),
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: onHistory,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: scheme.onPrimary,
                              backgroundColor: scheme.onPrimary.withValues(alpha: .12),
                              side: BorderSide(
                                color: scheme.onPrimary.withValues(alpha: .38),
                              ),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            icon: const Icon(Icons.receipt_long_rounded, size: 18),
                            label: const Text(
                              'My purchases',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: scheme.onPrimary.withValues(alpha: .16),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(Icons.bolt_rounded, color: scheme.onPrimary),
                        ),
                      ],
                    ),
                  ],
                ),
          ),
        ],
      ),
    );
  }
}

class _QuickRecharge extends StatelessWidget {
  const _QuickRecharge({required this.services, required this.onSelected});

  final List<Map<String, dynamic>> services;
  final ValueChanged<Map<String, dynamic>> onSelected;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeading(
          title: 'Quick recharge',
          subtitle: 'Your everyday services, ready to go',
          action: Icon(Icons.bolt_rounded, color: scheme.primary),
        ),
        SizedBox(
          height: 90,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: services.length,
            separatorBuilder: (_, __) => const SizedBox(width: 14),
            itemBuilder: (context, index) {
              final selection = services[index];
              final service = Map<String, dynamic>.from(
                selection['service'] as Map,
              );
              final provider = Map<String, dynamic>.from(
                selection['provider'] as Map,
              );
              return _QuickNetworkTile(
                provider: provider,
                serviceKey: '${service['key']}',
                onTap: () => onSelected(selection),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _QuickNetworkTile extends StatelessWidget {
  const _QuickNetworkTile({
    required this.provider,
    required this.serviceKey,
    required this.onTap,
  });

  final Map<String, dynamic> provider;
  final String serviceKey;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final brand = _brandFor(serviceKey);
    final label = '${provider['label'] ?? _label('${provider['id'] ?? ''}')}';
    return SizedBox(
      width: 74,
      child: Column(
        children: [
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(18),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: [
                    BoxShadow(
                      color: brand.accent.withValues(alpha: .18),
                      blurRadius: 14,
                      offset: const Offset(0, 7),
                    ),
                  ],
                ),
                child: _ProviderLogo(
                  provider: provider,
                  serviceKey: serviceKey,
                  size: 56,
                  radius: 18,
                ),
              ),
            ),
          ),
          const SizedBox(height: 7),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: scheme.onSurface,
            ),
          ),
        ],
      ),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.service, required this.onTap});

  final Map<String, dynamic> service;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final key = '${service['key'] ?? ''}';
    final brand = _brandFor(key);
    final providers = _rows(service['providers']);
    final title = '${service['label'] ?? _label(key)}';
    return Container(
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: brand.accent.withValues(alpha: .18)),
        boxShadow: [
          BoxShadow(
            color: brand.accent.withValues(alpha: .1),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Ink(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [brand.start.withValues(alpha: .14), scheme.surface],
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      _ServiceArt(
                        serviceKey: key,
                        imageUrl: _remoteImage(service),
                        size: 52,
                        radius: 15,
                      ),
                      const Spacer(),
                      Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: brand.accent.withValues(alpha: .13),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.north_east_rounded,
                          size: 15,
                          color: brand.accent,
                        ),
                      ),
                    ],
                  ),
                  const Spacer(),
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    _serviceTagline(key),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 9),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: brand.accent.withValues(alpha: .1),
                      borderRadius: BorderRadius.circular(30),
                    ),
                    child: Text(
                      providers.isEmpty
                          ? 'Available'
                          : '${providers.length} providers',
                      style: TextStyle(
                        color: brand.accent,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ServiceListCard extends StatelessWidget {
  const _ServiceListCard({required this.service, required this.onTap});

  final Map<String, dynamic> service;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final key = '${service['key'] ?? ''}';
    final brand = _brandFor(key);
    final providers = _rows(service['providers']);
    return Material(
      color: scheme.surface,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            gradient: LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [brand.start.withValues(alpha: .16), scheme.surface],
            ),
            border: Border.all(color: brand.accent.withValues(alpha: .2)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                _ServiceArt(
                  serviceKey: key,
                  imageUrl: _remoteImage(service),
                  size: 58,
                  radius: 17,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${service['label'] ?? _label(key)}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        _serviceTagline(key),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        providers.isEmpty
                            ? 'Available'
                            : '${providers.length} providers',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: brand.accent,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, color: brand.accent),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Purchase
// ---------------------------------------------------------------------------

class VasPurchaseScreen extends StatefulWidget {
  const VasPurchaseScreen({
    super.key,
    required this.api,
    required this.token,
    required this.service,
    required this.currency,
    required this.securityPinSet,
    this.initialProviderId,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> service;
  final String currency;
  final bool securityPinSet;
  final String? initialProviderId;

  @override
  State<VasPurchaseScreen> createState() => _VasPurchaseScreenState();
}

class _VasPurchaseScreenState extends State<VasPurchaseScreen> {
  final _formKey = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _amount = TextEditingController();
  final _pin = TextEditingController();

  String? _providerId;
  String? _planCode;
  String? _meterType;
  String? _tvAction;
  String? _verifiedName;
  String? _idempotencyKey;
  String? _error;
  String? _plansError;
  bool _busy = false;
  bool _plansLoading = false;
  List<Map<String, dynamic>> _plans = [];
  Map<String, dynamic> _wallet = {};

  String get _key => '${widget.service['key'] ?? ''}';
  String get _title => '${widget.service['label'] ?? _label(_key)}';
  _Brand get _brand => _brandFor(_key);
  List<Map<String, dynamic>> get _providers =>
      _rows(widget.service['providers']);
  Map<String, dynamic> get _provider =>
      _firstWhereOrNull(_providers, (p) => '${p['id']}' == _providerId) ??
      <String, dynamic>{};
  bool get _usesPlan => widget.service['pricing'] == 'plan';
  String get _recipientLabel =>
      '${_provider['recipient_label'] ?? widget.service['recipient_label'] ?? 'Recipient'}';
  bool get _needsVerification =>
      _provider['requires_verification'] == true ||
      widget.service['requires_verification'] == true;
  bool get _phoneRecipient => _recipientLabel.toLowerCase().contains('phone');
  Map<String, dynamic>? get _plan =>
      _firstWhereOrNull(_plans, (p) => '${p['code']}' == _planCode);

  @override
  void initState() {
    super.initState();
    final meterTypes = _rows(widget.service['meter_types']);
    _meterType = meterTypes.isNotEmpty
        ? '${meterTypes.first['id']}'
        : 'prepaid';
    final providers = _providers;
    final initial = widget.initialProviderId;
    if (initial != null &&
        providers.any((provider) => '${provider['id']}' == initial)) {
      _providerId = initial;
    }
    _providerId ??= providers.isNotEmpty ? '${providers.first['id']}' : null;
    _loadWallet();
    if (_usesPlan) _loadPlans();
  }

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _amount.dispose();
    _pin.dispose();
    super.dispose();
  }

  Future<void> _loadWallet({bool forceRefresh = false}) async {
    try {
      final wallets = await widget.api.wallets(
        widget.token,
        forceRefresh: forceRefresh,
      );
      final match = _firstWhereOrNull(
        wallets,
        (wallet) => '${wallet['type']}' == 'topup_fund',
      );
      if (mounted && match != null) setState(() => _wallet = match);
    } catch (_) {
      // The balance is informative only; checkout still validates server-side.
    }
  }

  Future<void> _loadPlans({bool forceRefresh = false}) async {
    if (!_usesPlan || _providerId == null) {
      if (mounted) {
        setState(() {
          _plans = const [];
          _plansError = null;
        });
      }
      return;
    }
    final providerId = _providerId!;
    setState(() {
      _plansLoading = true;
      _plansError = null;
      _plans = const [];
      _planCode = null;
    });
    try {
      final response = await widget.api.vasPlans(
        widget.token,
        service: _key,
        serviceId: providerId,
        forceRefresh: forceRefresh,
      );
      if (!mounted || providerId != _providerId) return;
      setState(() => _plans = _rows(_map(response['data'])['plans']));
    } catch (error) {
      if (!mounted || providerId != _providerId) return;
      setState(() => _plansError = error.toString());
    } finally {
      if (mounted && providerId == _providerId) {
        setState(() => _plansLoading = false);
      }
    }
  }

  void _selectProvider(String id) {
    if (id == _providerId) return;
    setState(() {
      _providerId = id;
      _planCode = null;
      _plans = const [];
      _verifiedName = null;
      _idempotencyKey = null;
      _error = null;
    });
    if (_usesPlan) _loadPlans();
  }

  String get _providerSectionTitle {
    switch (_key) {
      case 'airtime':
      case 'data':
        return 'Choose a network';
      case 'tv':
        return 'Choose a provider';
      case 'electricity':
        return 'Choose your DisCo';
      case 'education':
        return 'Choose an exam body';
      default:
        return 'Choose a provider';
    }
  }

  String get _helperText {
    switch (_key) {
      case 'airtime':
        return 'Instant mobile recharge, paid from your Top-up Fund.';
      case 'data':
        return 'Pick a network and a bundle that fits your data need.';
      case 'tv':
        return 'Renew or change your TV subscription in seconds.';
      case 'electricity':
        return 'Buy a prepaid token or settle a postpaid bill.';
      case 'education':
        return 'Buy exam pins and result checker vouchers.';
      case 'foreign-airtime':
        return 'Top up international numbers in a few taps.';
      default:
        return 'Purchase securely from your Top-up Fund.';
    }
  }

  double _planNgn(Map<String, dynamic>? plan) => plan == null
      ? 0
      : ((plan['amount_minor'] as num?)?.toDouble() ?? 0) / 100;

  double? get _estimateNgn {
    if (_usesPlan) {
      final plan = _plan;
      return plan == null ? null : _planNgn(plan);
    }
    final amount = double.tryParse(_amount.text.trim());
    if (amount == null || amount <= 0) return null;
    return amount;
  }

  String get _estimateLabel {
    final amount = _estimateNgn;
    if (amount == null) return _usesPlan ? 'Select a plan' : 'Enter amount';
    final converted = widget.api.convertMajor(amount, 'NGN', widget.currency);
    return converted == null
        ? formatMajorMoney(amount, 'NGN')
        : formatMajorMoney(converted, widget.currency);
  }

  String get _estimateCaption {
    if (_usesPlan) {
      final plan = _plan;
      return plan == null ? 'Choose a package above' : '${plan['name'] ?? ''}';
    }
    return 'Charged from your wallet';
  }

  String get _walletBalanceLabel {
    if (_wallet.isEmpty) return '—';
    final currency = '${_wallet['currency'] ?? widget.currency}';
    final precision =
        (_wallet['currency_minor_units'] as num?)?.toInt() ??
        currencyPrecision(currency);
    final amount =
        ((_wallet['balance_minor'] as num?)?.toDouble() ?? 0) /
        pow(10, precision);
    final converted = widget.api.convertMajor(
      amount,
      currency,
      widget.currency,
    );
    return converted == null
        ? formatMajorMoney(amount, currency)
        : formatMajorMoney(converted, widget.currency);
  }

  Widget _fieldLabel(String label) => Padding(
    padding: const EdgeInsets.only(bottom: 7),
    child: Text(
      label,
      style: Theme.of(context).textTheme.labelLarge
          ?.copyWith(fontWeight: FontWeight.w700),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = _brand;
    final providers = _providers;
    final actions = _rows(widget.service['subscription_actions']);
    final meterTypes = _rows(widget.service['meter_types']);

    return Scaffold(
      appBar: AppBar(
        title: Text(_title),
        actions: [
          IconButton(
            onPressed: _openHistory,
            icon: const Icon(Icons.receipt_long_rounded),
            tooltip: 'Order history',
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 6, 18, 24),
          children: [
            _PurchaseHeader(
              serviceKey: _key,
              title: _title,
              subtitle: _helperText,
              balance: _walletBalanceLabel,
              imageUrl: _remoteImage(widget.service),
            ),
            const SizedBox(height: 18),
            if (providers.isNotEmpty) ...[
              _SectionHeading(
                title: _providerSectionTitle,
                subtitle: providers.length < 10
                    ? 'Tap a logo to select'
                    : 'Choose from the list',
              ),
              _ProviderPicker(
                providers: providers,
                selectedId: _providerId,
                serviceKey: _key,
                accent: brand.accent,
                onSelected: _selectProvider,
              ),
              const SizedBox(height: 18),
            ],
            if (_key == 'tv' && actions.isNotEmpty) ...[
              _SectionHeading(
                title: 'Subscription action',
                subtitle: 'Renew or switch your bouquet',
              ),
              _ChoiceChips(
                options: actions,
                selected: _tvAction,
                accent: brand.accent,
                onSelected: (value) => setState(() {
                  _tvAction = value;
                  _idempotencyKey = null;
                  _error = null;
                }),
              ),
              const SizedBox(height: 18),
            ],
            if (_key == 'electricity' && meterTypes.isNotEmpty) ...[
              _SectionHeading(
                title: 'Meter type',
                subtitle: 'Prepaid meters receive a token',
              ),
              _ChoiceChips(
                options: meterTypes,
                selected: _meterType,
                accent: brand.accent,
                onSelected: (value) => setState(() {
                  _meterType = value;
                  _verifiedName = null;
                  _idempotencyKey = null;
                }),
              ),
              const SizedBox(height: 18),
            ],
            _SectionHeading(
              title: 'Recipient details',
              subtitle: _needsVerification
                  ? 'We confirm the name before you pay'
                  : null,
            ),
            _recipientCard(brand),
            const SizedBox(height: 18),
            if (_usesPlan)
              _PlanSection(
                plans: _plans,
                loading: _plansLoading,
                error: _plansError,
                selectedCode: _planCode,
                provider: _provider,
                serviceKey: _key,
                accent: brand.accent,
                api: widget.api,
                currency: widget.currency,
                onSelected: (code) => setState(() {
                  _planCode = code;
                  _idempotencyKey = null;
                  _error = null;
                }),
                onRetry: () => _loadPlans(forceRefresh: true),
              )
            else
              _AmountSection(
                controller: _amount,
                accent: brand.accent,
                onChanged: (_) => setState(() => _idempotencyKey = null),
              ),
            const SizedBox(height: 18),
            _WalletBanner(
              wallet: _wallet,
              preferredCurrency: widget.currency,
              api: widget.api,
            ),
            if (_error != null) ...[
              const SizedBox(height: 14),
              _InlineError(
                message: _error!,
                onDismiss: () => setState(() => _error = null),
              ),
            ],
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.lock_rounded, size: 13, color: scheme.outline),
                const SizedBox(width: 5),
                Flexible(
                  child: Text(
                    'Paid securely from your Top-up Fund wallet.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      bottomNavigationBar: _CheckoutBar(
        amountLabel: _estimateLabel,
        caption: _estimateCaption,
        busy: _busy,
        accent: brand.accent,
        onSubmit: _confirmPurchase,
      ),
    );
  }

  Widget _recipientCard(_Brand brand) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .55)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _fieldLabel(_recipientLabel),
          TextFormField(
            controller: _recipient,
            keyboardType: _phoneRecipient
                ? TextInputType.phone
                : TextInputType.text,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              prefixIcon: Icon(
                _phoneRecipient
                    ? Icons.phone_android_rounded
                    : Icons.confirmation_number_rounded,
              ),
              hintText: _phoneRecipient
                  ? '08012345678'
                  : 'Enter ${_recipientLabel.toLowerCase()}',
            ),
            validator: (value) => value == null || value.trim().isEmpty
                ? 'Enter $_recipientLabel'
                : null,
            onChanged: (_) {
              _idempotencyKey = null;
              if (_verifiedName != null) {
                setState(() => _verifiedName = null);
              }
            },
          ),
          if (!_phoneRecipient) ...[
            const SizedBox(height: 14),
            _fieldLabel('Contact phone number'),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.phone_rounded),
                hintText: '08012345678',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Enter a contact phone number'
                  : null,
              onChanged: (_) => _idempotencyKey = null,
            ),
          ],
          if (_needsVerification) ...[
            const SizedBox(height: 14),
            if (_verifiedName != null)
              _VerifiedBadge(name: _verifiedName!)
            else
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _busy ? null : _verify,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: brand.accent,
                    side: BorderSide(color: brand.accent.withValues(alpha: .5)),
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: const Icon(Icons.verified_user_rounded, size: 18),
                  label: const Text(
                    'Verify recipient',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  Future<void> _verify() async {
    if (_recipient.text.trim().isEmpty || _providerId == null) {
      setState(() => _error = 'Enter the recipient details first.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.api.verifyVasRecipient(widget.token, {
        'service': _key,
        'service_id': _providerId,
        'customer_id': _recipient.text.trim(),
        if (_key == 'electricity') 'type': _meterType ?? 'prepaid',
        if (_key == 'education') 'type': _planCode,
      });
      final data = _map(response['data']);
      if (!mounted) return;
      final name = data['customer_name']?.toString();
      setState(() => _verifiedName = name);
      _message(
        name == null
            ? 'Recipient could not be verified. Check the details and try again.'
            : 'Recipient verified: $name',
      );
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirmPurchase() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_needsVerification && _verifiedName == null) {
      setState(() => _error = 'Verify the recipient before continuing.');
      return;
    }
    if (_usesPlan && _plan == null) {
      setState(() => _error = 'Choose a plan to continue.');
      return;
    }
    if (_key == 'tv' &&
        _rows(widget.service['subscription_actions']).isNotEmpty &&
        _tvAction == null) {
      setState(() => _error = 'Choose renew or change plan.');
      return;
    }

    var amountMinor = 0;
    if (!_usesPlan) {
      final amount = double.tryParse(_amount.text.trim()) ?? 0;
      amountMinor = (amount * 100).round();
      if (amountMinor <= 0) {
        setState(() => _error = 'Enter an amount greater than zero.');
        return;
      }
    }

    final plan = _plan;
    final estimateNgn = _usesPlan ? _planNgn(plan) : amountMinor / 100;
    final walletCurrency = '${_wallet['currency'] ?? widget.currency}'
        .toUpperCase();
    final walletPrecision =
        (_wallet['currency_minor_units'] as num?)?.toInt() ??
        currencyPrecision(walletCurrency);
    final walletBalance =
        ((_wallet['balance_minor'] as num?)?.toDouble() ?? 0) /
        pow(10, walletPrecision);
    final estimateWallet = widget.api.convertMajor(
      estimateNgn,
      'NGN',
      walletCurrency,
    );
    final notEnough = estimateWallet != null && walletBalance < estimateWallet;
    final displayAmount =
        widget.api.convertMajor(estimateNgn, 'NGN', widget.currency) ??
        estimateNgn;
    final displayBalance =
        widget.api.convertMajor(
          walletBalance,
          walletCurrency,
          widget.currency,
        ) ??
        walletBalance;
    final displayDebit = estimateWallet == null
        ? null
        : widget.api.convertMajor(
                estimateWallet,
                walletCurrency,
                widget.currency,
              ) ??
              estimateWallet;

    setState(() => _error = null);
    final accepted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (context) => _PurchaseConfirmSheet(
        title: _title,
        provider: '${_provider['label'] ?? ''}',
        recipient: _recipient.text.trim(),
        plan: '${plan?['name'] ?? ''}',
        amount: displayAmount,
        currency: widget.currency,
        walletBalance: displayBalance,
        estimatedDebit: displayDebit,
        notEnough: notEnough,
        accent: _brand.accent,
        serviceKey: _key,
        pinRequired: widget.securityPinSet,
        pinController: _pin,
      ),
    );
    if (accepted != true || !mounted) return;

    setState(() => _busy = true);
    try {
      final response = await widget.api.purchaseVas(widget.token, {
        'service': _key,
        'service_id': _providerId,
        if (_usesPlan)
          'variation_code': _planCode
        else
          'amount_minor': amountMinor,
        if (_phoneRecipient)
          'phone': _recipient.text.trim()
        else
          'customer_id': _recipient.text.trim(),
        if (!_phoneRecipient) 'phone': _phone.text.trim(),
        if (_key == 'electricity') 'meter_type': _meterType ?? 'prepaid',
        if (_key == 'tv') 'subscription_action': _tvAction,
        'idempotency_key': _idempotencyKey ??=
            '${DateTime.now().microsecondsSinceEpoch}-${Random.secure().nextInt(1 << 32)}',
        if (_pin.text.isNotEmpty) 'security_pin': _pin.text,
      });
      _idempotencyKey = null;
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => VasReceiptScreen(
            api: widget.api,
            token: widget.token,
            currency: widget.currency,
            order: _map(response['data']),
          ),
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _openHistory() => Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => VasOrderHistoryScreen(
        api: widget.api,
        token: widget.token,
        currency: widget.currency,
      ),
    ),
  );

  void _message(String text) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
}

class _PurchaseHeader extends StatelessWidget {
  const _PurchaseHeader({
    required this.serviceKey,
    required this.title,
    required this.subtitle,
    required this.balance,
    this.imageUrl,
  });

  final String serviceKey;
  final String title;
  final String subtitle;
  final String balance;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final brand = _brandFor(serviceKey);
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [scheme.primary, scheme.secondary],
        ),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: scheme.primary.withValues(alpha: .28),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -20,
            top: -30,
            child: Icon(
              brand.icon,
              size: 138,
              color: scheme.onPrimary.withValues(alpha: .13),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  _ServiceArt(
                    serviceKey: serviceKey,
                    imageUrl: imageUrl,
                    size: 58,
                    radius: 17,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: TextStyle(
                            color: scheme.onPrimary,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.3,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: scheme.onPrimary.withValues(alpha: .85),
                            fontSize: 12,
                            height: 1.3,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  color: scheme.onPrimary.withValues(alpha: .17),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: scheme.onPrimary.withValues(alpha: .16),
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.account_balance_wallet_rounded,
                      color: scheme.onPrimary,
                      size: 17,
                    ),
                    const SizedBox(width: 7),
                    Text(
                      'Wallet balance',
                      style: TextStyle(
                        color: scheme.onPrimary.withValues(alpha: .82),
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      balance,
                      style: TextStyle(
                        color: scheme.onPrimary,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SelectionTile extends StatelessWidget {
  const _SelectionTile({
    required this.selected,
    required this.accent,
    required this.onTap,
    required this.child,
    this.width,
    this.height = 96,
  });

  final bool selected;
  final Color accent;
  final VoidCallback onTap;
  final Widget child;
  final double? width;
  final double height;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: selected ? accent.withValues(alpha: .09) : scheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: selected
              ? accent
              : scheme.outlineVariant.withValues(alpha: .6),
          width: selected ? 2 : 1,
        ),
        boxShadow: selected
            ? [
                BoxShadow(
                  color: accent.withValues(alpha: .16),
                  blurRadius: 14,
                  offset: const Offset(0, 6),
                ),
              ]
            : null,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Stack(
            children: [
              Positioned.fill(
                child: Padding(padding: const EdgeInsets.all(9), child: child),
              ),
              if (selected)
                Positioned(
                  top: 5,
                  right: 5,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      color: accent,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.check_rounded,
                      size: 10,
                      color: Colors.white,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProviderPicker extends StatelessWidget {
  const _ProviderPicker({
    required this.providers,
    required this.selectedId,
    required this.serviceKey,
    required this.accent,
    required this.onSelected,
  });

  final List<Map<String, dynamic>> providers;
  final String? selectedId;
  final String serviceKey;
  final Color accent;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    if (providers.length >= 10) {
      // Ten or more options stay compact in a dropdown.
      return DropdownButtonFormField<String>(
        value: selectedId,
        isExpanded: true,
        decoration: const InputDecoration(
          prefixIcon: Icon(Icons.cell_tower_rounded),
        ),
        items: providers
            .map(
              (provider) => DropdownMenuItem(
                value: '${provider['id']}',
                child: Row(
                  children: [
                    _ProviderLogo(
                      provider: provider,
                      serviceKey: serviceKey,
                      size: 26,
                      radius: 8,
                    ),
                    const SizedBox(width: 9),
                    Flexible(
                      child: Text(
                        '${provider['label'] ?? _label('${provider['id']}')}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            )
            .toList(),
        onChanged: (value) {
          if (value != null) onSelected(value);
        },
        validator: (value) => value == null ? 'Select a provider' : null,
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = 10.0;
        final columns = constraints.maxWidth >= 640
            ? 5
            : (constraints.maxWidth >= 430 ? 4 : 3);
        final width =
            (constraints.maxWidth - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final provider in providers)
              _SelectionTile(
                selected: '${provider['id']}' == selectedId,
                accent: accent,
                width: width,
                height: 94,
                onTap: () => onSelected('${provider['id']}'),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _ProviderLogo(
                      provider: provider,
                      serviceKey: serviceKey,
                      size: 42,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${provider['label'] ?? _label('${provider['id'] ?? ''}')}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}

class _ChoiceChips extends StatelessWidget {
  const _ChoiceChips({
    required this.options,
    required this.selected,
    required this.accent,
    required this.onSelected,
  });

  final List<Map<String, dynamic>> options;
  final String? selected;
  final Color accent;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [for (final option in options) _chip(context, option)],
    );
  }

  Widget _chip(BuildContext context, Map<String, dynamic> option) {
    final scheme = Theme.of(context).colorScheme;
    final id = '${option['id']}';
    final isSelected = id == selected;
    return InkWell(
      onTap: () => onSelected(id),
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
        decoration: BoxDecoration(
          color: isSelected ? accent.withValues(alpha: .12) : scheme.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected
                ? accent
                : scheme.outlineVariant.withValues(alpha: .7),
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Text(
          '${option['label'] ?? option['id']}',
          style: TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 13,
            color: isSelected ? accent : scheme.onSurface,
          ),
        ),
      ),
    );
  }
}

class _PlanSection extends StatelessWidget {
  const _PlanSection({
    required this.plans,
    required this.loading,
    required this.error,
    required this.selectedCode,
    required this.provider,
    required this.serviceKey,
    required this.accent,
    required this.api,
    required this.currency,
    required this.onSelected,
    required this.onRetry,
  });

  final List<Map<String, dynamic>> plans;
  final bool loading;
  final String? error;
  final String? selectedCode;
  final Map<String, dynamic> provider;
  final String serviceKey;
  final Color accent;
  final AuthApi api;
  final String currency;
  final ValueChanged<String> onSelected;
  final VoidCallback onRetry;

  String _price(Map<String, dynamic> plan) {
    final code = '${plan['currency'] ?? 'NGN'}';
    final precision =
        (plan['currency_minor_units'] as num?)?.toInt() ??
        currencyPrecision(code);
    return moneyConverted(
      api,
      plan['amount_minor'],
      code,
      precision,
      currency,
      isMinor: true,
    );
  }

  String? _meta(Map<String, dynamic> plan) {
    for (final key in const [
      'validity',
      'duration',
      'description',
      'plan_type',
      'note',
    ]) {
      final value = plan[key]?.toString().trim();
      if (value != null && value.isNotEmpty) return value;
    }
    return null;
  }

  Future<void> _browse(BuildContext context) async {
    final code = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (_) => _PlanSheet(
        plans: plans,
        selectedCode: selectedCode,
        accent: accent,
        provider: provider,
        serviceKey: serviceKey,
        priceOf: _price,
        metaOf: _meta,
      ),
    );
    if (code != null) onSelected(code);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final heading = _SectionHeading(
      title: 'Choose a plan',
      subtitle: plans.isEmpty
          ? null
          : '${plans.length} options · prices held for an hour',
      action: loading && plans.isNotEmpty
          ? const SizedBox(
              width: 16,
              height: 16,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : null,
    );

    if (loading && plans.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [heading, const _PlanSkeletons()],
      );
    }
    if (error != null && plans.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          heading,
          _NoticeCard(
            icon: Icons.cloud_off_rounded,
            title: 'Could not load plans',
            text: error!,
            color: scheme.error,
            action: TextButton(
              onPressed: onRetry,
              child: const Text('Try again'),
            ),
          ),
        ],
      );
    }
    if (plans.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          heading,
          const _NoticeCard(
            icon: Icons.inventory_2_outlined,
            title: 'No plans right now',
            text: 'This provider has no plans available. Try another provider.',
          ),
        ],
      );
    }

    if (plans.length < 10) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          heading,
          LayoutBuilder(
            builder: (context, constraints) {
              const spacing = 10.0;
              final columns = constraints.maxWidth >= 560 ? 3 : 2;
              final width =
                  (constraints.maxWidth - spacing * (columns - 1)) / columns;
              return Wrap(
                spacing: spacing,
                runSpacing: spacing,
                children: [
                  for (final plan in plans)
                    _PlanCard(
                      plan: plan,
                      selected: '${plan['code']}' == selectedCode,
                      accent: accent,
                      provider: provider,
                      serviceKey: serviceKey,
                      price: _price(plan),
                      meta: _meta(plan),
                      width: width,
                      onTap: () => onSelected('${plan['code']}'),
                    ),
                ],
              );
            },
          ),
        ],
      );
    }

    final selectedPlan = _firstWhereOrNull(
      plans,
      (plan) => '${plan['code']}' == selectedCode,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        heading,
        if (selectedPlan != null) ...[
          _PlanCard(
            plan: selectedPlan,
            selected: true,
            accent: accent,
            provider: provider,
            serviceKey: serviceKey,
            price: _price(selectedPlan),
            meta: _meta(selectedPlan),
            width: double.infinity,
            onTap: () => _browse(context),
          ),
          const SizedBox(height: 10),
        ],
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: () => _browse(context),
            style: OutlinedButton.styleFrom(
              foregroundColor: accent,
              side: BorderSide(color: accent.withValues(alpha: .5)),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            icon: const Icon(Icons.search_rounded, size: 18),
            label: Text(
              selectedPlan == null
                  ? 'Browse ${plans.length} plans'
                  : 'Change plan',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ],
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.selected,
    required this.accent,
    required this.provider,
    required this.serviceKey,
    required this.price,
    required this.meta,
    required this.onTap,
    this.width,
  });

  final Map<String, dynamic> plan;
  final bool selected;
  final Color accent;
  final Map<String, dynamic> provider;
  final String serviceKey;
  final String price;
  final String? meta;
  final VoidCallback onTap;
  final double? width;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return _SelectionTile(
      selected: selected,
      accent: accent,
      width: width,
      height: 112,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _ProviderLogo(
                provider: provider,
                serviceKey: serviceKey,
                size: 24,
                radius: 8,
              ),
              const SizedBox(width: 6),
              if (meta != null)
                Expanded(
                  child: Text(
                    meta!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.end,
                    style: TextStyle(
                      fontSize: 9.5,
                      fontWeight: FontWeight.w700,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                )
              else
                const Spacer(),
            ],
          ),
          const Spacer(),
          Text(
            '${plan['name'] ?? plan['code'] ?? 'Plan'}',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              height: 1.15,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            price,
            style: TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w900,
              color: accent,
            ),
          ),
        ],
      ),
    );
  }
}

class _PlanSkeletons extends StatelessWidget {
  const _PlanSkeletons();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    Widget box() => Container(
      height: 112,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .5)),
      ),
    );
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 560 ? 3 : 2;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (var index = 0; index < columns; index++)
              SizedBox(
                width: (constraints.maxWidth - 10 * (columns - 1)) / columns,
                child: box(),
              ),
          ],
        );
      },
    );
  }
}

class _PlanSheet extends StatefulWidget {
  const _PlanSheet({
    required this.plans,
    required this.selectedCode,
    required this.accent,
    required this.provider,
    required this.serviceKey,
    required this.priceOf,
    required this.metaOf,
  });

  final List<Map<String, dynamic>> plans;
  final String? selectedCode;
  final Color accent;
  final Map<String, dynamic> provider;
  final String serviceKey;
  final String Function(Map<String, dynamic>) priceOf;
  final String? Function(Map<String, dynamic>) metaOf;

  @override
  State<_PlanSheet> createState() => _PlanSheetState();
}

class _PlanSheetState extends State<_PlanSheet> {
  final _search = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final filtered = _query.isEmpty
        ? widget.plans
        : widget.plans.where((plan) {
            final haystack =
                '${plan['name'] ?? ''} ${plan['code'] ?? ''} ${widget.metaOf(plan) ?? ''}'
                    .toLowerCase();
            return haystack.contains(_query);
          }).toList();

    return SizedBox(
      height: MediaQuery.sizeOf(context).height * .82,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 10),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Choose a plan',
                        style: Theme.of(context).textTheme.titleLarge
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        '${filtered.length} of ${widget.plans.length} plans',
                        style: Theme.of(context).textTheme.bodySmall
                            ?.copyWith(color: scheme.onSurfaceVariant),
                      ),
                    ],
                  ),
                ),
                _ProviderLogo(
                  provider: widget.provider,
                  serviceKey: widget.serviceKey,
                  size: 38,
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: TextField(
              controller: _search,
              decoration: InputDecoration(
                hintText: 'Search plans',
                prefixIcon: const Icon(Icons.search_rounded),
                isDense: true,
                suffixIcon: _query.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _search.clear();
                          setState(() => _query = '');
                        },
                        icon: const Icon(Icons.close_rounded),
                      ),
              ),
              onChanged: (value) =>
                  setState(() => _query = value.trim().toLowerCase()),
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: filtered.isEmpty
                ? Center(
                    child: Text(
                      _query.isEmpty
                          ? 'No plans available.'
                          : 'No plans match "$_query".',
                      style: TextStyle(color: scheme.onSurfaceVariant),
                    ),
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 2, 16, 24),
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final plan = filtered[index];
                      final selected = '${plan['code']}' == widget.selectedCode;
                      final meta = widget.metaOf(plan);
                      return Material(
                        color: selected
                            ? widget.accent.withValues(alpha: .09)
                            : scheme.surface,
                        borderRadius: BorderRadius.circular(16),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(16),
                          onTap: () =>
                              Navigator.of(context).pop('${plan['code']}'),
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: selected
                                    ? widget.accent
                                    : scheme.outlineVariant.withValues(
                                        alpha: .6,
                                      ),
                                width: selected ? 2 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  selected
                                      ? Icons.radio_button_checked_rounded
                                      : Icons.radio_button_unchecked_rounded,
                                  size: 20,
                                  color: selected
                                      ? widget.accent
                                      : scheme.outline,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        '${plan['name'] ?? plan['code']}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 13,
                                        ),
                                      ),
                                      if (meta != null) ...[
                                        const SizedBox(height: 2),
                                        Text(
                                          meta,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: TextStyle(
                                            fontSize: 11,
                                            color: scheme.onSurfaceVariant,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Text(
                                  widget.priceOf(plan),
                                  style: TextStyle(
                                    fontWeight: FontWeight.w900,
                                    color: widget.accent,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _AmountSection extends StatelessWidget {
  const _AmountSection({
    required this.controller,
    required this.accent,
    required this.onChanged,
  });

  final TextEditingController controller;
  final Color accent;
  final ValueChanged<String> onChanged;

  static const _quickAmounts = <int>[500, 1000, 2000, 5000, 10000];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeading(
          title: 'Amount',
          subtitle: 'Enter any amount in Naira',
        ),
        TextFormField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,2}')),
          ],
          decoration: const InputDecoration(
            prefixIcon: Icon(Icons.payments_rounded),
            prefixText: '₦ ',
            hintText: 'Enter amount',
          ),
          validator: (value) {
            final amount = double.tryParse(value ?? '');
            return amount == null || amount <= 0
                ? 'Enter an amount greater than zero'
                : null;
          },
          onChanged: onChanged,
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final amount in _quickAmounts)
              ActionChip(
                label: Text(formatMajorMoney(amount.toDouble(), 'NGN')),
                labelStyle: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: accent,
                ),
                backgroundColor: accent.withValues(alpha: .07),
                side: BorderSide(color: accent.withValues(alpha: .28)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                onPressed: () {
                  final text = '$amount';
                  controller.text = text;
                  controller.selection = TextSelection.collapsed(
                    offset: text.length,
                  );
                  onChanged(text);
                },
              ),
          ],
        ),
      ],
    );
  }
}

class _WalletBanner extends StatelessWidget {
  const _WalletBanner({
    required this.wallet,
    required this.preferredCurrency,
    required this.api,
  });

  final Map<String, dynamic> wallet;
  final String preferredCurrency;
  final AuthApi api;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final currency = '${wallet['currency'] ?? preferredCurrency}';
    final precision =
        (wallet['currency_minor_units'] as num?)?.toInt() ??
        currencyPrecision(currency);
    final balance =
        ((wallet['balance_minor'] as num?)?.toDouble() ?? 0) /
        pow(10, precision);
    final converted = api.convertMajor(balance, currency, preferredCurrency);
    final displayCurrency = converted == null ? currency : preferredCurrency;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            scheme.primary.withValues(alpha: .12),
            scheme.primary.withValues(alpha: .04),
          ],
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.primary.withValues(alpha: .16)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: scheme.primary.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              Icons.account_balance_wallet_rounded,
              color: scheme.primary,
              size: 20,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Top-up Fund balance',
                  style: Theme.of(context).textTheme.labelMedium
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
                Text(
                  formatMajorMoney(converted ?? balance, displayCurrency),
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
              ],
            ),
          ),
          Icon(
            Icons.lock_outline_rounded,
            size: 18,
            color: scheme.onSurfaceVariant,
          ),
        ],
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message, this.onDismiss});

  final String message;
  final VoidCallback? onDismiss;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.errorContainer.withValues(alpha: .6),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, color: scheme.error, size: 20),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: scheme.onErrorContainer,
                fontSize: 12.5,
                height: 1.3,
              ),
            ),
          ),
          if (onDismiss != null)
            IconButton(
              onPressed: onDismiss,
              visualDensity: VisualDensity.compact,
              icon: Icon(
                Icons.close_rounded,
                size: 18,
                color: scheme.onErrorContainer,
              ),
            ),
        ],
      ),
    );
  }
}

class _CheckoutBar extends StatelessWidget {
  const _CheckoutBar({
    required this.amountLabel,
    required this.caption,
    required this.busy,
    required this.accent,
    required this.onSubmit,
  });

  final String amountLabel;
  final String caption;
  final bool busy;
  final Color accent;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        color: scheme.surface,
        border: Border(
          top: BorderSide(color: scheme.outlineVariant.withValues(alpha: .6)),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .07),
            blurRadius: 24,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Estimated total',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      amountLabel,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ],
                ),
                const SizedBox(width: 14),
                Flexible(
                  child: Text(
                    caption,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.end,
                    style: TextStyle(
                      fontSize: 11,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: FilledButton.icon(
                onPressed: busy ? null : onSubmit,
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  textStyle: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
                icon: busy
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.arrow_forward_rounded),
                label: Text(busy ? 'Please wait…' : 'Review purchase'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PurchaseConfirmSheet extends StatelessWidget {
  const _PurchaseConfirmSheet({
    required this.title,
    required this.provider,
    required this.recipient,
    required this.plan,
    required this.amount,
    required this.currency,
    required this.walletBalance,
    required this.estimatedDebit,
    required this.notEnough,
    required this.accent,
    required this.serviceKey,
    required this.pinRequired,
    required this.pinController,
  });

  final String title;
  final String provider;
  final String recipient;
  final String plan;
  final double amount;
  final String currency;
  final double walletBalance;
  final double? estimatedDebit;
  final bool notEnough;
  final Color accent;
  final String serviceKey;
  final bool pinRequired;
  final TextEditingController pinController;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return SafeArea(
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          20,
          4,
          20,
          20 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _ServiceArt(serviceKey: serviceKey, size: 44, radius: 13),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Confirm purchase',
                        style: Theme.of(context).textTheme.titleLarge
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        'One last check before we pay',
                        style: Theme.of(context).textTheme.bodySmall
                            ?.copyWith(color: scheme.onSurfaceVariant),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _SummaryRow(label: title, value: provider),
            _SummaryRow(label: 'Recipient', value: recipient),
            if (plan.isNotEmpty) _SummaryRow(label: 'Plan', value: plan),
            _SummaryRow(
              label: 'Top-up balance',
              value: formatMajorMoney(walletBalance, currency),
            ),
            if (estimatedDebit != null)
              _SummaryRow(
                label: 'Wallet debit',
                value: formatMajorMoney(estimatedDebit!, currency),
              ),
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: accent.withValues(alpha: .09),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: accent.withValues(alpha: .2)),
              ),
              child: Row(
                children: [
                  Text(
                    'Total to pay',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    formatMajorMoney(amount, currency),
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: accent,
                    ),
                  ),
                ],
              ),
            ),
            if (notEnough)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Row(
                  children: [
                    Icon(
                      Icons.warning_amber_rounded,
                      size: 18,
                      color: scheme.error,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        'Your balance may be too low. Top up first and try again.',
                        style: TextStyle(
                          color: scheme.error,
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (pinRequired) ...[
              const SizedBox(height: 14),
              TextField(
                controller: pinController,
                keyboardType: TextInputType.number,
                obscureText: true,
                maxLength: 4,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: const InputDecoration(
                  labelText: 'Security PIN',
                  prefixIcon: Icon(Icons.lock_rounded),
                ),
              ),
            ],
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: FilledButton.icon(
                onPressed: () {
                  if (pinRequired && pinController.text.length != 4) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Enter your 4-digit security PIN.'),
                      ),
                    );
                    return;
                  }
                  Navigator.pop(context, true);
                },
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                icon: const Icon(Icons.lock_rounded, size: 18),
                label: const Text(
                  'Confirm & pay',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _VerifiedBadge extends StatelessWidget {
  const _VerifiedBadge({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: scheme.tertiaryContainer,
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        children: [
          Icon(Icons.verified_rounded, color: scheme.tertiary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Verified recipient: $name',
              style: TextStyle(
                color: scheme.onTertiaryContainer,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Receipt
// ---------------------------------------------------------------------------

class VasReceiptScreen extends StatefulWidget {
  const VasReceiptScreen({
    super.key,
    required this.api,
    required this.token,
    required this.currency,
    required this.order,
  });

  final AuthApi api;
  final String token;
  final String currency;
  final Map<String, dynamic> order;

  @override
  State<VasReceiptScreen> createState() => _VasReceiptScreenState();
}

class _VasReceiptScreenState extends State<VasReceiptScreen> {
  late Map<String, dynamic> _order;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
  }

  Future<void> _check() async {
    setState(() => _checking = true);
    try {
      final response = await widget.api.checkVasOrder(
        widget.token,
        _order['id'],
      );
      if (!mounted) return;
      setState(() => _order = _map(response['data']));
    } catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  void _message(String text) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final status = '${_order['status'] ?? 'pending'}';
    final success =
        status == 'delivered' ||
        status == 'successful' ||
        status == 'completed';
    final failed = status == 'failed' || status == 'reversed';
    final accent = success
        ? const Color(0xFF0F9D58)
        : failed
        ? scheme.error
        : const Color(0xFFE0A106);
    final amount = _minorMoney(
      widget.api,
      _order['charged_amount_minor'],
      '${_order['currency'] ?? widget.currency}',
      _order['currency_minor_units'],
      widget.currency,
    );
    final serviceKey = '${_order['service'] ?? ''}';
    final providerLabel =
        '${_order['provider_name'] ?? _label('${_order['service_id'] ?? ''}')}';
    final reference = '${_order['reference'] ?? ''}';
    final token = '${_order['token'] ?? ''}';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Purchase receipt'),
        automaticallyImplyLeading: false,
        actions: [
          IconButton(
            onPressed: () => Navigator.of(context).pop(),
            icon: const Icon(Icons.close_rounded),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 6, 18, 28),
        children: [
          _ReceiptHero(
            status: status,
            success: success,
            failed: failed,
            accent: accent,
            amount: amount,
          ),
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: scheme.surface,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(
                color: scheme.outlineVariant.withValues(alpha: .55),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    _ProviderLogo(
                      provider: {
                        'id': '${_order['service_id'] ?? ''}',
                        'label': providerLabel,
                      },
                      serviceKey: serviceKey,
                      size: 42,
                    ),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_order['service_name'] ?? _label(serviceKey)}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 14,
                            ),
                          ),
                          Text(
                            providerLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11.5,
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    ),
                    _StatusPill(status: status),
                  ],
                ),
                const SizedBox(height: 14),
                _DashedDivider(color: scheme.outlineVariant),
                const SizedBox(height: 8),
                _SummaryRow(
                  label: 'Recipient',
                  value: '${_order['customer_id'] ?? _order['phone'] ?? '—'}',
                ),
                _SummaryRow(label: 'Amount charged', value: amount),
                _SummaryRow(
                  label: 'Reference',
                  value: reference.isEmpty ? '—' : reference,
                  trailing: reference.isEmpty
                      ? null
                      : IconButton(
                          onPressed: () {
                            Clipboard.setData(ClipboardData(text: reference));
                            _message('Reference copied.');
                          },
                          visualDensity: VisualDensity.compact,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(
                            minWidth: 28,
                            minHeight: 28,
                          ),
                          icon: const Icon(Icons.copy_rounded, size: 15),
                          tooltip: 'Copy reference',
                        ),
                ),
                _SummaryRow(label: 'Status', value: _label(status)),
              ],
            ),
          ),
          if (success && token.isNotEmpty) ...[
            const SizedBox(height: 14),
            _TokenCard(token: token),
          ],
          const SizedBox(height: 20),
          if (status == 'pending')
            SizedBox(
              height: 52,
              child: FilledButton.icon(
                onPressed: _checking ? null : _check,
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                icon: _checking
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.sync_rounded),
                label: Text(_checking ? 'Checking…' : 'Check status'),
              ),
            ),
          if (status == 'pending') const SizedBox(height: 10),
          SizedBox(
            height: 52,
            child: FilledButton.icon(
              onPressed: () => Navigator.of(context).pop(),
              style: FilledButton.styleFrom(
                backgroundColor: success
                    ? const Color(0xFF0F9D58)
                    : scheme.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                textStyle: const TextStyle(fontWeight: FontWeight.w800),
              ),
              icon: const Icon(Icons.check_rounded),
              label: const Text('Back to services'),
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 50,
            child: OutlinedButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => VasOrderHistoryScreen(
                    api: widget.api,
                    token: widget.token,
                    currency: widget.currency,
                  ),
                ),
              ),
              style: OutlinedButton.styleFrom(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                textStyle: const TextStyle(fontWeight: FontWeight.w700),
              ),
              icon: const Icon(Icons.history_rounded, size: 19),
              label: const Text('View order history'),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReceiptHero extends StatelessWidget {
  const _ReceiptHero({
    required this.status,
    required this.success,
    required this.failed,
    required this.accent,
    required this.amount,
  });

  final String status;
  final bool success;
  final bool failed;
  final Color accent;
  final String amount;

  @override
  Widget build(BuildContext context) {
    final title = success
        ? 'Purchase complete!'
        : failed
        ? 'Purchase not completed'
        : 'Purchase processing';
    final message = success
        ? 'Your service is ready to use.'
        : failed
        ? 'Any reserved wallet funds have been returned.'
        : 'We are waiting for the provider to confirm this transaction.';
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 24, 18, 24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [accent, Color.lerp(accent, const Color(0xFF1E293B), .35)!],
        ),
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: accent.withValues(alpha: .3),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Stack(
        children: [
          const Positioned(right: -34, top: -40, child: _Glow(size: 150)),
          Column(
            children: [
              Container(
                width: 66,
                height: 66,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .2),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: Colors.white.withValues(alpha: .35),
                    width: 2,
                  ),
                ),
                child: Icon(
                  success
                      ? Icons.check_rounded
                      : failed
                      ? Icons.close_rounded
                      : Icons.hourglass_top_rounded,
                  color: Colors.white,
                  size: 34,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 21,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                message,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: .88),
                  fontSize: 12.5,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                amount,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 30,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -1,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DashedDivider extends StatelessWidget {
  const _DashedDivider({this.color});

  final Color? color;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return LayoutBuilder(
      builder: (context, constraints) {
        const dash = 5.0;
        const gap = 5.0;
        final count = (constraints.maxWidth / (dash + gap)).floor();
        return Row(
          children: [
            for (var index = 0; index < count; index++)
              Container(
                width: dash,
                height: 1.2,
                margin: EdgeInsets.only(right: index == count - 1 ? 0 : gap),
                color: color ?? scheme.outlineVariant,
              ),
          ],
        );
      },
    );
  }
}

class _TokenCard extends StatelessWidget {
  const _TokenCard({required this.token});

  final String token;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            scheme.tertiaryContainer,
            scheme.tertiaryContainer.withValues(alpha: .55),
          ],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: scheme.tertiary.withValues(alpha: .25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.bolt_rounded,
                size: 18,
                color: scheme.onTertiaryContainer,
              ),
              const SizedBox(width: 6),
              Text(
                'Electricity token',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: scheme.onTertiaryContainer,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: SelectableText(
                  token,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.2,
                    color: scheme.onTertiaryContainer,
                  ),
                ),
              ),
              IconButton(
                onPressed: () {
                  Clipboard.setData(ClipboardData(text: token));
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Token copied.')),
                  );
                },
                icon: Icon(
                  Icons.copy_rounded,
                  color: scheme.onTertiaryContainer,
                ),
                tooltip: 'Copy token',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Order history
// ---------------------------------------------------------------------------

class VasOrderHistoryScreen extends StatefulWidget {
  const VasOrderHistoryScreen({
    super.key,
    required this.api,
    required this.token,
    required this.currency,
  });

  final AuthApi api;
  final String token;
  final String currency;

  @override
  State<VasOrderHistoryScreen> createState() => _VasOrderHistoryScreenState();
}

class _VasOrderHistoryScreenState extends State<VasOrderHistoryScreen> {
  final _search = TextEditingController();
  bool _loading = true;
  bool _refreshing = false;
  String? _error;
  List<Map<String, dynamic>> _orders = [];
  String _filter = 'all';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() {
      _error = null;
      if (_orders.isEmpty) {
        _loading = true;
      } else {
        _refreshing = true;
      }
    });
    try {
      final response = await widget.api.vasOrders(
        widget.token,
        perPage: 30,
        forceRefresh: forceRefresh,
      );
      if (!mounted) return;
      setState(() => _orders = _rows(response['data']));
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _refreshing = false;
        });
      }
    }
  }

  List<Map<String, dynamic>> get _shown {
    final query = _search.text.trim().toLowerCase();
    return _orders.where((order) {
      final matchesStatus = _filter == 'all' || '${order['status']}' == _filter;
      if (!matchesStatus) return false;
      if (query.isEmpty) return true;
      final haystack =
          '${order['service_name'] ?? ''} ${order['provider_name'] ?? ''} '
                  '${order['customer_id'] ?? ''} ${order['phone'] ?? ''} '
                  '${order['reference'] ?? ''}'
              .toLowerCase();
      return haystack.contains(query);
    }).toList();
  }

  int _countFor(String status) => status == 'all'
      ? _orders.length
      : _orders.where((order) => '${order['status']}' == status).length;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final shown = _shown;
    const filters = ['all', 'pending', 'delivered', 'failed', 'reversed'];

    return Scaffold(
      appBar: AppBar(
        title: const Text('My purchases'),
        actions: [
          IconButton(
            onPressed: _refreshing ? null : () => _load(forceRefresh: true),
            icon: _refreshing
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
            child: TextField(
              controller: _search,
              decoration: InputDecoration(
                hintText: 'Search recipient or reference',
                prefixIcon: const Icon(Icons.search_rounded),
                isDense: true,
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _search.clear();
                          setState(() {});
                        },
                        icon: const Icon(Icons.close_rounded),
                      ),
              ),
              onChanged: (_) => setState(() {}),
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: filters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final status = filters[index];
                final selected = _filter == status;
                final count = _countFor(status);
                return ChoiceChip(
                  label: Text(
                    '${_label(status)}${count == 0 ? '' : ' $count'}',
                  ),
                  selected: selected,
                  showCheckmark: false,
                  labelStyle: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    color: selected ? Colors.white : scheme.onSurfaceVariant,
                  ),
                  selectedColor: scheme.primary,
                  backgroundColor: scheme.surface,
                  side: BorderSide(
                    color: selected
                        ? scheme.primary
                        : scheme.outlineVariant.withValues(alpha: .7),
                  ),
                  onSelected: (_) => setState(() => _filter = status),
                );
              },
            ),
          ),
          const SizedBox(height: 6),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null && _orders.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: _NoticeCard(
                        icon: Icons.cloud_off_rounded,
                        title: 'Could not load purchases',
                        text: _error!,
                        color: scheme.error,
                        action: TextButton(
                          onPressed: () => _load(forceRefresh: true),
                          child: const Text('Try again'),
                        ),
                      ),
                    ),
                  )
                : shown.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: _EmptyState(
                        icon: Icons.receipt_long_rounded,
                        title: _orders.isEmpty
                            ? 'Nothing here yet'
                            : 'No matching purchases',
                        text: _orders.isEmpty
                            ? 'Your airtime and bill payments will appear here.'
                            : 'Try a different filter or search term.',
                        accent: scheme.primary,
                      ),
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: () => _load(forceRefresh: true),
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 6, 16, 28),
                      itemCount: shown.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) => _OrderTile(
                        order: shown[index],
                        currency: widget.currency,
                        api: widget.api,
                        onTap: () => _showOrder(shown[index]),
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  void _showOrder(Map<String, dynamic> order) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: Theme.of(context).colorScheme.surface,
    builder: (_) => _OrderDetails(
      order: order,
      currency: widget.currency,
      api: widget.api,
      token: widget.token,
      onChanged: _load,
    ),
  );
}

class _OrderTile extends StatelessWidget {
  const _OrderTile({
    required this.order,
    required this.currency,
    required this.api,
    required this.onTap,
  });

  final Map<String, dynamic> order;
  final String currency;
  final AuthApi api;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = '${order['status'] ?? 'pending'}';
    final amountCurrency = '${order['currency'] ?? currency}';
    final precision =
        (order['currency_minor_units'] as num?)?.toInt() ??
        currencyPrecision(amountCurrency);
    final amount = _minorMoney(
      api,
      order['charged_amount_minor'],
      amountCurrency,
      precision,
      currency,
    );
    final serviceKey = '${order['service'] ?? ''}';
    final providerLabel =
        '${order['provider_name'] ?? _label('${order['service_id'] ?? ''}')}';
    final title = '${order['service_name'] ?? _label(serviceKey)}';
    final recipient = '${order['customer_id'] ?? order['phone'] ?? ''}';
    final subtitle = recipient.isEmpty
        ? providerLabel
        : '$providerLabel · $recipient';
    final created = _relativeTime(order['created_at']);

    return Container(
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .55)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                _ProviderLogo(
                  provider: {
                    'id': '${order['service_id'] ?? ''}',
                    'label': providerLabel,
                  },
                  serviceKey: serviceKey,
                  size: 44,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13.5,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11.5,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          _StatusPill(status: status),
                          if (created.isNotEmpty) ...[
                            const SizedBox(width: 7),
                            Flexible(
                              child: Text(
                                created,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 10,
                                  color: scheme.outline,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      amount,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 13.5,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Icon(
                      Icons.chevron_right_rounded,
                      size: 18,
                      color: scheme.outline,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _OrderDetails extends StatefulWidget {
  const _OrderDetails({
    required this.order,
    required this.currency,
    required this.api,
    required this.token,
    required this.onChanged,
  });

  final Map<String, dynamic> order;
  final String currency;
  final AuthApi api;
  final String token;
  final VoidCallback onChanged;

  @override
  State<_OrderDetails> createState() => _OrderDetailsState();
}

class _OrderDetailsState extends State<_OrderDetails> {
  late Map<String, dynamic> _order;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
  }

  Future<void> _check() async {
    setState(() => _checking = true);
    try {
      final response = await widget.api.checkVasOrder(
        widget.token,
        _order['id'],
      );
      if (!mounted) return;
      setState(() => _order = _map(response['data']));
      widget.onChanged();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final status = '${_order['status'] ?? 'pending'}';
    final currency = '${_order['currency'] ?? widget.currency}';
    final amount = _minorMoney(
      widget.api,
      _order['charged_amount_minor'],
      currency,
      _order['currency_minor_units'],
      widget.currency,
    );
    final serviceKey = '${_order['service'] ?? ''}';
    final providerLabel =
        '${_order['provider_name'] ?? _label('${_order['service_id'] ?? ''}')}';
    final reference = '${_order['reference'] ?? ''}';
    final token = '${_order['token'] ?? ''}';

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _ProviderLogo(
                  provider: {
                    'id': '${_order['service_id'] ?? ''}',
                    'label': providerLabel,
                  },
                  serviceKey: serviceKey,
                  size: 44,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Order details',
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        _relativeTime(_order['created_at']),
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusPill(status: status),
              ],
            ),
            const SizedBox(height: 16),
            _SummaryRow(
              label: 'Service',
              value: '${_order['service_name'] ?? _label(serviceKey)}',
            ),
            _SummaryRow(label: 'Provider', value: providerLabel),
            _SummaryRow(
              label: 'Recipient',
              value: '${_order['customer_id'] ?? _order['phone'] ?? '—'}',
            ),
            _SummaryRow(label: 'Amount', value: amount),
            _SummaryRow(
              label: 'Reference',
              value: reference.isEmpty ? '—' : reference,
            ),
            if (token.isNotEmpty) _TokenCard(token: token),
            if (status == 'pending') ...[
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: FilledButton.icon(
                  onPressed: _checking ? null : _check,
                  style: FilledButton.styleFrom(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(15),
                    ),
                  ),
                  icon: _checking
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.sync_rounded),
                  label: Text(_checking ? 'Checking…' : 'Check status'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
