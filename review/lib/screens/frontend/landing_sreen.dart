import 'package:flutter/material.dart';

import '../../services/auth_api.dart';
import '../auth/login_screen.dart';
import '../auth/register_screen.dart';
import 'widgets/ads_share.dart';
import 'widgets/anim/anim_kit.dart';
import 'widgets/app_features.dart';
import 'widgets/banner_section.dart';
import 'widgets/faq_section.dart';
import 'widgets/featured_app.dart';
import 'widgets/footer_section.dart';
import 'widgets/how_it_works.dart';
import 'widgets/leaderboard.dart';
import 'widgets/platform_stats.dart';
import 'widgets/payment_options.dart';
import 'widgets/testimonies_list.dart';
import 'widgets/contact_us.dart';
import 'widgets/vas_services.dart';

/// Public TLStore landing page.
///
/// The page is browsable without a session: pass no `isUser`/`token` (the
/// defaults) for visitors who arrived from the intro or a login screen.
/// Anything that needs an account then offers "Create account" / "Log in"
/// instead of failing silently.
class LandingScreen extends StatefulWidget {
  const LandingScreen({
    super.key,
    required this.api,
    this.isUser = const <String, dynamic>{},
    this.token,
  });

  final AuthApi api;
  final Map<String, dynamic> isUser;
  final String? token;

  @override
  State<LandingScreen> createState() => _LandingScreenState();
}

class _LandingScreenState extends State<LandingScreen> {
  final _scrollController = ScrollController();

  bool get _isAuthenticated =>
      (widget.token ?? '').isNotEmpty && widget.isUser.isNotEmpty;

