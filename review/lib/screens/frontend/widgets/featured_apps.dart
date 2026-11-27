import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:review/services/media_url.dart';

import 'auto_slide_view.dart';

class FeaturedApps extends StatelessWidget {
  final List<Map<String, dynamic>> apps;

  const FeaturedApps({super.key, required this.apps});

  @override
  Widget build(BuildContext context) {
    if (apps.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionHeading(
          context,
          'Discover',
          'Featured apps',
          description: 'Explore apps that are currently featured on TLStore.',
        ),
        AutoSlideView(
          height: 216,
          interval: const Duration(seconds: 4),
          viewportFraction: .88,
          padding: const EdgeInsets.symmetric(horizontal: 6),
          itemCount: apps.length,
          itemBuilder: (context, index) {
            final app = apps[index];
            final image = _image(app['cover_image']);
            return Card(
              margin: EdgeInsets.zero,
              clipBehavior: Clip.antiAlias,
              elevation: 0,
              color: colors.surfaceContainerLow,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(22),
                side: BorderSide(color: colors.outlineVariant),
              ),
              child: InkWell(
                onTap: () {},
                child: Padding(
                  padding: const EdgeInsets.all(15),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          _mediaThumb(context, image, Icons.apps_rounded),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _text(app['name'], 'Featured app'),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _text(app['title'], 'App store'),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        color: colors.onSurfaceVariant,
                                      ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Expanded(
                        child: Text(
                          _text(
                            app['description'],
                            'Tap to learn more about this featured app.',
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(height: 1.4),
                        ),
                      ),
                      Row(
                        children: [
                          Icon(
                            Icons.star_rounded,
                            size: 17,
                            color: colors.tertiary,
                          ),
                          const SizedBox(width: 3),
                          Text(_text(app['rating'], 'New')),
                          const Spacer(),
                          TextButton.icon(
                            onPressed: () {},
                            icon: const Icon(Icons.arrow_forward_rounded),
                            label: const Text('Details'),
                          ),
                        ],
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

  Widget _mediaThumb(BuildContext context, String url, IconData fallback) {
    final colors = Theme.of(context).colorScheme;
    return ClipRRect(
      borderRadius: BorderRadius.circular(15),
      child: SizedBox(
        width: 58,
        height: 58,
        child: url.isEmpty
            ? ColoredBox(
                color: colors.primaryContainer,
                child: Icon(fallback, color: colors.onPrimaryContainer),
              )
            : CachedNetworkImage(
                imageUrl: url,
                fit: BoxFit.cover,
                errorWidget: (context, url, error) => ColoredBox(
                  color: colors.primaryContainer,
                  child: Icon(fallback, color: colors.onPrimaryContainer),
                ),
              ),
      ),
    );
  }
}
