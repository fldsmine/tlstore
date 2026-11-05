import 'package:flutter/material.dart';

import 'upgrade_screen.dart';
import 'update_service.dart';

class UpdateGate extends StatelessWidget {
  const UpdateGate({super.key, required this.child, required this.controller});
  final Widget child;
  final UpdateController controller;

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: controller,
    builder: (context, _) => Stack(
      children: [
        child,
        if (controller.mustUpdate && controller.info != null)
          Positioned.fill(
            child: UpgradeScreen(
              info: controller.info!,
              controller: controller,
            ),
          ),
        if (controller.showPrompt && controller.info != null)
          Positioned(
            left: 12,
            right: 12,
            bottom: 12,
            child: SafeArea(
              child: _UpdateCard(
                info: controller.info!,
                controller: controller,
              ),
            ),
          ),
      ],
    ),
  );
}

class _UpdateCard extends StatelessWidget {
  const _UpdateCard({required this.info, required this.controller});
  final UpdateInfo info;
  final UpdateController controller;
  @override
  Widget build(BuildContext context) => Card(
    elevation: 8,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(15, 13, 8, 13),
      child: Row(
        children: [
          Icon(
            Icons.system_update_rounded,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  info.title?.trim().isNotEmpty == true
                      ? info.title!
                      : 'New update available',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  'Version ${info.versionName} • build ${info.buildNumber}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) =>
                    UpgradeScreen(info: info, controller: controller),
              ),
            ),
            child: const Text('View'),
          ),
          IconButton(
            onPressed: controller.later,
            icon: const Icon(Icons.close_rounded),
          ),
        ],
      ),
    ),
  );
}
