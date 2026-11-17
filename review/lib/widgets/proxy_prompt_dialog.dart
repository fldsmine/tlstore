import 'package:flutter/material.dart';

import '../core/utils/pulsing_info_button.dart';
import 'proxy_info_dialog.dart';

/// A single feature shown on the proxy plan card.
@immutable
class ProxyPromptFeature {
  const ProxyPromptFeature({required this.label, required this.icon});

  final String label;
  final IconData icon;
}

/// Content shared by both proxy prompt layouts.
///
/// Supply values from the selected plan rather than baking price or features
/// into the dialog. The defaults mirror the two reference images in
/// `dialog/dialog.png` and `dialog/dialog2.png`.
@immutable
class ProxyPromptPlan {
  const ProxyPromptPlan({
    this.storeName = 'Trustlens Store',
    this.brandMark = 'TL',
    this.storeWord = 'store',
    this.titleLead = 'Why do I need\na ',
    this.titleHighlight = 'Proxy',
    this.titleTail = '?',
    this.noticeLead = 'An ',
    this.noticeHighlight = 'active proxy',
    this.noticeBody = ' is required to submit Reviews on Trustlens Store. Work done without a proxy ',
    this.noticeWarning = 'may be rejected.',
    this.price = '\$5',
    this.billingPeriod = '/mo.',
    this.requiredLabel = 'Required',
    this.purchaseLabel = 'PURCHASE PROXY',
    this.trustLabel = 'Secure & Reliable',
    this.footerBrand = 'Trustlens Store',
    this.reviewAction = false,
    this.reviewActionLabel = 'Submit review anyway',
    this.features = const <ProxyPromptFeature>[
      ProxyPromptFeature(label: '2 Location', icon: Icons.location_on_rounded),
      ProxyPromptFeature(label: '1GB Bandwidth', icon: Icons.speed_rounded),
      ProxyPromptFeature(label: 'Email Support', icon: Icons.email_rounded),
      ProxyPromptFeature(label: 'SSO+SCIM', icon: Icons.shield_outlined),
      ProxyPromptFeature(label: 'Dedicated IPS', icon: Icons.dns_rounded),
      ProxyPromptFeature(label: 'Encrypted Tunnel', icon: Icons.lock_rounded),
    ],
  });

  final String storeName;
  final String brandMark;
  final String storeWord;
  final String titleLead;
  final String titleHighlight;
  final String titleTail;
  final String noticeLead;
  final String noticeHighlight;
  final String noticeBody;
  final String noticeWarning;
  final String price;
  final String billingPeriod;
  final String requiredLabel;
  final String purchaseLabel;
  final String trustLabel;
  final String footerBrand;
  final List<ProxyPromptFeature> features;
  final String reviewActionLabel;
  final bool? reviewAction;
}

/// Tall proxy prompt matching the first reference image's vertical feature list.
///
/// Use [show] to present it as a modal, or place the widget in an existing
/// dialog route. The purchase callback runs after the prompt is dismissed by
/// default; set [closeOnPurchase] to false when the caller manages dismissal.
class ProxyRequirementDialog extends StatelessWidget {
  const ProxyRequirementDialog({
    super.key,
    required this.plan,
    required this.onPurchase,
    required this.onReview,
    this.closeOnPurchase = true,
  });

  final ProxyPromptPlan plan;
  final VoidCallback onPurchase;
  final VoidCallback onReview;
  final bool closeOnPurchase;

  static Future<T?> show<T>({
    required BuildContext context,
    required ProxyPromptPlan plan,
    required VoidCallback onPurchase,
    required VoidCallback onReview,
    bool barrierDismissible = true,
    bool closeOnPurchase = true,
  }) => showDialog<T>(
    context: context,
    barrierDismissible: barrierDismissible,
    builder: (_) => ProxyRequirementDialog(
      plan: plan,
      onPurchase: onPurchase,
      onReview: onReview,
      closeOnPurchase: closeOnPurchase,
    ),
  );

