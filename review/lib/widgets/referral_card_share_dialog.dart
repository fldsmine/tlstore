import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:share_plus/share_plus.dart';

import 'shareable_referral_card.dart';

class ReferralCardShareDialog extends StatefulWidget {
  const ReferralCardShareDialog({
    super.key,
    required this.name,
    required this.username,
    required this.referralCode,
    required this.referralLink,
    required this.backgroundAsset,
  });

  final String name;
  final String username;
  final String referralCode;
  final String referralLink;
  final String backgroundAsset;

  static Future<void> show(
    BuildContext context, {
    required String name,
    required String username,
    required String referralCode,
    required String referralLink,
    required String backgroundAsset,
  }) => showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: .76),
    builder: (_) => ReferralCardShareDialog(
      name: name,
      username: username,
      referralCode: referralCode,
      referralLink: referralLink,
      backgroundAsset: backgroundAsset,
    ),
  );

  @override
  State<ReferralCardShareDialog> createState() =>
      _ReferralCardShareDialogState();
}

class _ReferralCardShareDialogState extends State<ReferralCardShareDialog> {
  final _cardKey = GlobalKey();
  bool _sharing = false;
  String? _error;

  Future<void> _share() async {
    setState(() {
      _sharing = true;
      _error = null;
    });
    try {
      await precacheImage(AssetImage(widget.backgroundAsset), context);
      await precacheImage(const AssetImage('assets/logo.jpg'), context);
      await WidgetsBinding.instance.endOfFrame;
      final boundary = _cardKey.currentContext?.findRenderObject();
      if (boundary is! RenderRepaintBoundary) {
        throw StateError('The promotional card is not ready to share.');
      }
      final image = await boundary.toImage(pixelRatio: 3);
      try {
        final data = await image.toByteData(format: ui.ImageByteFormat.png);
        if (data == null) throw StateError('Could not render the share image.');
        final bytes = data.buffer.asUint8List();
        await SharePlus.instance.share(
          ShareParams(
            subject: 'Join me on TL Store',
            text:
                'Join me on TL Store, use my referral code ${widget.referralCode}: ${widget.referralLink}',
            files: [
              XFile.fromData(
                bytes,
                mimeType: 'image/png',
                name:
                    'tl-store-referral-${widget.referralCode.toLowerCase()}.png',
              ),
            ],
            downloadFallbackEnabled: true,
          ),
        );
      } finally {
        image.dispose();
      }
      if (mounted) Navigator.of(context).pop();
    } catch (_) {
      if (mounted) {
        setState(() {
          _sharing = false;
          _error = 'Could not create the share card. Please try again.';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.sizeOf(context);
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 22),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 440,
          maxHeight: media.height * .9,
        ),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            borderRadius: BorderRadius.circular(24),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Share your referral card',
                      style: Theme.of(context).textTheme.titleLarge
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: _sharing ? null : () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: RepaintBoundary(
                    key: _cardKey,
                    child: ShareableReferralCard(
                      name: widget.name,
                      username: widget.username,
                      referralCode: widget.referralCode,
                      referralLink: widget.referralLink,
                      backgroundAsset: widget.backgroundAsset,
                    ),
                  ),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _sharing ? null : () => Navigator.pop(context),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _sharing ? null : _share,
                      icon: _sharing
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.ios_share_rounded),
                      label: Text(_sharing ? 'Preparing…' : 'Share card'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
