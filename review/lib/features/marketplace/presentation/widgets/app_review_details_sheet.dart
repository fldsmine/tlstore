import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../../services/media_url.dart';

/// Reusable app details layout for both the daily-task sheet and listing detail page.
class AppReviewDetailsSheet extends StatelessWidget {
  const AppReviewDetailsSheet({
    super.key,
    required this.app,
    required this.screenshots,
    required this.onStartReview,
    this.onOpenStore,
    this.onViewAll,
    this.rewardText,
    this.startLabel = 'Start review',
    this.isStarting = false,
    this.showHeader = true,
    this.onReport,
  });

  final Map<String, dynamic> app;
  final List<String> screenshots;
  final VoidCallback onStartReview;
  final VoidCallback? onOpenStore;
  final VoidCallback? onViewAll;
  final String? rewardText;
  final String startLabel;
  final bool isStarting;
  final bool showHeader;
  final VoidCallback? onReport;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final storeLink = app['store_link']?.toString().trim() ?? '';
    final appStore = app['store']?.toString();

    final details = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (showHeader) ...[
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 2, 12, 12),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    'App details',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Close',
                  onPressed: () => Navigator.of(context).pop(false),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
        ],
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
            children: [
              Row(
                children: [
                  _AppDetailsIcon(
                    url: MediaUrl.resolve(app['app_icon']?.toString()),
                  ),
                  const SizedBox(width: 13),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          app['name']?.toString() ?? 'App review',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          [
                                app['developer']?.toString(),
                                app['store']?.toString(),
                              ]
                              .whereType<String>()
                              .where((value) => value.isNotEmpty)
                              .join(' · '),
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (rewardText != null) ...[
                    const SizedBox(width: 8),
                    Text(
                      rewardText!,
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: scheme.primary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ],
              ),
              if (screenshots.isNotEmpty) ...[
                const SizedBox(height: 22),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Screenshots',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (onViewAll != null && screenshots.length > 1)
                      TextButton.icon(
                        onPressed: onViewAll,
                        icon: const Icon(Icons.open_in_full_rounded, size: 17),
                        label: Text('View all (${screenshots.length})'),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                SizedBox(
                  height: 210,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: screenshots.length,
                    separatorBuilder: (_, _) => const SizedBox(width: 10),
                    itemBuilder: (context, index) => ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: AspectRatio(
                        aspectRatio: .72,
                        child: CachedNetworkImage(
                          imageUrl: screenshots[index],
                          fit: BoxFit.cover,
                          errorWidget: (_, _, _) => ColoredBox(
                            color: scheme.surfaceContainerHighest,
                            child: const Center(
                              child: Icon(Icons.broken_image_outlined),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 22),
              Text(
                'About this app',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                app['description']?.toString().trim().isNotEmpty == true
                    ? app['description'].toString()
                    : 'No app description is available.',
                style: theme.textTheme.bodyLarge?.copyWith(height: 1.5),
              ),
              if (storeLink.isNotEmpty) ...[
                const SizedBox(height: 20),
                OutlinedButton.icon(
                  onPressed: onOpenStore,
                  icon: const Icon(Icons.open_in_new_rounded),
                  label: Text('Download app on $appStore'),
                ),
              ],
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
            child: Row(
              children: [
                if (onReport != null) ...[
                  OutlinedButton.icon(
                    onPressed: onReport,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: scheme.error,
                      backgroundColor: scheme.error.withValues(alpha: .07),
                      side: BorderSide(
                        color: scheme.error.withValues(alpha: .35),
                      ),
                    ),
                    icon: const Icon(Icons.flag_outlined),
                    label: const Text('Report'),
                  ),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: FilledButton.icon(
                    onPressed: isStarting ? null : onStartReview,
                    icon: isStarting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.rate_review_outlined),
                    label: Text(isStarting ? 'Starting review…' : startLabel),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );

    return showHeader
        ? SizedBox(
            height: MediaQuery.sizeOf(context).height * .88,
            child: details,
          )
        : details;
  }
}

class AppScreenshotGalleryPage extends StatelessWidget {
  const AppScreenshotGalleryPage({
    super.key,
    required this.name,
    required this.screenshots,
  });

  final String name;
  final List<String> screenshots;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(name), centerTitle: false),
    body: PageView.builder(
      itemCount: screenshots.length,
      itemBuilder: (context, index) => Padding(
        padding: const EdgeInsets.all(16),
        child: InteractiveViewer(
          child: Center(
            child: CachedNetworkImage(
              imageUrl: screenshots[index],
              fit: BoxFit.contain,
              errorWidget: (_, _, _) =>
                  const Icon(Icons.broken_image_outlined, size: 48),
            ),
          ),
        ),
      ),
    ),
  );
}

class _AppDetailsIcon extends StatelessWidget {
  const _AppDetailsIcon({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(14),
    child: SizedBox(
      width: 62,
      height: 62,
      child: url == null
          ? ColoredBox(
              color: Theme.of(context).colorScheme.primaryContainer,
              child: Icon(
                Icons.apps_rounded,
                color: Theme.of(context).colorScheme.onPrimaryContainer,
              ),
            )
          : CachedNetworkImage(
              imageUrl: url!,
              fit: BoxFit.cover,
              errorWidget: (_, _, _) => ColoredBox(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Icon(
                  Icons.apps_rounded,
                  color: Theme.of(context).colorScheme.onPrimaryContainer,
                ),
              ),
            ),
    ),
  );
}
