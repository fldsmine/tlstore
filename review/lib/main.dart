import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'core/links/referral_link.dart';
import 'screens/frontend/landing_sreen.dart';
import 'screens/home_screen.dart';
import 'screens/intro_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/splash_screen.dart';
import 'services/auth_api.dart';
import 'services/push_notification_service.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/app_theme_controller.dart';
import 'core/update/update_gate.dart';
import 'core/update/update_service.dart';
import 'utils/impersonation_handoff.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const TrustLensApp());
}

class TrustLensApp extends StatefulWidget {
  const TrustLensApp({super.key});

  @override
  State<TrustLensApp> createState() => _TrustLensAppState();
}

class _TrustLensAppState extends State<TrustLensApp> {
  final _updates = UpdateController.instance;
  final _appLinks = AppLinks();
  final _navigatorKey = GlobalKey<NavigatorState>();
  StreamSubscription<Uri>? _appLinkSubscription;
  Uri? _pendingReferralLink;
  String? _lastHandledReferralLink;

  @override
  void initState() {
    super.initState();
    _appLinkSubscription = _appLinks.uriLinkStream.listen(
      _handleIncomingLink,
      onError: (Object _) {},
    );
    if (kIsWeb) {
      final uri = Uri.base;
      if (ReferralLink.isRegistrationLink(uri) ||
          uri.queryParameters['telegram_callback'] == '1') {
        _handleIncomingLink(uri);
      }
    }
    unawaited(
      _appLinks
          .getInitialLink()
          .then((uri) {
            if (uri != null) _handleIncomingLink(uri);
          })
          .catchError((Object _) {}),
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _openPendingReferralLink();
      unawaited(AppThemeController.instance.initialize());
      unawaited(PushNotificationService.instance.initialize());
      unawaited(_updates.check(force: false));
    });
  }

  @override
  void dispose() {
    _appLinkSubscription?.cancel();
    super.dispose();
  }

  void _handleIncomingLink(Uri uri) {
    if (kIsWeb &&
        !ReferralLink.isRegistrationLink(uri) &&
        uri.queryParameters['telegram_callback'] != '1') {
      return;
    }
    if (!ReferralLink.isHandledLink(uri)) return;
    if (_lastHandledReferralLink == uri.toString()) return;
    _lastHandledReferralLink = uri.toString();
    _pendingReferralLink = uri;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _openPendingReferralLink();
    });
  }

  void _openPendingReferralLink() {
    final uri = _pendingReferralLink;
    final navigator = _navigatorKey.currentState;
    if (uri == null || navigator == null) return;
    _pendingReferralLink = null;
    if (ReferralLink.isRegistrationLink(uri)) {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) =>
              RegisterScreen(initialReferralCode: ReferralLink.codeFrom(uri)),
        ),
        (_) => false,
      );
    } else if (kIsWeb &&
        uri.queryParameters['telegram_callback'] == '1' &&
        uri.queryParameters['screen'] == 'register') {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => const RegisterScreen(),
        ),
        (_) => false,
      );
    } else if (kIsWeb &&
        uri.queryParameters['telegram_callback'] == '1') {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
        (_) => false,
      );
    } else {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => const _SessionGate()),
        (_) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: AppThemeController.instance,
      builder: (context, _) => MaterialApp(
        navigatorKey: _navigatorKey,
        title: 'TrustLens',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: AppThemeController.instance.mode,
        home: UpdateGate(controller: _updates, child: const _SessionGate()),
      ),
    );
  }
}

class _SessionGate extends StatefulWidget {
  const _SessionGate();

  @override
  State<_SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<_SessionGate> {
  final _api = AuthApi();
  static const _storage = FlutterSecureStorage();
  late final Future<(AuthResult?, bool)> _initial = _restore();

  String? _impersonationNotice;

  Future<(AuthResult?, bool)> _restore() async {
    await _redeemImpersonationIfPresent();
    final values = await Future.wait<Object?>([
      _api.restoreSession(),
      _storage.read(key: 'trustlens.intro_seen'),
    ]);
    final session = values[0] as AuthResult?;
    final seen = values[1] == 'true';
    return (session, seen);
  }

  /// An administrator can open the web app with `?impersonation=<code>`; the
  /// code is redeemed exactly once and removed from the address bar.
  Future<void> _redeemImpersonationIfPresent() async {
    final code = impersonationCodeFromUrl();
    if (code == null) return;
    try {
      await _api.redeemImpersonation(code);
    } on AuthException catch (error) {
      _impersonationNotice = error.message;
    } catch (_) {
      _impersonationNotice =
          'The impersonation link could not be redeemed. Ask the administrator for a new one.';
    } finally {
      clearImpersonationCode();
    }
  }

  Future<void> _openAuth({required bool register}) async {
    await _storage.write(key: 'trustlens.intro_seen', value: 'true');
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => register ? const RegisterScreen() : const LoginScreen(),
      ),
    );
  }

  /// Guest tour: open the public landing page over the intro, with no session.
  /// Back returns to the intro; registering/logging in from the page replaces
  /// the whole stack with the authenticated home screen.
  Future<void> _openExplore() async {
    await _storage.write(key: 'trustlens.intro_seen', value: 'true');
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => LandingScreen(api: _api)),
    );
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<(AuthResult?, bool)>(
    future: _initial,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const SplashScreen();
      }
      final (result, introSeen) = snapshot.data!;
      return result == null
          ? introSeen
                ? LoginScreen(notice: _impersonationNotice)
                : IntroScreen(
                    onLogin: () => _openAuth(register: false),
                    onRegister: () => _openAuth(register: true),
                    onExplore: _openExplore,
                  )
          : HomeScreen(result: result, api: _api);
    },
  );
}
