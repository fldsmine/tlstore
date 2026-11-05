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
import '../../widgets/google_web_sign_in_button.dart';
import '../../widgets/auth_spotlight_panel.dart';
import '../../widgets/country_picker.dart';
import '../legal_document_screen.dart';
import '../home_screen.dart';
import 'account_access_screen.dart';
import 'two_factor_challenge_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, this.initialReferralCode});

  final String? initialReferralCode;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _fullName = TextEditingController();
  final _username = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _referral = TextEditingController();
  final _api = AuthApi();
  final _social = SocialAuthClient();
  final _appLinks = AppLinks();
  StreamSubscription<Uri>? _telegramLinkSubscription;
  String? _pendingTelegramState;
  String? _lastTelegramCallback;
  bool _handlingTelegramLink = false;
  List<CountryOption> _countries = const [];
  CountryOption? _selectedCountry;
  bool _loadingCountries = true;
  bool _submitting = false;
  bool _hidePassword = true;
  String? _loadingProvider;

  @override
  void initState() {
    super.initState();
    unawaited(
      _social.initializeWebFacebook(_api).catchError((Object error) {
        debugPrint('Facebook web initialization failed: $error');
      }),
    );
    final referralCode = widget.initialReferralCode?.trim();
    if (referralCode != null && referralCode.isNotEmpty) {
      _referral.text = referralCode.toUpperCase();
    }
    _loadCountries();
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

  @override
  void dispose() {
    _telegramLinkSubscription?.cancel();
    _fullName.dispose();
    _username.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _referral.dispose();
    super.dispose();
  }

  Future<void> _loadCountries() async {
    try {
      final countries = await _api.countries();
      if (!mounted) return;
      setState(() {
        _countries = countries;
        _selectedCountry =
            countries.where((country) => country.code == 'NG').firstOrNull ??
            (countries.isEmpty ? null : countries.first);
        _loadingCountries = false;
      });
    } on AuthException catch (error) {
      if (mounted) {
        setState(() => _loadingCountries = false);
        showAuthMessage(context, error.message);
      }
    }
  }

  String? _required(String? value, String label) =>
      value == null || value.trim().isEmpty ? '$label is required.' : null;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final country = _selectedCountry;
    if (country == null) {
      showAuthMessage(context, 'Select your country to continue.');
      return;
    }
    setState(() => _submitting = true);
    try {
      final result = await _api.register(
        fullName: _fullName.text,
        username: _username.text,
        email: _email.text,
        phone: '${country.dialCode}${_phone.text.trim()}',
        password: _password.text,
        countryCode: country.code,
        referralCode: _referral.text,
      );
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => HomeScreen(result: result, api: _api),
        ),
        (_) => false,
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
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
      final uri = await _social.telegramLoginUri(_api, state, register: true);
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
    if (_handlingTelegramLink) return;
    _handlingTelegramLink = true;
    try {
      final state = uri.queryParameters['state'];
      final encodedPayload = uri.queryParameters['payload'];
      final expectedState =
          _pendingTelegramState ?? await _social.takePendingTelegramState();
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
    Map<String, dynamic> credentials,
  ) async {
    final country = _selectedCountry;
    if (country == null) {
      showAuthMessage(
        context,
        'Select your country before creating an account.',
      );
      return;
    }
    setState(() {
      _submitting = true;
      _loadingProvider = provider;
    });
    try {
      final result = await _api.socialLogin(
        provider: provider,
        credentials: credentials,
        countryCode: country.code,
      );
      if (!mounted) return;
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
        if (verified == null || !mounted) return;
        completed = verified;
      }
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => HomeScreen(result: completed, api: _api),
        ),
        (_) => false,
      );
    } on AuthException catch (error) {
      if (!mounted) return;
      if (error.accountState != null) {
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => AccountAccessScreen(
              state: error.accountState!,
              reason: error.accountReason ?? error.message,
              supportEmail: error.supportEmail,
              onLogout: () => Navigator.of(context).pop(),
            ),
          ),
        );
      } else {
        showAuthMessage(context, error.message);
      }
    } finally {
      if (mounted)
        setState(() {
          _submitting = false;
          _loadingProvider = null;
        });
    }
  }

  void _policy(String label) {
    final slug = label.toLowerCase().contains('privacy') ? 'privacy' : 'terms';
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => LegalDocumentScreen(api: _api, slug: slug),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: SafeArea(
        top: false,
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
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const AuthBrandHeader(compact: true),
            const SizedBox(height: 24),
            const AuthIntro(
              title: 'Create your account',
              subtitle:
                  'Join the community and start earning from honest feedback.',
            ),
            const SizedBox(height: 27),
            AuthField(
              controller: _fullName,
              label: 'Full name *',
              hint: 'Your full name',
              icon: Icons.person_outline_rounded,
              textCapitalization: TextCapitalization.words,
              textInputAction: TextInputAction.next,
              validator: (value) => _required(value, 'Name'),
            ),
            const SizedBox(height: 15),
            AuthField(
              controller: _username,
              label: 'Username *',
              hint: 'Choose a username',
              icon: Icons.alternate_email_rounded,
              textInputAction: TextInputAction.next,
              validator: (value) {
                final required = _required(value, 'Username');
                if (required != null) return required;
                return RegExp(r'^[A-Za-z0-9_-]{3,80}$').hasMatch(value!.trim())
                    ? null
                    : 'Use 3–80 letters, numbers, dashes or underscores.';
              },
            ),
            const SizedBox(height: 15),
            AuthField(
              controller: _email,
              label: 'Email *',
              hint: 'johndoe@email.com',
              icon: Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              validator: (value) {
                if (value == null ||
                    !RegExp(r'^.+@.+\..+$').hasMatch(value.trim()))
                  return 'Enter a valid email address.';
                return null;
              },
            ),
            const SizedBox(height: 15),
            const Text(
              'WhatsApp / mobile number *',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: Color(0xFF425048),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                if (_loadingCountries)
                  const SizedBox(
                    width: 80,
                    height: 54,
                    child: Center(
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                else if (_selectedCountry != null)
                  CountryPicker(
                    countries: _countries,
                    selected: _selectedCountry!,
                    onChanged: (country) =>
                        setState(() => _selectedCountry = country),
                  )
                else
                  IconButton(
                    onPressed: _loadCountries,
                    icon: const Icon(Icons.refresh_rounded),
                  ),
                const SizedBox(width: 9),
                Expanded(
                  child: AuthField(
                    controller: _phone,
                    label: '',
                    hint: 'Enter mobile number',
                    icon: Icons.phone_outlined,
                    keyboardType: TextInputType.phone,
                    textInputAction: TextInputAction.next,
                    validator: (value) => _required(value, 'Mobile number'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 15),
            AuthField(
              controller: _password,
              label: 'Create password *',
              hint: 'At least 10 characters',
              icon: Icons.lock_outline_rounded,
              obscureText: _hidePassword,
              onToggleVisibility: () =>
                  setState(() => _hidePassword = !_hidePassword),
              textInputAction: TextInputAction.next,
              validator: (value) {
                if (value == null || value.length < 10)
                  return 'Use at least 10 characters.';
                if (!RegExp(r'[A-Za-z]').hasMatch(value) ||
                    !RegExp(r'[0-9]').hasMatch(value))
                  return 'Include at least one letter and one number.';
                return null;
              },
            ),
            const SizedBox(height: 8),
            const Text(
              'Use 10 or more characters, including a letter and a number.',
              style: TextStyle(color: mutedInk, fontSize: 11),
            ),
            const SizedBox(height: 15),
            AuthField(
              controller: _referral,
              label: 'Referral code (optional)',
              hint: 'Enter referral code',
              icon: Icons.card_giftcard_outlined,
              readOnly: widget.initialReferralCode?.trim().isNotEmpty == true,
              suffixIcon: widget.initialReferralCode?.trim().isNotEmpty == true
                  ? const Icon(Icons.lock_outline_rounded, size: 18)
                  : null,
              textInputAction: TextInputAction.done,
            ),
            const SizedBox(height: 22),
            AuthPrimaryButton(
              label: 'Create account',
              loading: _submitting,
              onPressed: _submit,
            ),
            const SizedBox(height: 18),
            const AuthDivider(),
            const SizedBox(height: 14),
            AuthProviderButtons(
              loadingProvider: _submitting
                  ? (_loadingProvider ?? 'email')
                  : null,
              onGoogle: () => unawaited(_googleSignIn()),
              onFacebook: () => unawaited(_facebookSignIn()),
              onTelegram: () => unawaited(_telegramSignIn()),
              googleWebButton: GoogleWebSignInButton(
                api: _api,
                enabled: !_submitting,
                onIdToken: (idToken) => unawaited(
                  _submitSocial('google', {'id_token': idToken}),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              children: [
                const Text(
                  'By signing up, you agree to our ',
                  style: TextStyle(color: mutedInk, fontSize: 11, height: 1.5),
                ),
                _policyLink('Terms of Use'),
                const Text(
                  ' & ',
                  style: TextStyle(color: mutedInk, fontSize: 11),
                ),
                _policyLink('Privacy Policy'),
                const Text(
                  ' and other applicable policies.',
                  style: TextStyle(color: mutedInk, fontSize: 11, height: 1.5),
                ),
              ],
            ),
            const SizedBox(height: 17),
            Center(
              child: TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text(
                  'Already have an account?  Sign in',
                  style: TextStyle(
                    color: brandGreen,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            const AuthFooter(),
          ],
        ),
      ),
    );
  }

  Widget _policyLink(String text) => GestureDetector(
    onTap: () => _policy(text),
    child: Text(
      text,
      style: const TextStyle(
        color: brandGreen,
        fontSize: 11,
        fontWeight: FontWeight.w700,
        decoration: TextDecoration.underline,
      ),
    ),
  );
}