  @override
  Widget build(BuildContext context) => _PromptFrame(
    child: _TallProxyPrompt(
      plan: plan,
      onPurchase: onPurchase,
      onReview: onReview,
      closeOnPurchase: closeOnPurchase,
    ),
  );
}

/// Compact proxy prompt matching the second reference image's 3-column feature
/// grid and curved green footer.
class ProxyRequirementGridDialog extends StatelessWidget {
  const ProxyRequirementGridDialog({
    super.key,
    required this.plan,
    required this.onPurchase,
    required this.onReview,
    this.closeOnPurchase = true,
  });

  final ProxyPromptPlan plan;
  final VoidCallback onPurchase;
  final VoidCallback onReview;
  final bool closeOnPurchase;

  static Future<T?> show<T>({
    required BuildContext context,
    required ProxyPromptPlan plan,
    required VoidCallback onPurchase,
    required VoidCallback onReview,
    bool barrierDismissible = true,
    bool closeOnPurchase = true,
  }) => showDialog<T>(
    context: context,
    barrierDismissible: barrierDismissible,
    builder: (_) => ProxyRequirementGridDialog(
      plan: plan,
      onPurchase: onPurchase,
      onReview: onReview,
      closeOnPurchase: closeOnPurchase,
    ),
  );

  @override
  Widget build(BuildContext context) => _PromptFrame(
    child: _GridProxyPrompt(
      plan: plan,
      onPurchase: onPurchase,
      onReview: onReview,
      closeOnPurchase: closeOnPurchase,
    ),
  );
}

/// A focused proxy add-on prompt for the add-on purchase flow. It keeps the
/// details and actions from [ProxyRequirementDialog] while presenting them in
/// a compact, theme-aware layout.
class ProxyAddonsDialog extends StatelessWidget {
  const ProxyAddonsDialog({
    super.key,
    required this.plan,
    required this.onPurchase,
    required this.onReview,
    this.closeOnPurchase = true,
  });

  final ProxyPromptPlan plan;
  final VoidCallback onPurchase;
  final VoidCallback onReview;
  final bool closeOnPurchase;

  static Future<T?> show<T>({
    required BuildContext context,
    required ProxyPromptPlan plan,
    required VoidCallback onPurchase,
    required VoidCallback onReview,
    bool barrierDismissible = true,
    bool closeOnPurchase = true,
  }) => showDialog<T>(
    context: context,
    barrierDismissible: barrierDismissible,
    builder: (_) => ProxyAddonsDialog(
      plan: plan,
      onPurchase: onPurchase,
      onReview: onReview,
      closeOnPurchase: closeOnPurchase,
    ),
  );


  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final features = plan.features;

