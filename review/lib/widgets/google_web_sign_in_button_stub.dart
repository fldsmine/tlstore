import 'package:flutter/material.dart';

import '../services/auth_api.dart';

class GoogleWebSignInButton extends StatelessWidget {
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
  Widget build(BuildContext context) => const SizedBox.shrink();
}
