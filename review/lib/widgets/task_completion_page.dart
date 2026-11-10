import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../core/links/referral_link.dart';
import '../services/auth_api.dart';

/// Shared celebration and referral prompt shown after a task is submitted.
class TaskCompletionPage extends StatefulWidget {
  const TaskCompletionPage({
    super.key,
    required this.api,
    required this.token,
    required this.user,
    required this.taskLabel,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;
  final String taskLabel;

  @override
  State<TaskCompletionPage> createState() => _TaskCompletionPageState();
}

class _TaskCompletionPageState extends State<TaskCompletionPage> {
  String? _referralLink;
  bool _loadingLink = true;

  @override
  void initState() {
    super.initState();
    _loadReferralLink();
  }

  Future<void> _loadReferralLink() async {
    try {
      final response = await widget.api.get('me/referral-code', widget.token);
      final data = response['data'];
      final code = data is Map ? data['code']?.toString().trim() : null;
      if (code != null && code.isNotEmpty && mounted) {
        setState(() => _referralLink = ReferralLink.forCode(code).toString());
      }
    } on AuthException {
      // Keep the result screen available if the optional referral request fails.
    } catch (_) {
      // Referral sharing is optional; never block the task completion screen.
    } finally {
      if (mounted) setState(() => _loadingLink = false);
    }
  }

  Future<void> _copyLink() async {
    final link = _referralLink;
    if (link == null) return;
    await Clipboard.setData(ClipboardData(text: link));
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Referral link copied.')),
      );
    }
  }

  Future<void> _shareLink() async {
    final link = _referralLink;
    if (link == null) return;
    await SharePlus.instance.share(
      ShareParams(
        text: 'Join me on TL Store and earn by completing tasks: $link',
        subject: 'Join TL Store',
      ),
    );
  }

  void _closeToHome() {
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final fullName = widget.user['full_name']?.toString().trim() ?? '';
    final firstName = fullName.isEmpty
        ? (widget.user['username']?.toString().trim().isNotEmpty == true
            ? widget.user['username'].toString().trim()
            : 'there')
        : fullName.split(RegExp(r'\s+')).first;

    return Scaffold(
      backgroundColor: colors.surfaceContainerLow,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 18),
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: IconButton.filledTonal(
                      tooltip: 'Close and return to home',
                      onPressed: _closeToHome,
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(
                        flex: 4,
                        child: Container(
                          height: 150,
                          decoration: BoxDecoration(
                            color: colors.primaryContainer,
                            borderRadius: BorderRadius.circular(28),
                          ),
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              Icon(Icons.park_rounded,
                                  size: 98, color: colors.onPrimaryContainer),
                              Positioned(
                                right: 17,
                                bottom: 18,
                                child: CircleAvatar(
                                  radius: 21,
                                  backgroundColor: colors.tertiaryContainer,
                                  child: Icon(Icons.check_rounded,
                                      color: colors.onTertiaryContainer),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 18),
                      Expanded(
                        flex: 5,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Nice work, $firstName!',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 7),
                            Text(
                              'Your ${widget.taskLabel.toLowerCase()} has been submitted.',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: colors.onSurfaceVariant,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 26),
                  Text(
                    'What’s next?',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    'Our team will review your submission. Your reward will be added after it is approved.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 22),
                  Card(
                    margin: EdgeInsets.zero,
                    color: colors.surface,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(22),
                      side: BorderSide(color: colors.outlineVariant),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 54,
                                height: 54,
                                decoration: BoxDecoration(
                                  color: colors.secondaryContainer,
                                  borderRadius: BorderRadius.circular(16),
                                ),
                                child: Icon(Icons.people_alt_rounded,
                                    color: colors.onSecondaryContainer,
                                    size: 29),
                              ),
                              const SizedBox(width: 13),
                              Expanded(
                                child: Text(
                                  'Earn extra by inviting friends',
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Invite friends to TL Store with your personal link. You can earn referral rewards when they subscribe or complete eligible tasks.',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: colors.onSurfaceVariant,
                              height: 1.45,
                            ),
                          ),
                          const SizedBox(height: 17),
                          Text('Share this link',
                              style: theme.textTheme.labelLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                              )),
                          const SizedBox(height: 8),
                          if (_loadingLink)
                            const LinearProgressIndicator()
                          else if (_referralLink != null)
                            Container(
                              padding: const EdgeInsets.fromLTRB(12, 9, 5, 9),
                              decoration: BoxDecoration(
                                color: colors.surfaceContainerHighest,
                                borderRadius: BorderRadius.circular(13),
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: SelectableText(
                                      _referralLink!,
                                      maxLines: 2,
                                      style: theme.textTheme.bodySmall?.copyWith(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  IconButton.filledTonal(
                                    tooltip: 'Copy referral link',
                                    onPressed: _copyLink,
                                    icon: const Icon(Icons.copy_rounded),
                                  ),
                                ],
                              ),
                            )
                          else
                            Text(
                              'Your referral link is temporarily unavailable. You can find it later on the Refer & earn page.',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: colors.onSurfaceVariant,
                              ),
                            ),
                          if (_referralLink != null) ...[
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton.icon(
                                onPressed: _shareLink,
                                icon: const Icon(Icons.share_rounded),
                                label: const Text('Share with friends'),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 14),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _closeToHome,
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(54),
                  ),
                  child: const Text('Continue to Home'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