    return Dialog(
      backgroundColor: colors.surface,
      surfaceTintColor: colors.surfaceTint,
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 460,
          maxHeight: MediaQuery.sizeOf(context).height * .88,
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(22),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: colors.primaryContainer,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: Icon(
                      Icons.shield_outlined,
                      color: colors.onPrimaryContainer,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      plan.storeName,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              RichText(
                text: TextSpan(
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: colors.onSurface,
                    fontWeight: FontWeight.w900,
                    height: 1.12,
                  ),
                  children: [
                    TextSpan(text: plan.titleLead),
                    TextSpan(
                      text: plan.titleHighlight,
                      style: TextStyle(color: colors.primary),
                    ),
                    TextSpan(text: plan.titleTail),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: colors.primaryContainer.withValues(alpha: .48),
                  borderRadius: BorderRadius.circular(17),
                  border: Border.all(
                    color: colors.primary.withValues(alpha: .18),
                  ),
                ),
                child: RichText(
                  text: TextSpan(
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurface,
                      height: 1.45,
                    ),
                    children: [
                      TextSpan(text: plan.noticeLead),
                      TextSpan(
                        text: plan.noticeHighlight,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      TextSpan(text: plan.noticeBody),
                      TextSpan(
                        text: plan.noticeWarning,
                        style: TextStyle(
                          color: colors.error,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: colors.surfaceContainerLow,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: colors.outlineVariant),
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text.rich(
                            TextSpan(
                              style: theme.textTheme.headlineSmall?.copyWith(
                                color: colors.primary,
                                fontWeight: FontWeight.w900,
                              ),
                              children: [
                                TextSpan(text: plan.price),
                                TextSpan(
                                  text: plan.billingPeriod,
                                  style: theme.textTheme.titleSmall?.copyWith(
                                    color: colors.onSurfaceVariant,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: colors.secondaryContainer,
                            borderRadius: BorderRadius.circular(30),
                          ),
                          child: Text(
                            plan.requiredLabel,
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: colors.onSecondaryContainer,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (plan.reviewAction == true) ...[
                      const SizedBox(height: 12),
                      Divider(color: colors.outlineVariant),
                      const SizedBox(height: 10),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PulsingInfoButton(
                            color: colors.primary,
                            onPressed:  () => showProxyInformation(context),
                          ),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              'Please activate your proxy now to continue without interruptions.',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: colors.onSurfaceVariant,
                                height: 1.4,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ] else if (features.isNotEmpty) ...[
                      const SizedBox(height: 13),
                      Divider(color: colors.outlineVariant),
                      const SizedBox(height: 9),
                      for (final feature in features)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 5),
                          child: Row(
                            children: [
                              Icon(
                                feature.icon,
                                size: 19,
                                color: colors.primary,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  feature.label,
                                  style: theme.textTheme.bodyMedium,
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.verified_user_outlined,
                    size: 17,
                    color: colors.primary,
                  ),
                  const SizedBox(width: 7),
                  Text(
                    plan.trustLabel,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text('·', style: TextStyle(color: colors.onSurfaceVariant)),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      plan.footerBrand,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              if (plan.reviewAction == true) ...[
                const SizedBox(height: 13),
                OutlinedButton.icon(
                  onPressed: () =>
                      _purchase(context, onReview, closeOnPurchase),
                  icon: const Icon(Icons.rate_review_outlined),
                  label: Text(plan.reviewActionLabel),
                ),
              ],
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () =>
                    _purchase(context, onPurchase, closeOnPurchase),
                icon: const Icon(Icons.shopping_bag_outlined),
                label: Text(plan.purchaseLabel),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

abstract final class _ProxyColors {
  static const ink = Color(0xFF141922);
  static const muted = Color(0xFF667085);
  static const green = Color(0xFF078B4D);
  static const greenDark = Color(0xFF006B3D);
  static const greenLight = Color(0xFFEAF8EF);
  static const red = Color(0xFFBD3030);
  static const line = Color(0xFFE5E9EC);
  static const blue = Color(0xFFBBDDF7);
}

class _PromptFrame extends StatelessWidget {
  const _PromptFrame({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    return Dialog(
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      insetPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 18),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 520,
          maxHeight: size.height * .94,
        ),
        child: Material(
          color: const Color(0xFFFCFDFE),
          clipBehavior: Clip.antiAlias,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(26),
            side: const BorderSide(color: Color(0xFFDDE5E8)),
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(17, 15, 17, 14),
            child: child,
          ),
        ),
      ),
    );
  }
}

class _TallProxyPrompt extends StatelessWidget {
  const _TallProxyPrompt({
    required this.plan,
    required this.onPurchase,
    required this.onReview,
    required this.closeOnPurchase,
  });

  final ProxyPromptPlan plan;
  final VoidCallback onPurchase;
  final VoidCallback onReview;
  final bool closeOnPurchase;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      _LoadImage(imageLink: 'assets/cards/proxy.png'),
      const SizedBox(height: 13),
      _ProxyNotice(plan: plan, compact: true),
      const SizedBox(height: 12),
      _TallPriceFeatures(plan: plan),
      const SizedBox(height: 12),
      if (plan.reviewAction == true)
        _ReviewAction(
          label: plan.reviewActionLabel,
          onPressed: () => _purchase(context, onReview, closeOnPurchase),
        ),
      const SizedBox(height: 12),
      _PurchaseButton(
        label: plan.purchaseLabel,
        onPressed: () => _purchase(context, onPurchase, closeOnPurchase),
      ),
      const SizedBox(height: 12),
      _TallFooter(plan: plan),
    ],
  );
}

class _GridProxyPrompt extends StatelessWidget {
  const _GridProxyPrompt({
    required this.plan,
    required this.onPurchase,
    required this.closeOnPurchase,
    required this.onReview,
  });

  final ProxyPromptPlan plan;
  final VoidCallback onPurchase;
  final bool closeOnPurchase;
  final VoidCallback onReview;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      _LoadImage(imageLink: 'assets/cards/proxy.png'),
      const SizedBox(height: 13),
      _GridHero(plan: plan),
      const SizedBox(height: 13),
      _GridPrice(plan: plan),
      const SizedBox(height: 12),
      _FeatureGrid(features: plan.features),
      const SizedBox(height: 13),
      _PurchaseButton(
        label: plan.purchaseLabel,
        onPressed: () => _purchase(context, onPurchase, closeOnPurchase),
      ),
      const SizedBox(height: 6),
      _WaveFooter(label: plan.trustLabel),
    ],
  );
}

void _purchase(
  BuildContext context,
  VoidCallback onPurchase,
  bool closeOnPurchase,
) {
  if (closeOnPurchase) Navigator.of(context).pop();
  onPurchase();
}

class _LoadImage extends StatelessWidget {
  const _LoadImage({this.imageLink = 'assets/cards/benefits.png'});

  final String imageLink;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(0),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withOpacity(0.06),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Image.asset(
        imageLink,
        width: double.infinity,
        fit: BoxFit.contain,
        alignment: Alignment.center,
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader({required this.plan, required this.compact});

  final ProxyPromptPlan plan;
  final bool compact;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: compact ? 40 : 46,
        height: compact ? 38 : 42,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: const Color(0xFFD71931),
          borderRadius: BorderRadius.circular(compact ? 10 : 12),
          boxShadow: const [
            BoxShadow(
              color: Color(0x22252B33),
              blurRadius: 7,
              offset: Offset(0, 3),
            ),
          ],
        ),
        child: Text(
          plan.brandMark,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white,
            fontSize: compact ? 17 : 19,
            fontWeight: FontWeight.w900,
            fontStyle: FontStyle.italic,
            letterSpacing: -1,
          ),
        ),
      ),
      const SizedBox(width: 7),
      Text(
        plan.storeWord,
        style: TextStyle(
          color: _ProxyColors.greenDark,
          fontSize: compact ? 27 : 29,
          fontWeight: FontWeight.w900,
          letterSpacing: -1.2,
        ),
      ),
      if (!compact) ...[
        const SizedBox(width: 13),
        Container(width: 1.5, height: 34, color: const Color(0xFF87909B)),
        const SizedBox(width: 13),
        Expanded(
          child: Text(
            plan.storeName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _ProxyColors.muted,
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ] else
        const Spacer(),
    ],
  );
}

class _TallHero extends StatelessWidget {
  const _TallHero({required this.plan});

  final ProxyPromptPlan plan;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final artWidth = constraints.maxWidth < 360 ? 115.0 : 150.0;
      return SizedBox(
        height: 178,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(child: _ProxyHeadline(plan: plan, fontSize: 30)),
            SizedBox(
              width: artWidth,
              child: const _ProxyArtwork(compact: false, showBadge: false),
            ),
          ],
        ),
      );
    },
  );
}

class _GridHero extends StatelessWidget {
  const _GridHero({required this.plan});

  final ProxyPromptPlan plan;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final narrow = constraints.maxWidth < 330;
      final artworkWidth = narrow ? 132.0 : 174.0;
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: narrow ? 205.0 : 184.0,
            child: Row(
              children: [
                Expanded(child: _ProxyHeadline(plan: plan, fontSize: 30)),
                SizedBox(
                  width: artworkWidth,
                  child: const _ProxyArtwork(compact: true, showBadge: true),
                ),
              ],
            ),
          ),
          const SizedBox(height: 3),
          _ProxyNotice(plan: plan, compact: false),
        ],
      );
    },
  );
}

