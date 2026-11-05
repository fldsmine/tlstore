import 'package:flutter/material.dart';

import 'update_service.dart';

class UpgradeScreen extends StatelessWidget {
  const UpgradeScreen({
    super.key,
    required this.info,
    required this.controller,
  });

  final UpdateInfo info;
  final UpdateController controller;

  Future<void> _openUpdate(BuildContext context) async {
    final opened = await controller.openDownload();
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: const Row(
            children: [
              Icon(Icons.error_outline_rounded, color: Colors.white),
              SizedBox(width: 12),
              Expanded(
                child: Text('The update link is unavailable right now.'),
              ),
            ],
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final isForced = info.force;

    return PopScope(
      canPop: !isForced,
      child: Scaffold(
        backgroundColor: colors.surface,
        body: Stack(
          children: [
            // Decorative background radial gradient accent
            Positioned(
              top: -100,
              right: -100,
              child: Container(
                width: 320,
                height: 320,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: colors.primary.withValues(alpha: 0.12),
                ),
              ),
            ),
            SafeArea(
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 480),
                  child: Column(
                    children: [
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 24,
                            vertical: 16,
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              const SizedBox(height: 16),
                              
                              // Hero Icon / Badge
                              _buildHeroHeader(colors, isForced),
                              
                              const SizedBox(height: 24),

                              // Header Title & Subtitle
                              Text(
                                info.title?.trim().isNotEmpty == true
                                    ? info.title!
                                    : (isForced
                                        ? 'Update Required'
                                        : 'New Update Available'),
                                textAlign: TextAlign.center,
                                style: theme.textTheme.headlineMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                  color: colors.onSurface,
                                  letterSpacing: -0.5,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                isForced
                                    ? 'To continue using TrustLens, please update to the latest build to access critical updates.'
                                    : 'A new version of TrustLens is available. Upgrade now for the latest features and stability fixes.',
                                textAlign: TextAlign.center,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: colors.onSurfaceVariant,
                                  height: 1.5,
                                ),
                              ),

                              const SizedBox(height: 28),

                              // Version Specs Card
                              _buildVersionCard(theme, colors),

                              const SizedBox(height: 20),

                              // Release Notes / What's New Section
                              if (info.notes?.trim().isNotEmpty == true)
                                _buildReleaseNotesCard(theme, colors),

                              // Secondary Release History
                              if (info.changes.length > 1) ...[
                                const SizedBox(height: 16),
                                _buildChangelogHistory(theme, colors),
                              ],

                              const SizedBox(height: 24),
                            ],
                          ),
                        ),
                      ),

                      // Bottom Sticky Action Section
                      _buildBottomActions(context, theme, colors, isForced),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Visual Header Badge Icon
  Widget _buildHeroHeader(ColorScheme colors, bool isForced) {
    return Stack(
      alignment: Alignment.center,
      children: [
        Container(
          width: 104,
          height: 104,
          decoration: BoxDecoration(
            color: isForced
                ? colors.errorContainer.withValues(alpha: 0.4)
                : colors.primaryContainer.withValues(alpha: 0.5),
            shape: BoxShape.circle,
          ),
        ),
        Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            color: isForced ? colors.errorContainer : colors.primaryContainer,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: (isForced ? colors.error : colors.primary)
                    .withValues(alpha: 0.25),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Icon(
            isForced
                ? Icons.warning_amber_rounded
                : Icons.system_update_alt_rounded,
            size: 40,
            color: isForced
                ? colors.onErrorContainer
                : colors.onPrimaryContainer,
          ),
        ),
      ],
    );
  }

  /// Version Info Specs Box
  Widget _buildVersionCard(ThemeData theme, ColorScheme colors) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _buildInfoTile(
            theme,
            colors,
            label: 'Version',
            value: info.versionName.isNotEmpty ? info.versionName : 'Latest',
            icon: Icons.new_releases_outlined,
          ),
          Container(
            height: 32,
            width: 1,
            color: colors.outlineVariant.withValues(alpha: 0.6),
          ),
          _buildInfoTile(
            theme,
            colors,
            label: 'Build',
            value: '#${info.buildNumber}',
            icon: Icons.build_circle_outlined,
          ),
          if (info.publishedAt != null) ...[
            Container(
              height: 32,
              width: 1,
              color: colors.outlineVariant.withValues(alpha: 0.6),
            ),
            _buildInfoTile(
              theme,
              colors,
              label: 'Released',
              value: info.publishedAt!.split('T').first,
              icon: Icons.calendar_today_outlined,
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildInfoTile(
    ThemeData theme,
    ColorScheme colors, {
    required String label,
    required String value,
    required IconData icon,
  }) {
    return Column(
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: colors.primary),
            const SizedBox(width: 4),
            Text(
              label,
              style: theme.textTheme.labelSmall?.copyWith(
                color: colors.onSurfaceVariant,
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.bold,
            color: colors.onSurface,
          ),
        ),
      ],
    );
  }

  /// Release Notes Card
  Widget _buildReleaseNotesCard(ThemeData theme, ColorScheme colors) {
    final rawNotes = info.notes?.trim() ?? '';
    final notesList = rawNotes
        .split('\n')
        .where((line) => line.trim().isNotEmpty)
        .toList();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.4),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.auto_awesome_rounded,
                  size: 20, color: colors.primary),
              const SizedBox(width: 8),
              Text(
                'What’s New',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: colors.onSurface,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 12),
          ...notesList.map(
            (note) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '• ',
                    style: TextStyle(
                      color: colors.primary,
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                  Expanded(
                    child: Text(
                      note,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                        height: 1.45,
                      ),
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

  /// Older Changelog Entries
  Widget _buildChangelogHistory(ThemeData theme, ColorScheme colors) {
    return ExpansionTile(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      collapsedShape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      backgroundColor: colors.surfaceContainerLow,
      collapsedBackgroundColor: colors.surfaceContainerLow,
      leading: Icon(Icons.history_rounded, color: colors.onSurfaceVariant),
      title: Text(
        'Previous Versions Changelog',
        style: theme.textTheme.titleSmall?.copyWith(
          fontWeight: FontWeight.w600,
          color: colors.onSurface,
        ),
      ),
      childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: info.changes.skip(1).take(5).map((change) {
        return Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'v${change['version_name'] ?? ''}: ',
                style: theme.textTheme.bodySmall?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: colors.primary,
                ),
              ),
              Expanded(
                child: Text(
                  change['release_notes'] ?? '',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  /// Dynamic Bottom Actions Bar
  Widget _buildBottomActions(
    BuildContext context,
    ThemeData theme,
    ColorScheme colors,
    bool isForced,
  ) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: colors.surface,
        border: Border(
          top: BorderSide(
            color: colors.outlineVariant.withValues(alpha: 0.3),
          ),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Primary Download Action Button
          SizedBox(
            width: double.infinity,
            height: 52,
            child: FilledButton.icon(
              onPressed: info.hasDownloadUrl ? () => _openUpdate(context) : null,
              style: FilledButton.styleFrom(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                elevation: 0,
              ),
              icon: const Icon(Icons.download_rounded),
              label: Text(
                info.hasDownloadUrl ? 'Update Now' : 'Link Unavailable',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Secondary Action Handlers
          if (isForced) ...[
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton(
                onPressed: controller.checking ? null : () => controller.check(),
                style: OutlinedButton.styleFrom(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: controller.checking
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('I’ve already updated — Check again'),
              ),
            ),
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: TextButton(
                    onPressed: controller.later,
                    child: const Text('Remind me later'),
                  ),
                ),
                Expanded(
                  child: TextButton(
                    onPressed: controller.skipThisVersion,
                    child: Text(
                      'Skip version',
                      style: TextStyle(color: colors.error),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}