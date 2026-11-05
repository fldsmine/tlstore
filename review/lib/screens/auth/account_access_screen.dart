import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../widgets/auth_components.dart';

class AccountAccessScreen extends StatelessWidget {
  const AccountAccessScreen({
    super.key,
    required this.state,
    required this.reason,
    required this.supportEmail,
    required this.onLogout,
  });

  final String state;
  final String reason;
  final String? supportEmail;
  final VoidCallback onLogout;

  Future<void> _contactSupport(BuildContext context) async {
    final email = supportEmail?.trim();
    if (email == null || email.isEmpty) {
      showAuthMessage(context, 'Please contact TrustLens support for help.');
      return;
    }
    final uri = Uri(
      scheme: 'mailto',
      path: email,
      queryParameters: {
        'subject':
            'TrustLens account ${state == 'deleted' ? 'recovery' : 'access'} help',
      },
    );
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        context.mounted) {
      showAuthMessage(
        context,
        'Could not open an email app. Contact $email for help.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final deleted = state == 'deleted';
    final title = deleted
        ? 'This account was deleted'
        : 'Account access is restricted';
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 82,
                    height: 82,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1E8),
                      borderRadius: BorderRadius.circular(26),
                    ),
                    child: Icon(
                      deleted
                          ? Icons.person_off_outlined
                          : Icons.lock_person_outlined,
                      size: 42,
                      color: const Color(0xFFB86A32),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    reason,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: mutedInk, height: 1.5),
                  ),
                  const SizedBox(height: 28),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: () => _contactSupport(context),
                      icon: const Icon(Icons.support_agent_rounded),
                      label: const Text('Contact support'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: onLogout,
                      icon: const Icon(Icons.logout_rounded),
                      label: const Text('Log out'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
