import 'package:flutter/material.dart';

import '../../services/auth_api.dart';
import 'widgets/ads_share.dart';
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

class LandingScreen extends StatefulWidget {
  const LandingScreen({
    super.key,
    required this.api,
    required this.isUser,
    required this.token,
  });

  final AuthApi api;
  final Map<String, dynamic> isUser;
  final String token;

  @override
  State<LandingScreen> createState() => _LandingScreenState();
}

class _LandingScreenState extends State<LandingScreen> {
  final _scrollController = ScrollController();

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
      body: Center(
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
                      BannerSection(banner: _banner, user: widget.isUser),
                      const SizedBox(height: 24),
                      FeaturedApp(apps: _apps),
                      const SizedBox(height: 24),
                      PaymentOptions(options: _paymentOptions),
                      const SizedBox(height: 24),
                      Leaderboard(leaders: _leaders),
                      const SizedBox(height: 24),
                      HowItWorks(steps: _steps),
                      const SizedBox(height: 24),
                      AdShare(ads: _ads, api: widget.api),
                      const SizedBox(height: 24),
                      AppFeatures(features: _features),
                      const SizedBox(height: 24),
                      VasServicesSection(data: _vasServices),
                      const SizedBox(height: 24),
                      PlatformStats(stats: _stats),
                      const SizedBox(height: 24),
                      TestimoniesList(testimonies: _testimonies),
                      const SizedBox(height: 24),
                      FaqSection(faqs: _faqs),
                      const SizedBox(height: 24),
                      ContactUs(
                        api: widget.api,
                        token: widget.token,
                        contact: _contact,
                        support: _supportLinks,
                        user: widget.isUser,
                      ),
                      const SizedBox(height: 24),
                      FooterSection(footer: _footerData, api: widget.api),
                    ],
                  ),
                ),
        ),
      ),
    );
  }

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
