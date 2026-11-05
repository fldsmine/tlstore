import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_facebook_auth/flutter_facebook_auth.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../config/firebase_options.dart';
import 'auth_api.dart';

class SocialAuthClient {
  static Future<void>? _googleInitialization;
  static Future<void>? _facebookWebInitialization;
  static const _telegramStateKey = 'trustlens.auth.telegram.pending_state';
  static const _stateStorage = FlutterSecureStorage();

  String createTelegramState() {
    final random = Random.secure();
    final bytes = List<int>.generate(32, (_) => random.nextInt(256));
    return base64Url.encode(bytes).replaceAll('=', '');
  }

  Future<void> savePendingTelegramState(String state) =>
      _stateStorage.write(key: _telegramStateKey, value: state);

  Future<String?> takePendingTelegramState() async {
    try {
      final state = await _stateStorage.read(key: _telegramStateKey);
      if (state != null) {
        await _stateStorage.delete(key: _telegramStateKey);
      }
      return state;
    } catch (error) {
      debugPrint(
        '[telegram] Could not restore the pending login state: $error',
      );
      return null;
    }
  }

  Future<void> clearPendingTelegramState() =>
      _stateStorage.delete(key: _telegramStateKey);

  Future<Map<String, dynamic>?> googleCredentials(AuthApi api) async {
    final config = await _providerConfig(api);
    final serverClientId = config['google_server_client_id']?.toString();
    if (serverClientId == null || serverClientId.isEmpty) {
      throw const AuthException(
        'Google sign-in is not configured on the server.',
      );
    }

    _googleInitialization ??= GoogleSignIn.instance.initialize(
      clientId: defaultTargetPlatform == TargetPlatform.iOS
          ? DefaultFirebaseOptions.ios.iosClientId
          : null,
      serverClientId: serverClientId,
    );
    await _googleInitialization;
    final account = await GoogleSignIn.instance.authenticate();
    final idToken = account.authentication.idToken;
    if (idToken == null || idToken.isEmpty) {
      throw const AuthException(
        'Google did not return an ID token. Check the OAuth client configuration.',
      );
    }
    return {'id_token': idToken};
  }

  Future<Map<String, dynamic>?> facebookCredentials(AuthApi api) async {
    if (kIsWeb) {
      await initializeWebFacebook(api);
    }
    final result = await FacebookAuth.instance.login(
      permissions: const ['email', 'public_profile'],
      loginTracking: LoginTracking.enabled,
    );
    if (result.status == LoginStatus.cancelled) return null;
    if (result.status != LoginStatus.success || result.accessToken == null) {
      throw AuthException(
        result.message ?? 'Facebook sign-in could not be completed.',
      );
    }
    return {'access_token': result.accessToken!.tokenString};
  }

  Future<void> initializeWebFacebook(AuthApi api) async {
    if (!kIsWeb || FacebookAuth.instance.isWebSdkInitialized) return;
    _facebookWebInitialization ??= _initializeWebFacebook(api);
    await _facebookWebInitialization;
  }

  Future<void> _initializeWebFacebook(AuthApi api) async {
    if (!FacebookAuth.instance.isWebSdkInitialized) {
      final config = await _providerConfig(api);
      final appId = config['facebook_app_id']?.toString();
      if (appId == null || appId.isEmpty) {
        throw const AuthException(
          'Facebook sign-in is not configured for the web app.',
        );
      }
      await FacebookAuth.instance.webAndDesktopInitialize(
        appId: appId,
        cookie: true,
        xfbml: false,
        version: 'v23.0',
      );
    }
  }

  Future<Uri> telegramLoginUri(
    AuthApi api,
    String state, {
    bool register = false,
  }) async {
    final config = await _providerConfig(api);
    final baseUrl = config['telegram_login_url']?.toString();
    if (baseUrl == null || baseUrl.isEmpty) {
      throw const AuthException(
        'Telegram sign-in is not configured on the server.',
      );
    }
    final base = Uri.tryParse(baseUrl);
    if (base == null || !base.hasScheme || base.host.isEmpty) {
      throw const AuthException('The Telegram login URL is invalid.');
    }
    return base.replace(
      queryParameters: {
        ...base.queryParameters,
        'state': state,
        if (kIsWeb) 'web': '1',
        if (kIsWeb) 'screen': register ? 'register' : 'login',
      },
    );
  }

  Future<Map<String, dynamic>> _providerConfig(AuthApi api) async {
    final response = await api.socialProviderConfig();
    final data = response['data'];
    if (data is! Map<String, dynamic>) {
      throw const AuthException(
        'The server returned invalid social sign-in settings.',
      );
    }
    return data;
  }
}
