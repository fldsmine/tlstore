import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'auth_components.dart';

class AuthProviderButtons extends StatelessWidget {
  const AuthProviderButtons({
    super.key,
    required this.loadingProvider,
    required this.onGoogle,
    required this.onFacebook,
    required this.onTelegram,
    this.googleWebButton,
  });

  final String? loadingProvider;
  final VoidCallback onGoogle;
  final VoidCallback onFacebook;
  final VoidCallback onTelegram;
  final Widget? googleWebButton;

  @override
  Widget build(BuildContext context) {
    final busy = loadingProvider != null;
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: kIsWeb
                  ? (googleWebButton ?? const SizedBox.shrink())
                  : ProviderButton(
                      label: loadingProvider == 'google'
                          ? 'Connecting…'
                          : 'Google',
                      icon: Icons.g_mobiledata_rounded,
                      onTap: busy ? () {} : onGoogle,
                    ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ProviderButton(
                label: loadingProvider == 'facebook'
                    ? 'Connecting…'
                    : 'Facebook',
                icon: Icons.facebook_rounded,
                onTap: busy ? () {} : onFacebook,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          child: ProviderButton(
            label: loadingProvider == 'telegram'
                ? 'Connecting…'
                : 'Continue with Telegram',
            icon: Icons.send_rounded,
            onTap: busy ? () {} : onTelegram,
          ),
        ),
      ],
    );
  }
}