  Map<String, dynamic> _payload = const {};
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _openAuth({required bool register}) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => register ? const RegisterScreen() : const LoginScreen(),
      ),
    );
  }

  /// Guests may read every section; an action that needs an account asks them
  /// to create one or log in, without losing the page they were on.
  Future<void> _requireAccount({
    String message = 'Create an account or log in to continue.',
  }) async {
    if (_isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Open this from your dashboard.')),
      );
      return;
    }

    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 2, 22, 22),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Join TLStore',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  message,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: () => Navigator.pop(sheetContext, 'register'),
                  child: const Text('Create account'),
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () => Navigator.pop(sheetContext, 'login'),
                  child: const Text('Log in'),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(sheetContext),
                  child: const Text('Keep exploring'),
                ),
              ],
            ),
          ),
        );
      },
    );

    if (!mounted || action == null) return;
    _openAuth(register: action == 'register');
  }

  void _primaryCta() {
    if (_isAuthenticated) {
      Navigator.of(context).maybePop();
      return;
    }
    _openAuth(register: true);
  }

  void _secondaryCta() {
    if (_isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Open your profile from the dashboard menu.'),
        ),
      );
      return;
    }
    _openAuth(register: false);
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.frontend();
      final data = _asMap(response['data']);
      if (!mounted) return;
      setState(() {
        _payload = data;
        _loading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'We could not load the TLStore landing page.';
        _loading = false;
      });
    }
  }

  Map<String, dynamic> _asMap(Object? value) => value is Map
      ? value.map((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(Object? value) => value is List
      ? value.whereType<Map>().map(_asMap).toList()
      : const <Map<String, dynamic>>[];

  Map<String, dynamic> get _banner => _asMap(_payload['banner']);
  Map<String, dynamic> get _contact => _asMap(_payload['contact_info']);
  Map<String, dynamic> get _footerData => _asMap(_payload['footer']);
  List<Map<String, dynamic>> get _apps => _rows(_payload['products']);
  List<Map<String, dynamic>> get _ads => _rows(_payload['ads']);
  List<Map<String, dynamic>> get _testimonies => _rows(_payload['testimonies']);
  List<Map<String, dynamic>> get _stats => _rows(_payload['platform_stats']);
  List<Map<String, dynamic>> get _paymentOptions =>
      _rows(_payload['pay_option']);
  List<Map<String, dynamic>> get _leaders => _rows(_payload['leaderboard']);
  List<Map<String, dynamic>> get _steps => _rows(_payload['how_it_works']);
  Map<String, dynamic> get _vasServices => _asMap(_payload['vas_services']);
  List<String> get _supportLinks {
    final value = _payload['support_link'];
    if (value is List) {
      return value
          .map((item) => item.toString().trim())
          .where((item) => item.isNotEmpty && item != 'null')
          .toList();
    }
    if (value is String && value.trim().isNotEmpty) return [value.trim()];
    return const <String>[];
  }

  List<String> get _features {
    final value = _payload['features'];
    if (value is List) {
      return value
          .map((item) => item.toString().trim())
          .where((item) => item.isNotEmpty)
          .toList();
    }
    if (value is String && value.trim().isNotEmpty) {
      return value
          .split(RegExp(r'[,\n|]'))
          .map((item) => item.trim())
          .where((item) => item.isNotEmpty)
          .toList();
    }
    return const [
      'App reviews',
      'Sponsored sharing',
      'Earn rewards',
      'Referral rewards',
    ];
  }

  List<Map<String, dynamic>> get _faqs {
    final grouped = _asMap(_payload['faqs']);
    final result = <Map<String, dynamic>>[];
    for (final entry in grouped.entries) {
      if (entry.value is List) {
        for (final row in _rows(entry.value)) {
          result.add({...row, 'category': row['category'] ?? entry.key});
        }
      }
    }
    return result;
  }

  /// Keeps the reference screen's dark purple palette on any host theme.
  ThemeData _landingTheme(BuildContext context) {
    final scheme =
        ColorScheme.fromSeed(
          seedColor: const Color(0xFF7C6CF6),
          brightness: Brightness.dark,
        ).copyWith(
          surface: const Color(0xFF19172A),
          onSurface: const Color(0xFFF1EFFB),
          surfaceContainerLow: const Color(0xFF25213C),
          surfaceContainerHigh: const Color(0xFF2E294A),
          surfaceContainerHighest: const Color(0xFF332E50),
          outlineVariant: const Color(0xFF3A3555),
        );
    return Theme.of(context).copyWith(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
      canvasColor: scheme.surface,
      cardColor: scheme.surfaceContainerLow,
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        elevation: 0,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = _landingTheme(context);

    return Scaffold(
      // Guests always have a visible way into registration while browsing.
      floatingActionButton: _isAuthenticated
          ? null
          : FloatingActionButton.extended(
              onPressed: _primaryCta,
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('Create account'),
            ),
      body: Stack(
        children: [
          const Positioned.fill(child: AmbientBackdrop()),
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1180),
              child: _loading && _payload.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null && _payload.isEmpty
                  ? _loadError(theme)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        controller: _scrollController,
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          // The hero animates itself.
                          BannerSection(
                            banner: _banner,
                            user: widget.isUser,
                            onPrimaryAction: _primaryCta,
                            onSecondaryAction: _secondaryCta,
                          ),
                          _gap,
                          Reveal(
                            child: FeaturedApp(
                              apps: _apps,
                              onOpenApp: (app) => _requireAccount(
                                message:
                                    'Create an account or log in to open app '
                                    'details and start reviewing.',
                              ),
                            ),
                          ),
                          _gap,
                          Reveal(child: PaymentOptions(options: _paymentOptions)),
                          _gap,
                          Reveal(child: Leaderboard(leaders: _leaders)),
                          _gap,
                          // How it works, features, services and stats
                          // run their own staggered, illustrated reveals.
                          HowItWorks(steps: _steps),
                          _gap,
                          Reveal(
                            child: AdShare(
                              ads: _ads,
                              api: widget.api,
                              onOpenAd: (ad) => _requireAccount(
                                message:
                                    'Create an account or log in to join this '
                                    'sponsored sharing campaign.',
                              ),
                            ),
                          ),
                          _gap,
                          AppFeatures(features: _features),
                          _gap,
                          VasServicesSection(data: _vasServices),
                          _gap,
                          PlatformStats(stats: _stats),
                          _gap,
                          Reveal(child: TestimoniesList(testimonies: _testimonies)),
                          _gap,
                          Reveal(child: FaqSection(faqs: _faqs)),
                          _gap,
                          Reveal(
                            child: ContactUs(
                              api: widget.api,
                              token: widget.token ?? '',
                              contact: _contact,
                              support: _supportLinks,
                              user: widget.isUser,
                            ),
                          ),
                          _gap,
                          Reveal(
                            offset: const Offset(0, 16),
                            child: FooterSection(footer: _footerData, api: widget.api),
                          ),
                        ],
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  static const _gap = SizedBox(height: 24);

  Widget _loadError(ThemeData theme) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: theme.colorScheme.error,
          ),
          const SizedBox(height: 12),
          Text(
            _error ?? 'Landing content is unavailable.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try again'),
          ),
        ],
      ),
    ),
  );
}