class _ProxyHeadline extends StatelessWidget {
  const _ProxyHeadline({required this.plan, required this.fontSize});

  final ProxyPromptPlan plan;
  final double fontSize;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      RichText(
        text: TextSpan(
          style: TextStyle(
            color: _ProxyColors.ink,
            fontSize: fontSize,
            height: 1.02,
            fontWeight: FontWeight.w900,
            letterSpacing: -1.5,
          ),
          children: [
            TextSpan(text: plan.titleLead),
            TextSpan(
              text: plan.titleHighlight,
              style: const TextStyle(color: _ProxyColors.green),
            ),
            TextSpan(text: plan.titleTail),
          ],
        ),
      ),
      const SizedBox(height: 10),
      Container(
        width: 72,
        height: 5,
        decoration: BoxDecoration(
          color: _ProxyColors.green,
          borderRadius: BorderRadius.circular(10),
        ),
      ),
    ],
  );
}

class _ProxyArtwork extends StatelessWidget {
  const _ProxyArtwork({required this.compact, required this.showBadge});

  final bool compact;
  final bool showBadge;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = constraints.maxWidth;
      final height = constraints.maxHeight;
      final globeSize = width * (compact ? .72 : .84);
      final shieldSize = width * (compact ? .70 : .78);
      return SizedBox(
        width: width,
        height: height,
        child: Stack(
          alignment: Alignment.center,
          clipBehavior: Clip.none,
          children: [
            Positioned(
              right: width * .04,
              top: height * .10,
              child: Container(
                width: globeSize,
                height: globeSize,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: [
                      _ProxyColors.blue.withValues(alpha: .76),
                      const Color(0xFFEAF7FF).withValues(alpha: .18),
                    ],
                  ),
                ),
                child: Icon(
                  Icons.public_rounded,
                  size: globeSize * .86,
                  color: const Color(0xFF8DC8E8).withValues(alpha: .66),
                ),
              ),
            ),
            if (compact)
              Positioned(
                left: 4,
                bottom: height * .02,
                child: Icon(
                  Icons.laptop_chromebook_rounded,
                  size: width * .70,
                  color: const Color(0xFF29313C),
                ),
              )
            else
              Positioned(
                right: 1,
                bottom: height * .03,
                child: Icon(
                  Icons.dns_rounded,
                  size: width * .43,
                  color: const Color(0xFF26323A),
                ),
              ),
            Positioned(
              left: width * .22,
              top: height * .19,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Icon(
                    Icons.shield_rounded,
                    size: shieldSize,
                    color: const Color(0xFF049958),
                  ),
                  Padding(
                    padding: EdgeInsets.only(bottom: 8),
                    child: Icon(
                      Icons.lock_rounded,
                      size: shieldSize * .35,
                      color: Color(0xFFF1FFF7),
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              right: width * .04,
              top: height * .12,
              child: const Icon(
                Icons.location_on_rounded,
                color: _ProxyColors.green,
                size: 31,
              ),
            ),
            if (showBadge)
              Positioned(
                left: 0,
                right: 0,
                bottom: height * .07,
                child: Center(
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 7,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(30),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x22000000),
                          blurRadius: 10,
                          offset: Offset(0, 3),
                        ),
                      ],
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.check_circle_rounded,
                          size: 20,
                          color: _ProxyColors.green,
                        ),
                        SizedBox(width: 6),
                        Text(
                          'Proxy Required',
                          style: TextStyle(
                            color: _ProxyColors.ink,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      );
    },
  );
}

