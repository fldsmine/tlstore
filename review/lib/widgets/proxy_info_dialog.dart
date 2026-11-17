import 'package:flutter/material.dart';

Future<void> showProxyInformation(BuildContext context) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      final colors = theme.colorScheme;
      return FractionallySizedBox(
        heightFactor: .9,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 12, 12),
              child: Row(
                children: [
                  Icon(Icons.shield_outlined, color: colors.primary, size: 27),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Why do you need a TLstore Proxy?',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: () => Navigator.pop(sheetContext),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
                children: [
                  Text(
                    'Your security. Your privacy. Your connection reliability.',
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: colors.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'At TLstore, we take the security, integrity, and reliability of our review environment seriously. Eligible reviewers need an active proxy add-on before they can submit reviews for packages that require it. A proxy routes eligible network traffic through a designated server and can provide a more consistent connection while you work.',
                    style: theme.textTheme.bodyMedium?.copyWith(height: 1.55),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    'Why purchasing a proxy matters',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  _proxyInfoPoint(
                    sheetContext,
                    number: '1',
                    title: 'Enhanced network privacy',
                    body: 'A proxy can show its public IP address to a destination service instead of your network’s public IP. The proxy provider may still be able to see connection information, so choose a trusted service.',
                    icon: Icons.visibility_off_outlined,
                  ),
                  _proxyInfoPoint(
                    sheetContext,
                    number: '2',
                    title: 'Secure data transmission',
                    body: 'A proxy by itself does not encrypt traffic. HTTPS or another encrypted connection protects data in transit between your device and the destination; use secure websites and networks.',
                    icon: Icons.lock_outline_rounded,
                  ),
                  _proxyInfoPoint(
                    sheetContext,
                    number: '3',
                    title: 'Reliable working environment',
                    body: 'A dedicated IP can provide a more consistent network identity during review sessions and help reduce unexpected changes in connection details.',
                    icon: Icons.wifi_tethering_rounded,
                  ),
                  _proxyInfoPoint(
                    sheetContext,
                    number: '4',
                    title: 'Regional compatibility',
                    body: 'Where supported, proxy locations such as the US or UK can help with legitimate region-specific app testing and compatibility checks.',
                    icon: Icons.public_rounded,
                  ),
                  _proxyInfoPoint(
                    sheetContext,
                    number: '5',
                    title: 'More consistent account access',
                    body: 'Keeping a stable connection environment may reduce unusual network changes that can prompt additional security checks. It cannot guarantee that a service will not request verification.',
                    icon: Icons.verified_user_outlined,
                  ),
                  _proxyInfoPoint(
                    sheetContext,
                    number: '6',
                    title: 'Reviewer eligibility',
                    body: 'An active proxy add-on is part of the eligibility requirements for review packages that require it. Without an active add-on, submission may be unavailable or your work may be rejected.',
                    icon: Icons.fact_check_outlined,
                  ),
                  const SizedBox(height: 16),
                  Card(
                    color: colors.secondaryContainer,
                    child: Padding(
                      padding: const EdgeInsets.all(15),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Why is it mandatory?',
                            style: theme.textTheme.titleSmall?.copyWith(
                              color: colors.onSecondaryContainer,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'TLstore requires proxy activation for eligible review-submission activities on packages that specify this requirement. Activate the matching add-on before submitting, or use the available option to submit without it if shown; that submission may not qualify for approval.',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: colors.onSecondaryContainer,
                              height: 1.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Your work deserves a reliable connection. Review the add-on details and locations available to your package before activating proxy access.',
                    style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
                  ),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () => Navigator.pop(sheetContext),
                    child: const Text('Continue to proxy add-ons'),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    },
  );


  Widget _proxyInfoPoint(
    BuildContext context, {
    required String number,
    required String title,
    required String body,
    required IconData icon,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Card(
        margin: EdgeInsets.zero,
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: colors.primaryContainer,
                foregroundColor: colors.onPrimaryContainer,
                child: Text(
                  number,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(icon, size: 18, color: colors.primary),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            title,
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      body,
                      style: theme.textTheme.bodySmall?.copyWith(
                        height: 1.45,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
