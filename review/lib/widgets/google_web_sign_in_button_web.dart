import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:google_sign_in_web/web_only.dart' as google_web;

import '../services/auth_api.dart';

class GoogleWebSignInButton extends StatefulWidget {
  const GoogleWebSignInButton({
    super.key,
    required this.api,
    required this.onIdToken,
    this.enabled = true,
  });

  final AuthApi api;
  final ValueChanged<String> onIdToken;
  final bool enabled;

  @override
  State<GoogleWebSignInButton> createState() => _GoogleWebSignInButtonState();
}

class _GoogleWebSignInButtonState extends State<GoogleWebSignInButton> {
  static Future<void>? _googleInitialization;
  static String? _initializedClientId;

  StreamSubscription<GoogleSignInAuthenticationEvent>? _subscription;
  bool _ready = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    unawaited(_initialize());
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  Future<void> _initialize() async {
    try {
      final response = await widget.api.socialProviderConfig();
      final data = response['data'];
      final clientId = data is Map<String, dynamic>
          ? data['google_server_client_id']?.toString()
          : null;
      if (clientId == null || clientId.isEmpty) {
        throw const AuthException('Google sign-in is not configured.');
      }

      final googleSignIn = GoogleSignIn.instance;
      if (_googleInitialization == null) {
        _initializedClientId = clientId;
        _googleInitialization = googleSignIn.initialize(clientId: clientId);
      } else if (_initializedClientId != clientId) {
        throw const AuthException(
          'The Google web client changed after sign-in was initialized. Reload the page.',
        );
      }
      await _googleInitialization!;
      _subscription = googleSignIn.authenticationEvents.listen(
        (event) async {
          if (event is! GoogleSignInAuthenticationEventSignIn) return;
          final idToken = event.user.authentication.idToken;
          if (idToken == null || idToken.isEmpty) {
            if (mounted) {
              setState(() {
                _error = 'Google did not return an ID token.';
              });
            }
            return;
          }
          widget.onIdToken(idToken);
        },
        onError: (Object error) {
          if (mounted) {
            setState(() => _error = 'Google sign-in failed: $error');
          }
        },
      );
      if (mounted) setState(() => _ready = true);
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) {
        setState(() => _error = 'Google sign-in could not load: $error');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_ready) {
      return SizedBox(
        height: 44,
        child: Center(
          child: _error == null
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Tooltip(
                  message: _error!,
                  child: const Icon(Icons.error_outline_rounded),
                ),
        ),
      );
    }
    return IgnorePointer(
      ignoring: !widget.enabled,
      child: Opacity(
        opacity: widget.enabled ? 1 : 0.5,
        child: google_web.renderButton(
          configuration: google_web.GSIButtonConfiguration(
            type: google_web.GSIButtonType.standard,
            theme: google_web.GSIButtonTheme.outline,
            size: google_web.GSIButtonSize.large,
            text: google_web.GSIButtonText.signinWith,
            shape: google_web.GSIButtonShape.rectangular,
            logoAlignment: google_web.GSIButtonLogoAlignment.left,
            minimumWidth: 120,
          ),
        ),
      ),
    );
  }
}
