import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:review/services/auth_api.dart';
import 'package:review/services/media_url.dart';
import '../../../../core/currency/money.dart';
import 'auto_marquee.dart';

class AdShare extends StatelessWidget {

  final List<Map<String, dynamic>> ads;
  final AuthApi api;

  /// Called when a visitor taps a campaign card; guests are offered
  /// registration/log-in by the landing page.
  final ValueChanged<Map<String, dynamic>>? onOpenAd;

  const AdShare({
    super.key, 
    required this.ads, 
    required this.api, 
    this.onOpenAd,
  });

  @override
  Widget build(BuildContext context) {

    if (ads.isEmpty) return const SizedBox.shrink();
    final colors = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionHeading(
          context,
          'Sponsored sharing',
          'Featured tasks',
          description: 'See a sample of the public sharing opportunities.',
        ),
        AutoMarquee(
          itemCount: ads.length,
          height: 226,
          speed: 30,
          itemSpacing: 12,
          pauseOnTouch: true,
          padding: const EdgeInsets.symmetric(horizontal: 18),

          itemBuilder: (context, index) {
            final ad = ads[index];
            final image = _image(ad['creative_path']);
            final currency = _text(ad['currency'], 'USD');
            final price =
                double.tryParse(ad['price']?.toString() ?? '') ?? 0;

            return SizedBox(
              width: 285,
              child: Card(
                margin: EdgeInsets.zero,
                clipBehavior: Clip.antiAlias,
                elevation: 0,
                color: colors.surfaceContainerLow,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(22),
                  side: BorderSide(color: colors.outlineVariant),
                ),
                child: InkWell(
                  onTap: onOpenAd == null ? null : () => onOpenAd!(ad),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        height: 124,
                        width: double.infinity,
                        child: image.isEmpty
                            ? ColoredBox(
                                color: colors.secondaryContainer,
                                child: Icon(
                                  Icons.campaign_rounded,
                                  size: 38,
                                  color: colors.onSecondaryContainer,
                                ),
                              )
                            : CachedNetworkImage(
                                imageUrl: image,
                                fit: BoxFit.cover,
                                errorWidget: (context, url, error) =>
                                    ColoredBox(
                                  color: colors.secondaryContainer,
                                  child: Icon(
                                    Icons.campaign_rounded,
                                    color: colors.onSecondaryContainer,
                                  ),
                                ),
                              ),
                      ),

                      Padding(
                        padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _text(ad['title'], 'Sponsored task'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                  ),
                            ),

                            const SizedBox(height: 4),

                            Text(
                              _text(
                                ad['description'],
                                'Share this campaign publicly.',
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(
                                    color: colors.onSurfaceVariant,
                                  ),
                            ),

                            const SizedBox(height: 6),

                            Row(
                              children: [
                                Text(
                                  formatMajorMoney(price, currency),
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleSmall
                                      ?.copyWith(
                                        color: colors.primary,
                                        fontWeight: FontWeight.w900,
                                      ),
                                ),
                                const Spacer(),
                                Icon(
                                  Icons.open_in_new_rounded,
                                  size: 17,
                                  color: colors.primary,
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
        const SizedBox(height: 18),
      ],
    );

  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }
  String _image(Object? value) => MediaUrl.resolve(value?.toString()) ?? '';

  Widget _sectionHeading(
    BuildContext context,
    String eyebrow,
    String title, {
    String? description,
    Widget? trailing,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  eyebrow.toUpperCase(),
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.primary,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  title,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.5,
                  ),
                ),
                if (description != null) ...[
                  const SizedBox(height: 5),
                  Text(
                    description,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      height: 1.4,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) trailing,
        ],
      ),
    );
  }

}