class _ProxyNotice extends StatelessWidget {
  const _ProxyNotice({required this.plan, required this.compact});

  final ProxyPromptPlan plan;
  final bool compact;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.symmetric(
      horizontal: compact ? 13 : 14,
      vertical: compact ? 12 : 2,
    ),
    decoration: BoxDecoration(
      color: _ProxyColors.greenLight,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: const Color(0xFFE4F2E7)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: compact ? 36 : 28,
          height: compact ? 36 : 28,
          alignment: Alignment.center,
          decoration: const BoxDecoration(
            color: _ProxyColors.green,
            shape: BoxShape.circle,
          ),
          child: Icon(
            compact ? Icons.info_rounded : Icons.check_rounded,
            size: compact ? 21 : 19,
            color: Colors.white,
          ),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: RichText(
            text: TextSpan(
              style: const TextStyle(
                color: _ProxyColors.ink,
                fontSize: 15,
                height: 1.48,
              ),
              children: [
                TextSpan(text: plan.noticeLead),
                TextSpan(
                  text: plan.noticeHighlight,
                  style: const TextStyle(
                    color: _ProxyColors.greenDark,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                TextSpan(text: plan.noticeBody),
                TextSpan(
                  text: plan.noticeWarning,
                  style: const TextStyle(
                    color: _ProxyColors.red,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _TallPriceFeatures extends StatelessWidget {
  const _TallPriceFeatures({required this.plan});

  final ProxyPromptPlan plan;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.only(top: 13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      border: Border.all(color: _ProxyColors.line),
      boxShadow: const [
        BoxShadow(
          color: Color(0x0B17202C),
          blurRadius: 14,
          offset: Offset(0, 4),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: EdgeInsets.only(left: 17, right: 4),
          child: _PriceAndRequired(plan: plan, compact: false),
        ),
        const Padding(
          padding: EdgeInsets.only(top: 10, bottom: 8),
          child: Divider(height: 1, color: Color(0xFFEDF0F2)),
        ),
        LayoutBuilder(
          builder: (context, constraints) => SizedBox(
            height: plan.features.length > 4 ? 174.0 : 150.0,
            child: Stack(
              children: [
                Positioned.fill(
                  child: ClipRRect(
                    borderRadius: const BorderRadius.only(
                      bottomLeft: Radius.circular(24),
                      bottomRight: Radius.circular(24),
                    ),
                    child: Opacity(
                      opacity: 0.95,
                      child: Image.asset(
                        'assets/cards/listing.jpg',
                        fit: BoxFit.cover,
                        colorBlendMode: BlendMode.darken,
                      ),
                    ),
                  ),
                ),

                Padding(
                  padding: EdgeInsets.only(left: 17, right: 8, bottom: 15),
                  child: Row(
                    children: [
                      Expanded(
                        flex: constraints.maxWidth < 370 ? 7 : 6,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                          children: plan.features
                              .take(6)
                              .map((feature) => _CheckFeature(feature: feature))
                              .toList(),
                        ),
                      ),
                      const SizedBox(width: 2),
                      Expanded(
                        flex: constraints.maxWidth < 370 ? 4 : 5,
                        child: const _MapDecoration(),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _PriceAndRequired extends StatelessWidget {
  const _PriceAndRequired({required this.plan, required this.compact});

  final ProxyPromptPlan plan;
  final bool compact;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Flexible(
        flex: 6,
        child: FittedBox(
          alignment: Alignment.centerLeft,
          fit: BoxFit.scaleDown,
          child: RichText(
            text: TextSpan(
              style: TextStyle(
                color: _ProxyColors.green,
                fontSize: compact ? 66 : 58,
                height: 1,
                fontWeight: FontWeight.w900,
                letterSpacing: -2.2,
              ),
              children: [
                TextSpan(text: plan.price),
                TextSpan(
                  text: plan.billingPeriod,
                  style: TextStyle(
                    color: _ProxyColors.ink,
                    fontSize: compact ? 28 : 25,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -1,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      if (compact) ...[
        Container(height: 54, width: 1.5, color: const Color(0xFF8ACAA8)),
        const SizedBox(width: 13),
      ] else
        const Spacer(),
      Flexible(
        flex: 5,
        child: _RequiredPill(label: plan.requiredLabel, compact: compact),
      ),
    ],
  );
}

class _RequiredPill extends StatelessWidget {
  const _RequiredPill({required this.label, required this.compact});

  final String label;
  final bool compact;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.symmetric(
      horizontal: compact ? 13 : 14,
      vertical: compact ? 12 : 10,
    ),
    decoration: BoxDecoration(
      color: compact ? const Color(0xFFD7F3E3) : _ProxyColors.green,
      borderRadius: BorderRadius.circular(30),
    ),
    child: FittedBox(
      fit: BoxFit.scaleDown,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            compact ? Icons.check_circle_rounded : Icons.star_rounded,
            color: compact ? _ProxyColors.green : Colors.white,
            size: compact ? 23 : 20,
          ),
          const SizedBox(width: 7),
          Text(
            label,
            maxLines: 1,
            style: TextStyle(
              color: compact ? _ProxyColors.greenDark : Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: compact ? 15 : 14,
            ),
          ),
        ],
      ),
    ),
  );
}

class _CheckFeature extends StatelessWidget {
  const _CheckFeature({required this.feature});

  final ProxyPromptFeature feature;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const Icon(
        Icons.check_circle_rounded,
        size: 21,
        color: _ProxyColors.green,
      ),
      const SizedBox(width: 9),
      Expanded(
        child: Text(
          feature.label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: _ProxyColors.ink,
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
    ],
  );
}

class _MapDecoration extends StatelessWidget {
  const _MapDecoration();

  @override
  Widget build(BuildContext context) => Stack(
    alignment: Alignment.center,
    children: [
      Icon(
        Icons.public_rounded,
        size: 138,
        color: _ProxyColors.green.withValues(alpha: .10),
      ),
      Positioned(
        left: 4,
        bottom: 20,
        child: Icon(
          Icons.location_on_rounded,
          size: 30,
          color: _ProxyColors.green.withValues(alpha: .9),
        ),
      ),
      Positioned(
        right: 9,
        top: 22,
        child: Icon(
          Icons.location_on_rounded,
          size: 28,
          color: _ProxyColors.green.withValues(alpha: .9),
        ),
      ),
      CustomPaint(size: const Size(112, 62), painter: _RoutePainter()),
    ],
  );
}

class _RoutePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = _ProxyColors.green.withValues(alpha: .78)
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    final path = Path()
      ..moveTo(8, size.height - 8)
      ..cubicTo(
        size.width * .28,
        size.height * .05,
        size.width * .64,
        size.height * .98,
        size.width - 8,
        8,
      );
    for (final metric in path.computeMetrics()) {
      for (var distance = 0.0; distance < metric.length; distance += 7.0) {
        final end = distance + 4.0 < metric.length
            ? distance + 4.0
            : metric.length;
        canvas.drawPath(metric.extractPath(distance, end), paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _RoutePainter oldDelegate) => false;
}

class _GridPrice extends StatelessWidget {
  const _GridPrice({required this.plan});

  final ProxyPromptPlan plan;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    decoration: BoxDecoration(
      color: const Color(0xFFEAF9F1),
      borderRadius: BorderRadius.circular(21),
      border: Border.all(color: const Color(0xFF97D8B6), width: 1.5),
    ),
    child: _PriceAndRequired(plan: plan, compact: true),
  );
}

class _FeatureGrid extends StatelessWidget {
  const _FeatureGrid({required this.features});

  final List<ProxyPromptFeature> features;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final narrow = constraints.maxWidth < 390;
      final columns = narrow ? 2 : 3;
      return GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: features.length,
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: columns,
          crossAxisSpacing: 1,
          mainAxisSpacing: 1,
          childAspectRatio: narrow ? 1.32 : 1.55,
        ),
        itemBuilder: (context, index) =>
            _GridFeatureCell(feature: features[index], narrow: narrow),
      );
    },
  );
}

class _GridFeatureCell extends StatelessWidget {
  const _GridFeatureCell({required this.feature, required this.narrow});

  final ProxyPromptFeature feature;
  final bool narrow;

  @override
  Widget build(BuildContext context) {
    final icon = Container(
      width: 31,
      height: 31,
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        color: _ProxyColors.green,
        shape: BoxShape.circle,
      ),
      child: Icon(feature.icon, color: Colors.white, size: 17),
    );
    return Container(
      padding: EdgeInsets.symmetric(horizontal: narrow ? 5 : 8, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFF0F1F3)),
      ),
      child: narrow
          ? Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                icon,
                const SizedBox(height: 5),
                Text(
                  feature.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: _ProxyColors.ink,
                    fontSize: 12,
                    height: 1.12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            )
          : Row(
              children: [
                icon,
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    feature.label,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _ProxyColors.ink,
                      fontSize: 12,
                      height: 1.12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _ReviewAction extends StatelessWidget {
  const _ReviewAction({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const Spacer(),
      GestureDetector(
        onTap: onPressed,
        child: _RequiredPill(label: label, compact: true),
      ),
    ],
  );
}

class _PurchaseButton extends StatelessWidget {
  const _PurchaseButton({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    borderRadius: BorderRadius.circular(30),
    child: InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(30),
      child: Ink(
        height: 56,
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF0AA95E), Color(0xFF007A40)],
          ),
          borderRadius: BorderRadius.circular(30),
          boxShadow: const [
            BoxShadow(
              color: Color(0x26006B3D),
              blurRadius: 12,
              offset: Offset(0, 5),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.shopping_cart_rounded,
              color: Colors.white,
              size: 25,
            ),
            const SizedBox(width: 13),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                  letterSpacing: .4,
                ),
              ),
            ),
            const SizedBox(width: 12),
            const Icon(
              Icons.chevron_right_rounded,
              color: Colors.white,
              size: 30,
            ),
          ],
        ),
      ),
    ),
  );
}

class _TallFooter extends StatelessWidget {
  const _TallFooter({required this.plan});

  final ProxyPromptPlan plan;

  @override
  Widget build(BuildContext context) => Wrap(
    alignment: WrapAlignment.center,
    crossAxisAlignment: WrapCrossAlignment.center,
    spacing: 11,
    runSpacing: 5,
    children: [
      const Icon(
        Icons.verified_user_rounded,
        color: _ProxyColors.green,
        size: 22,
      ),
      Text(
        plan.trustLabel,
        style: const TextStyle(
          color: _ProxyColors.muted,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
      ),
      Container(width: 1, height: 19, color: const Color(0xFFADB4BA)),
      Text.rich(
        TextSpan(
          style: const TextStyle(color: _ProxyColors.muted, fontSize: 13),
          children: [
            const TextSpan(text: 'Powered by '),
            TextSpan(
              text: plan.footerBrand,
              style: const TextStyle(
                color: _ProxyColors.greenDark,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

class _WaveFooter extends StatelessWidget {
  const _WaveFooter({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 58,
    child: CustomPaint(
      painter: const _FooterWavePainter(),
      child: Center(
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.verified_user_rounded,
              color: _ProxyColors.green,
              size: 21,
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: const TextStyle(
                color: _ProxyColors.muted,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _FooterWavePainter extends CustomPainter {
  const _FooterWavePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final light = Paint()..color = const Color(0xFF75D9A7);
    final dark = Paint()..color = const Color(0xFF09A75C);
    final lightPath = Path()
      ..moveTo(0, size.height * .32)
      ..quadraticBezierTo(
        size.width * .5,
        size.height * .92,
        size.width,
        size.height * .25,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    final darkPath = Path()
      ..moveTo(0, size.height * .64)
      ..quadraticBezierTo(
        size.width * .5,
        size.height * 1.15,
        size.width,
        size.height * .55,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(lightPath, light);
    canvas.drawPath(darkPath, dark);
  }

  @override
  bool shouldRepaint(covariant _FooterWavePainter oldDelegate) => false;
}
