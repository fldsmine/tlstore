import 'package:flutter/material.dart';

import '../../../../services/media_url.dart';
import '../../../../core/utils/helper.dart';

/// Compact store-style card for an app review campaign.
class AppListingCard extends StatelessWidget {
  const AppListingCard({
    super.key,
    required this.item,
    required this.reward,
    required this.onTap,
    this.expanded = false,
  });

  final Map<String, dynamic> item;
  final String reward;
  final VoidCallback? onTap;
  final bool expanded;

  Map<String, dynamic> get _app => item['app'] is Map<String, dynamic>
      ? item['app'] as Map<String, dynamic>
      : const {};

  List<String> get _screenshots {
    final values = _app['screenshots'];
    if (values is! List) return const [];
    return values
        .whereType<Map>()
        .map((value) => MediaUrl.resolve(value['image_url']?.toString()))
        .whereType<String>()
        .take(4)
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final app = _app;
    final title = app['name']?.toString() ?? 'App review';
    final developer = app['developer']?.toString() ?? 'App developer';
    final category = app['category']?.toString() ?? app['store']?.toString();
    final description =
        app['description']?.toString() ??
        'Try the app and share an honest review.';
    final screenshots = _screenshots;
    final status = item['task_access_status']?.toString();
    final location = item['location']?.toString();
    final iconUrl = MediaUrl.resolve(app['app_icon']?.toString());
    final radius = BorderRadius.circular(16);
	final storeInfo = getStoreInfo(app['store']?.toString());

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: scheme.outlineVariant),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  _AppIcon(url: iconUrl),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          category == null || category.isEmpty
                              ? developer
                              : '$developer · $category',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (item['featured'] == 1 || item['featured'] == true)
                    _Pill(
                      icon: Icons.star_rounded,
                      label: 'Featured',
                      color: scheme.tertiary,
                    ),
                ],
              ),
              const SizedBox(height: 11),
              if (screenshots.isNotEmpty) ...[
                _ScreenshotStrip(urls: screenshots),
                const SizedBox(height: 14),
              ],
              Text(
                description,
                maxLines: expanded ? 5 : 2,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  _StoreDiv(
                    icon: storeInfo.icon,
                    label: storeInfo.name,
          					color: storeInfo.color,
                  ),
                  if (location != null && location.isNotEmpty)
                    _Pill(
                      icon: Icons.location_on_outlined,
                      label: location,
                      color: scheme.secondary,
                    ),
                  if (app['rating'] != null)
                    _Pill(
                      icon: Icons.star_rounded,
                      label: '${app['rating']}',
                      color: scheme.tertiary,
                    ),
                  _Pill(
                    icon: Icons.payments_outlined,
                    label: reward,
                    color: scheme.primary,
                  ),
                ],
              ),
              if (!expanded) ...[
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: onTap,
                    child: Text('View Review & Earn'),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ScreenshotStrip extends StatelessWidget {
  const _ScreenshotStrip({required this.urls});
  final List<String> urls;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 142,
    child: Row(
      children: [
        for (var index = 0; index < urls.length; index++) ...[
          if (index > 0) const SizedBox(width: 8),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: Image.network(
                urls[index],
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) =>
                    const _ImagePlaceholder(icon: Icons.phone_android_rounded),
              ),
            ),
          ),
        ],
      ],
    ),
  );
}

class _AppIcon extends StatelessWidget {
  const _AppIcon({required this.url});
  final String? url;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(11),
    child: SizedBox(
      width: 46,
      height: 46,
      child: url == null
          ? const _ImagePlaceholder(icon: Icons.apps_rounded)
          : Image.network(
              url!,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) =>
                  const _ImagePlaceholder(icon: Icons.apps_rounded),
            ),
    ),
  );
}

class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder({required this.icon});
  final IconData icon;
  @override
  Widget build(BuildContext context) => ColoredBox(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    child: Icon(icon, color: Theme.of(context).colorScheme.primary),
  );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.icon, required this.label, required this.color});
  final IconData icon;
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: scheme.onSurface,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreDiv extends StatelessWidget {
  const _StoreDiv({
    required this.icon,
    required this.label,
    required this.color,
  });

  final String icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: 9,
        vertical: 6,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: color.withValues(alpha: .20),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset(
            'assets/others/$icon.png',
            width: 18,
            height: 18,
            fit: BoxFit.contain,
          ),
          const SizedBox(width: 4),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: scheme.onSurface,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

