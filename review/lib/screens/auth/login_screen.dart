import 'dart:async';
import 'dart:convert';

import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/auth_api.dart';
import '../../services/social_auth_client.dart';
import '../../widgets/auth_components.dart';
import '../../widgets/auth_provider_buttons.dart';
import '../../widgets/auth_spotlight_panel.dart';
import '../../widgets/google_web_sign_in_button.dart';
import '../home_screen.dart';
import 'account_access_screen.dart';
import 'password_recovery_screen.dart';
import 'register_screen.dart';
import 'two_factor_challenge_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();
  final _api = AuthApi();
  final _social = SocialAuthClient();
  final _appLinks = AppLinks();
  StreamSubscription<Uri>? _telegramLinkSubscription;
  String? _pendingTelegramState;
  String? _lastTelegramCallback;
  bool _handlingTelegramLink = false;
  bool _loading = false;
  String? _loadingProvider;
  bool _hidePassword = true;

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    _telegramLinkSubscription?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();

    unawaited(
      _social.initializeWebFacebook(_api).catchError((Object error) {
        debugPrint('Facebook web initialization failed: $error');
      }),
    );
    _telegramLinkSubscription = _appLinks.uriLinkStream.listen(
      _handleTelegramLink,
    );
    if (kIsWeb && Uri.base.queryParameters['telegram_callback'] == '1') {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _handleTelegramLink(Uri.base);
      });
    }

    unawaited(
      _appLinks
          .getInitialLink()
          .then((uri) {
            if (uri != null) _handleTelegramLink(uri);
          })
          .catchError((_) {}),
    );
  }

  Future<void> _signIn() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _loading = true);
    try {
      final result = await _api.login(
        identifier: _identifierController.text,
        password: _passwordController.text,
      );
      await _completeSignIn(result);
    } on AuthException catch (error) {
      if (mounted && error.accountState != null) {
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => AccountAccessScreen(
              state: error.accountState!,
              reason: error.accountReason ?? error.message,
              supportEmail: error.supportEmail,
              onLogout: _returnToLogin,
            ),
          ),
        );
      } else if (mounted) {
        showAuthMessage(context, error.message);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _googleSignIn() async {
    try {
      final credentials = await _social.googleCredentials(_api);
      if (credentials != null) await _submitSocial('google', credentials);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } catch (error) {
      if (mounted)
        showAuthMessage(
          context,
          'Google sign-in could not be completed. $error',
        );
    }
  }

  Future<void> _facebookSignIn() async {
    try {
      final credentials = await _social.facebookCredentials(_api);
      if (credentials != null) await _submitSocial('facebook', credentials);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } catch (error) {
      if (mounted)
        showAuthMessage(
          context,
          'Facebook sign-in could not be completed. $error',
        );
    }
  }

  Future<void> _telegramSignIn() async {
    final state = _social.createTelegramState();
    try {
      final uri = await _social.telegramLoginUri(_api, state);
      await _social.savePendingTelegramState(state);
      _pendingTelegramState = state;
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        _pendingTelegramState = null;
        await _social.clearPendingTelegramState();
        if (mounted)
          showAuthMessage(context, 'Could not open Telegram sign-in.');
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } catch (error) {
      if (mounted)
        showAuthMessage(
          context,
          'Telegram sign-in could not be started. $error',
        );
    }
  }

  Future<void> _handleTelegramLink(Uri uri) async {
    final isNativeCallback =
        uri.scheme == 'trustlens' && uri.host == 'telegram-login';
    final isWebCallback =
        kIsWeb && uri.queryParameters['telegram_callback'] == '1';
    if (!isNativeCallback && !isWebCallback) return;
    if (_lastTelegramCallback == uri.toString()) return;
    _lastTelegramCallback = uri.toString();
    debugPrint('[telegram] Return link received by the login screen.');
    if (_handlingTelegramLink) return;
    _handlingTelegramLink = true;
    try {
      final state = uri.queryParameters['state'];
      final encodedPayload = uri.queryParameters['payload'];
      final expectedState =
          _pendingTelegramState ?? await _social.takePendingTelegramState();
      debugPrint(
        '[telegram] State matched: ${state != null && state == expectedState}; '
        'payload present: ${encodedPayload != null}.',
      );
      if (state == null || state != expectedState || encodedPayload == null) {
        if (mounted)
          showAuthMessage(
            context,
            'The Telegram sign-in session expired. Please try again.',
          );
        return;
      }
      _pendingTelegramState = null;
      await _social.clearPendingTelegramState();
      try {
        final payload = jsonDecode(encodedPayload);
        if (payload is! Map<String, dynamic>) throw const FormatException();
        await _submitSocial('telegram', {'telegram': payload});
      } on FormatException {
        if (mounted)
          showAuthMessage(context, 'Telegram returned invalid sign-in data.');
      }
    } finally {
      _handlingTelegramLink = false;
    }
  }

  Future<void> _submitSocial(
    String provider,
    Map<String, dynamic> credentials, {
    String? countryCode,
  }) async {
    setState(() {
      _loading = true;
      _loadingProvider = provider;
    });
    try {
      final result = await _api.socialLogin(
        provider: provider,
        credentials: credentials,
        countryCode: countryCode,
      );
      await _completeSignIn(result, clearBackStack: true);
    } on AuthException catch (error) {
      if (!mounted) return;
      if (provider == 'telegram') {
        debugPrint('[telegram] API rejected sign-in: ${error.message}');
      }
      if (error.accountState != null) {
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => AccountAccessScreen(
              state: error.accountState!,
              reason: error.accountReason ?? error.message,
              supportEmail: error.supportEmail,
              onLogout: _returnToLogin,
            ),
          ),
        );
      } else if (countryCode == null &&
          error.message.toLowerCase().contains('select a country')) {
        final selectedCountry = await _chooseCountry();
        if (selectedCountry != null && mounted) {
          await _submitSocial(
            provider,
            credentials,
            countryCode: selectedCountry,
          );
        }
      } else {
        showAuthMessage(context, error.message);
      }
    } finally {
      if (mounted)
        setState(() {
          _loading = false;
          _loadingProvider = null;
        });
    }
  }

  Future<void> _completeSignIn(
    AuthResult result, {
    bool clearBackStack = false,
  }) async {
    var completed = result;
    if (result.requiresTwoFactor) {
      final verified = await Navigator.of(context).push<AuthResult>(
        MaterialPageRoute<AuthResult>(
          builder: (_) => TwoFactorChallengeScreen(
            api: _api,
            challengeToken: result.challengeToken!,
          ),
        ),
      );
      if (verified == null) return;
      completed = verified;
    }
    if (!mounted) return;
    final route = MaterialPageRoute<void>(
      builder: (_) => HomeScreen(result: completed, api: _api),
    );
    if (clearBackStack) {
      Navigator.of(context).pushAndRemoveUntil(route, (_) => false);
    } else {
      Navigator.of(context).pushReplacement(route);
    }
  }

  Future<String?> _chooseCountry() async {
    try {
      final countries = await _api.countries();
      if (!mounted || countries.isEmpty) return null;
      return await showDialog<String>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Choose your country'),
          content: SizedBox(
            width: double.maxFinite,
            child: ListView.builder(
              shrinkWrap: true,
              itemCount: countries.length,
              itemBuilder: (_, index) {
                final country = countries[index];
                return ListTile(
                  title: Text('${country.flag}  ${country.name}'),
                  onTap: () => Navigator.pop(dialogContext, country.code),
                );
              },
            ),
          ),
        ),
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
      return null;
    }
  }

  Future<void> _returnToLogin() async {
    await _api.clearSession();
    if (!mounted) return;
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final desktop = constraints.maxWidth >= 1000;
            return Center(
              child: SingleChildScrollView(
                padding: EdgeInsets.symmetric(
                  horizontal: desktop ? 40 : 20,
                  vertical: 24,
                ),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1200),
                  child: desktop
                      ? Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(child: _form()),
                            const SizedBox(width: 72),
                            Expanded(child: AuthSpotlightPanel(api: _api)),
                          ],
                        )
                      : Column(
                          children: [
                            _form(),
                            const SizedBox(height: 46),
                            AuthSpotlightPanel(api: _api),
                          ],
                        ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _form() {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 500),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const AuthBrandHeader(),
            const SizedBox(height: 31),
            const _WelcomeCard(),
            const SizedBox(height: 27),
            const AuthIntro(
              title: 'Welcome back',
              subtitle: 'Sign in to find your next task and keep track of your rewards.',
            ),
            const SizedBox(height: 23),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  AuthField(
                    controller: _identifierController,
                    label: 'Email or username',
                    hint: 'you@example.com or username',
                    icon: Icons.mail_outline_rounded,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.next,
                    validator: (value) {
                      if (value == null || value.trim().isEmpty)
                        return 'Enter your email or username.';
                      if (value.trim().contains('@') &&
                          !RegExp(r'^.+@.+\..+$').hasMatch(value.trim()))
                        return 'Enter a valid email address.';
                      return null;
                    },
                  ),
                  const SizedBox(height: 17),
                  AuthField(
                    controller: _passwordController,
                    label: 'Password',
                    hint: 'Enter your password',
                    icon: Icons.lock_outline_rounded,
                    obscureText: _hidePassword,
                    onToggleVisibility: () =>
                        setState(() => _hidePassword = !_hidePassword),
                    textInputAction: TextInputAction.done,
                    validator: (value) => value == null || value.isEmpty
                        ? 'Enter your password.'
                        : null,
                  ),
                ],
              ),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => ForgotPasswordScreen(
                      api: _api,
                      initialEmail:
                          _identifierController.text.contains('@')
                          ? _identifierController.text
                          : '',
                    ),
                  ),
                ),
                style: TextButton.styleFrom(
                  foregroundColor: brandGreen,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 2,
                    vertical: 8,
                  ),
                ),
                child: const Text(
                  'Forgot password?',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 5),
            AuthPrimaryButton(
              label: 'Sign in',
              loading: _loading,
              onPressed: _signIn,
            ),
            const SizedBox(height: 21),
            const AuthDivider(),
            const SizedBox(height: 15),
            AuthProviderButtons(
              loadingProvider: _loading
                  ? (_loadingProvider ?? 'email')
                  : null,
              onGoogle: () => unawaited(_googleSignIn()),
              onFacebook: () => unawaited(_facebookSignIn()),
              onTelegram: () => unawaited(_telegramSignIn()),
              googleWebButton: GoogleWebSignInButton(
                api: _api,
                enabled: !_loading,
                onIdToken: (idToken) => unawaited(
                  _submitSocial('google', {'id_token': idToken}),
                ),
              ),
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text(
                  'New to TrustLens?',
                  style: TextStyle(fontSize: 13, color: mutedInk),
                ),
                TextButton(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const RegisterScreen(),
                    ),
                  ),
                  style: TextButton.styleFrom(
                    foregroundColor: brandGreen,
                  ),
                  child: const Text(
                    'Create account',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            const AuthFooter(),
          ],
        ),
      ),
    );
  }

}

class _WelcomeCard extends StatelessWidget {
  const _WelcomeCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF145A3D), Color(0xFF28704F)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1B145A3D),
            blurRadius: 20,
            offset: Offset(0, 9),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: .15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Text(
                    'REVIEW  •  SHARE  •  EARN',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.1,
                    ),
                  ),
                ),
                const SizedBox(height: 15),
                const Text(
                  'Your time and\nopinion matter.',
                  style: TextStyle(
                    color: Colors.white,
                    height: 1.16,
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -.3,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Pick a task. Give honest feedback. Earn fairly.',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: .78),
                    fontSize: 11,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            width: 89,
            height: 89,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(26),
            ),
            child: const Stack(
              alignment: Alignment.center,
              children: [
                Icon(Icons.stars_rounded, size: 52, color: Color(0xFFF2D48C)),
                Positioned(
                  right: 7,
                  top: 9,
                  child: Icon(
                    Icons.check_circle,
                    size: 20,
                    color: Colors.white,
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
