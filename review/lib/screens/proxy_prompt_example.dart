import 'package:flutter/material.dart';

import '../widgets/proxy_prompt_dialog.dart';

/// Drop this page into a route to try both reusable proxy prompt layouts.
///
/// The sample plan intentionally changes the reference values to demonstrate
/// that the price, copy, and feature list are supplied at runtime.
class ProxyPromptExamplePage extends StatelessWidget {
  const ProxyPromptExamplePage({super.key});

  static const _samplePlan = ProxyPromptPlan(
    storeName: 'TrustLens Store',
    titleLead: 'Why do I need\na ',
    titleHighlight: 'Proxy',
    titleTail: '?',
    noticeLead: 'An ',
    noticeHighlight: 'active proxy',
    noticeBody:
        ' is needed to submit reviews for this plan. Work completed without one ',
    noticeWarning: 'may not be approved.',
    price: '\$7.50',
    billingPeriod: '/month',
    requiredLabel: 'Required',
    purchaseLabel: 'CHOOSE THIS PROXY',
    trustLabel: 'Secure checkout',
    footerBrand: 'TrustLens Store',
    features: <ProxyPromptFeature>[
      ProxyPromptFeature(label: '3 Locations', icon: Icons.location_on_rounded),
      ProxyPromptFeature(label: '5GB Bandwidth', icon: Icons.speed_rounded),
      ProxyPromptFeature(label: 'Priority Support', icon: Icons.email_rounded),
      ProxyPromptFeature(label: 'SSO + SCIM', icon: Icons.shield_outlined),
      ProxyPromptFeature(label: 'Dedicated IPs', icon: Icons.dns_rounded),
      ProxyPromptFeature(label: 'Encrypted Tunnel', icon: Icons.lock_rounded),
    ],
  );

  void _showPurchaseMessage(BuildContext context, String layout) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text('$layout dialog purchase tapped.')),
      );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Proxy dialog examples')),
    body: Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560),
        child: ListView(
          padding: const EdgeInsets.all(24),
          shrinkWrap: true,
          children: [
            const Icon(
              Icons.shield_rounded,
              size: 54,
              color: Color(0xFF078B4D),
            ),
            const SizedBox(height: 12),
            Text(
              'Choose a prompt layout',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Both dialogs use the same plan data and purchase callback. '
              'The first presents a vertical feature list; the second uses a '
              'compact feature grid.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                height: 1.5,
              ),
            ),
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: () => ProxyRequirementDialog.show<void>(
                context: context,
                plan: _samplePlan,
                onPurchase: () => _showPurchaseMessage(context, 'Tall'),
                onReview: () => _showPurchaseMessage(context, 'Tall'),
              ),
              icon: const Icon(Icons.view_agenda_rounded),
              label: const Text('Show tall prompt'),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () => ProxyAddonsDialog.show<void>(
                context: context,
                plan: _samplePlan,
                onPurchase: () => _showPurchaseMessage(context, 'Addons'),
                onReview: () => _showPurchaseMessage(context, 'Addons'),
              ),
              icon: const Icon(Icons.view_agenda_rounded),
              label: const Text('Show addons prompt'),
            ),
            const SizedBox(height: 12),
            FilledButton.tonalIcon(
              onPressed: () => ProxyRequirementGridDialog.show<void>(
                context: context,
                plan: _samplePlan,
                onPurchase: () => _showPurchaseMessage(context, 'Grid'),
                onReview: () => _showPurchaseMessage(context, 'Grid')
              ),
              icon: const Icon(Icons.grid_view_rounded),
              label: const Text('Show compact grid prompt'),
            ),
            const SizedBox(height: 20),
            const _UsageNote(),
          ],
        ),
      ),
    ),
  );
}

class _UsageNote extends StatelessWidget {
  const _UsageNote();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.primaryContainer.withValues(alpha: .45),
        borderRadius: BorderRadius.circular(18),
      ),
      child: const SelectableText(
        r"""await ProxyRequirementGridDialog.show<void>(
  context: context,
  plan: const ProxyPromptPlan(price: r'$7.50'),
  onPurchase: startCheckout,
);""",
        style: TextStyle(height: 1.5, fontFamily: 'monospace', fontSize: 12),
      ),
    );
  }
}
