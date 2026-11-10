import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/currency/money.dart';
import '../../../services/auth_api.dart';

class SocialHandlesDialog extends StatefulWidget {
  const SocialHandlesDialog({
    super.key,
    required this.handles,
    required this.api,
    required this.token,
    this.onRewardClaimed,
  });

  final List<Map<String, dynamic>> handles;
  final AuthApi api;
  final String token;
  final VoidCallback? onRewardClaimed;

  static Future<void> showIfEligible({
    required BuildContext context,
    required List<Map<String, dynamic>> handles,
    required AuthApi api,
    required String token,
    VoidCallback? onRewardClaimed,
  }) async {
    if (handles.isEmpty ||
        !handles.any((handle) => handle['followed'] != true)) {
      return;
    }

    await showDialog<void>(
      context: context,
      barrierDismissible: true,
      builder: (_) => SocialHandlesDialog(
        handles: handles,
        api: api,
        token: token,
        onRewardClaimed: onRewardClaimed,
      ),
    );
  }

  @override
  State<SocialHandlesDialog> createState() => _SocialHandlesDialogState();
}

class _SocialHandlesDialogState extends State<SocialHandlesDialog> {
  late final List<Map<String, dynamic>> _remaining;
  final Set<int> _opened = {};
  final Set<int> _claiming = {};

  @override
  void initState() {
    super.initState();
    _remaining = widget.handles
        .where((handle) => handle['followed'] != true)
        .toList(growable: true);
  }

  int get _completed => widget.handles.length - _remaining.length;

  Future<void> _openChannel(Map<String, dynamic> handle) async {
    final id = (handle['id'] as num).toInt();
    final uri = Uri.tryParse(handle['channel']?.toString() ?? '');
    if (uri == null ||
        !uri.hasScheme ||
        !['http', 'https'].contains(uri.scheme)) {
      _showMessage('This social link is not available right now.');
      return;
    }

    try {
      final launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      if (!launched) {
        _showMessage('Could not open ${handle['name']}. Please try again.');
        return;
      }
      if (mounted) setState(() => _opened.add(id));
    } catch (_) {
      _showMessage('Could not open ${handle['name']}. Please try again.');
    }
  }

  Future<void> _claimFollow(Map<String, dynamic> handle) async {
    final id = (handle['id'] as num).toInt();
    setState(() => _claiming.add(id));
    try {
      final response = await widget.api.claimSocialFollow(widget.token, id);
      if (!mounted) return;
      setState(() {
        _claiming.remove(id);
        _remaining.removeWhere((item) => (item['id'] as num).toInt() == id);
      });
      widget.onRewardClaimed?.call();
      final alreadyCompleted =
          response['data'] is Map &&
          (response['data'] as Map)['already_completed'] == true;
      _showMessage(
        alreadyCompleted
            ? 'This social follow reward was already claimed.'
            : '${moneyConverted(widget.api, handle['reward_amount'], handle['currency']?.toString() ?? 'USD', handle['currency_minor_units'], widget.api.preferredCurrency, isMinor: false)} added to your bonus wallet.',
      );
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() => _claiming.remove(id));
      _showMessage(error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _claiming.remove(id));
      _showMessage('Could not record this follow. Please try again.');
    }
  }

  String _reward(Map<String, dynamic> handle) {
    final amount = (handle['reward_amount'] as num?)?.toDouble() ?? 0;
    final precision = (handle['currency_minor_units'] as num?)?.toInt() ?? 2;
    return moneyConverted(
      widget.api,
      amount,
      handle['currency']?.toString() ?? 'USD',
      precision,
      widget.api.preferredCurrency,
      isMinor: false,
    );
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final complete = _remaining.isEmpty;

    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 520,
          maxHeight: MediaQuery.sizeOf(context).height * .82,
        ),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  gradient: LinearGradient(
                    colors: [colors.primary, colors.tertiary],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: Column(
                  children: [
                    Icon(
                      complete
                          ? Icons.check_circle_rounded
                          : Icons.card_giftcard_rounded,
                      color: colors.onPrimary,
                      size: 38,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      complete ? 'All rewards claimed!' : 'Follow & earn',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: colors.onPrimary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      complete ? 'Thanks for following our official channels.' : 'Follow our official channels and claim a reward for each one.',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: colors.onPrimary.withValues(alpha: .85),
                      ),
                    ),
                    const SizedBox(height: 14),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(20),
                      child: LinearProgressIndicator(
                        value: widget.handles.isEmpty
                            ? 1
                            : _completed / widget.handles.length,
                        minHeight: 7,
                        backgroundColor: colors.onPrimary.withValues(
                          alpha: .22,
                        ),
                        color: colors.onPrimary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '$_completed of ${widget.handles.length} completed',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: colors.onPrimary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              if (complete) ...[
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Done'),
                ),
              ] else ...[
                const SizedBox(height: 14),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Choose a channel',
                    style: Theme.of(context).textTheme.titleSmall
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(height: 8),
                Flexible(
                  child: GridView.builder(
                    shrinkWrap: true,
                    itemCount: _remaining.length,
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 9,
                          mainAxisSpacing: 9,
                          mainAxisExtent: 160,
                        ),
                    itemBuilder: (context, index) =>
                        _socialCard(_remaining[index]),
                  ),
                ),
                const SizedBox(height: 7),
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Maybe later'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _socialCard(Map<String, dynamic> handle) {
    final theme = Theme.of(context);
    final id = (handle['id'] as num).toInt();
    final opened = _opened.contains(id);
    final claiming = _claiming.contains(id);
    final color = _socialColor(handle['key']?.toString() ?? '');

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 10, 10, 8),
        child: Column(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: color,
              child: Icon(
                _socialIcon(handle['key']?.toString() ?? ''),
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              handle['name']?.toString() ?? 'Social channel',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            Text(
              '+${handle['currency']} ${_reward(handle)}',
              style: theme.textTheme.labelSmall?.copyWith(
                color: theme.colorScheme.primary,
              ),
            ),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              height: 34,
              child: FilledButton.tonal(
                onPressed: claiming
                    ? null
                    : () =>
                          opened ? _claimFollow(handle) : _openChannel(handle),
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                ),
                child: claiming
                    ? const SizedBox.square(
                        dimension: 17,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(
                        opened ? 'I followed · Claim' : 'Open channel',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _socialColor(String key) => switch (key.toLowerCase()) {
    'facebook' => const Color(0xFF1877F2),
    'telegram' => const Color(0xFF2AABEE),
    'instagram' => const Color(0xFFC13584),
    'x' || 'twitter' => const Color(0xFF171717),
    'youtube' => const Color(0xFFFF0033),
    'tiktok' => const Color(0xFF111111),
    _ => Theme.of(context).colorScheme.primary,
  };

  IconData _socialIcon(String key) => switch (key.toLowerCase()) {
    'facebook' => Icons.facebook_rounded,
    'telegram' => Icons.send_rounded,
    'instagram' => Icons.camera_alt_rounded,
    'x' || 'twitter' => Icons.alternate_email_rounded,
    'youtube' => Icons.play_arrow_rounded,
    'tiktok' => Icons.music_note_rounded,
    _ => Icons.public_rounded,
  };
}
