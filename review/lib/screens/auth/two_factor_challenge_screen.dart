import 'package:flutter/material.dart';

import '../../services/auth_api.dart';
import '../../widgets/auth_components.dart';

class TwoFactorChallengeScreen extends StatefulWidget {
  const TwoFactorChallengeScreen({
    super.key,
    required this.api,
    required this.challengeToken,
  });

  final AuthApi api;
  final String challengeToken;

  @override
  State<TwoFactorChallengeScreen> createState() =>
      _TwoFactorChallengeScreenState();
}

class _TwoFactorChallengeScreenState extends State<TwoFactorChallengeScreen> {
  final _code = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (_code.text.trim().length < 6) {
      showAuthMessage(
        context,
        'Enter your current authenticator or recovery code.',
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final result = await widget.api.verifyTwoFactorChallenge(
        challengeToken: widget.challengeToken,
        code: _code.text,
      );
      if (mounted) Navigator.of(context).pop(result);
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Authenticator check')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(22),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.shield_moon_rounded,
                      size: 48,
                      color: theme.colorScheme.primary,
                    ),
                    const SizedBox(height: 14),
                    Text(
                      'Verify it’s you',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Enter the 6-digit code from Google Authenticator. You can use one of your recovery codes instead.',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 18),
                    TextField(
                      controller: _code,
                      autofocus: true,
                      keyboardType: TextInputType.visiblePassword,
                      textCapitalization: TextCapitalization.characters,
                      textAlign: TextAlign.center,
                      maxLength: 20,
                      decoration: const InputDecoration(
                        labelText: 'Authenticator or recovery code',
                        counterText: '',
                        prefixIcon: Icon(Icons.password_rounded),
                      ),
                      onSubmitted: (_) => _verify(),
                    ),
                    const SizedBox(height: 14),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _busy ? null : _verify,
                        icon: _busy
                            ? const SizedBox.square(
                                dimension: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.verified_user_outlined),
                        label: Text(
                          _busy ? 'Verifying…' : 'Verify and continue',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
