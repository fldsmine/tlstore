import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../marketplace/presentation/widgets/listing_components.dart';
import '../../../../services/auth_api.dart';
import '../../../../widgets/auth_components.dart';
import '../../../../screens/history_screen.dart';

class AdEvidenceScreen extends StatefulWidget {
  const AdEvidenceScreen({
    super.key,
    required this.api,
    required this.token,
    required this.task,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> task;
  @override
  State<AdEvidenceScreen> createState() => _AdEvidenceScreenState();
}

class _AdEvidenceScreenState extends State<AdEvidenceScreen> {
  final _url = TextEditingController();
  String _channel = 'Instagram';
  PlatformFile? _proof;
  bool _sending = false;
  Map<String, dynamic> get _ad => widget.task['ad'] is Map<String, dynamic>
      ? widget.task['ad'] as Map<String, dynamic>
      : const {};

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  String get _shareText {
    final message =
        _ad['share_message']?.toString() ??
        _ad['description']?.toString() ??
        '';
    final destination = _ad['destination_url']?.toString() ?? '';
    return '$message${destination.isEmpty ? '' : '\n$destination'}'.trim();
  }

  Future<void> _pickProof() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.image,
        allowMultiple: false,
        withData: true,
      );
      if (!mounted) return;
      final file = result?.files.single;
      if (file == null) return;
      if (file.size > 10 * 1024 * 1024) {
        showAuthMessage(context, 'Choose a screenshot smaller than 10 MB.');
        return;
      }
      if (file.bytes == null) {
        showAuthMessage(
          context,
          'Could not access that image. Choose another screenshot.',
        );
        return;
      }
      setState(() => _proof = file);
    } on PlatformException {
      if (mounted) {
        showAuthMessage(
          context,
          'Could not open your photos. Please try again.',
        );
      }
    }
  }

  Future<void> _shareCampaign() async {
    if (_shareText.isEmpty) {
      showAuthMessage(
        context,
        'This campaign has no post text or destination link.',
      );
      return;
    }
    try {
      final box = context.findRenderObject() as RenderBox?;
      await SharePlus.instance.share(
        ShareParams(
          text: _shareText,
          title: _ad['title']?.toString() ?? 'Share campaign',
          sharePositionOrigin: box == null
              ? null
              : box.localToGlobal(Offset.zero) & box.size,
        ),
      );
    } catch (_) {
      if (mounted) {
        showAuthMessage(
          context,
          'Could not open the share sheet. You can copy the post text instead.',
        );
      }
    }
  }

  Future<void> _openDestination() async {
    final destination = Uri.tryParse(_ad['destination_url']?.toString() ?? '');
    if (destination == null ||
        !destination.hasScheme ||
        destination.host.isEmpty) {
      showAuthMessage(
        context,
        'This campaign does not have a valid destination link.',
      );
      return;
    }
    if (!await launchUrl(destination, mode: LaunchMode.externalApplication) &&
        mounted) {
      showAuthMessage(context, 'Could not open the campaign destination.');
    }
  }

  Future<void> _submit() async {
    final uri = Uri.tryParse(_url.text.trim());
    if (uri == null || !uri.hasScheme || !uri.host.contains('.')) {
      showAuthMessage(context, 'Enter the public post URL.');
      return;
    }
    setState(() => _sending = true);
    try {
      final path = 'ad-share-tasks/${widget.task['id']}/submit';
      if (_proof?.bytes != null) {
        await widget.api.postMultipart(
          path: path,
          token: widget.token,
          fields: {'channel': _channel, 'post_url': uri.toString()},
          fileField: 'screenshot',
          fileBytes: _proof!.bytes!,
          fileName: _proof!.name,
        );
      } else {
        await widget.api.post(path, widget.token, {
          'channel': _channel,
          'post_url': uri.toString(),
        });
      }
      if (!mounted) return;
      final navigator = Navigator.of(context);
      final messenger = ScaffoldMessenger.of(context);
      navigator.popUntil((route) => route.isFirst);
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Public share submitted. It is awaiting admin verification.',
          ),
        ),
      );
      await navigator.push(
        MaterialPageRoute<void>(
          builder: (_) => HistoryScreen(
            api: widget.api,
            token: widget.token,
            initialTab: 2,
          ),
        ),
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final message =
        _ad['share_message']?.toString() ??
        _ad['description']?.toString() ??
        '';
    final destination = _ad['destination_url']?.toString() ?? '';
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text(
          'Share campaign',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      ),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          DetailCard(
            children: [
              Text(
                _ad['title']?.toString() ?? 'Sponsored post',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: ink,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                style: const TextStyle(height: 1.45, color: mutedInk),
              ),
              if (destination.isNotEmpty) ...[
                const SizedBox(height: 10),
                SelectableText(
                  destination,
                  style: const TextStyle(color: brandGreen, fontSize: 12),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: _openDestination,
                  icon: const Icon(Icons.open_in_new_rounded),
                  label: const Text('Open campaign destination'),
                ),
              ],
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () async {
                  await Clipboard.setData(
                    ClipboardData(
                      text:
                          '$message${destination.isEmpty ? '' : '\n$destination'}',
                    ),
                  );
                  if (context.mounted) {
                    showAuthMessage(context, 'Campaign text copied.');
                  }
                },
                icon: const Icon(Icons.copy_rounded),
                label: const Text('Copy post text'),
              ),
              const SizedBox(height: 8),
              FilledButton.tonalIcon(
                onPressed: _shareCampaign,
                icon: const Icon(Icons.ios_share_rounded),
                label: const Text('Share post text'),
              ),
            ],
          ),
          const SizedBox(height: 15),
          DetailCard(
            children: [
              const Text(
                'Submit public post for verification',
                style: TextStyle(fontWeight: FontWeight.w800, color: ink),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _channel,
                decoration: const InputDecoration(labelText: 'Social channel'),
                items:
                    const [
                          'Instagram',
                          'Facebook',
                          'TikTok',
                          'X',
                          'YouTube',
                          'Other',
                        ]
                        .map(
                          (name) =>
                              DropdownMenuItem(value: name, child: Text(name)),
                        )
                        .toList(),
                onChanged: (value) =>
                    setState(() => _channel = value ?? 'Instagram'),
              ),
              const SizedBox(height: 13),
              TextField(
                controller: _url,
                keyboardType: TextInputType.url,
                decoration: const InputDecoration(
                  labelText: 'Public post URL',
                  hintText: 'https://…',
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Post the campaign publicly first. Your submission will be checked by an admin.',
                style: TextStyle(color: mutedInk, fontSize: 11, height: 1.4),
              ),
              const SizedBox(height: 13),
              OutlinedButton.icon(
                onPressed: _sending ? null : _pickProof,
                icon: const Icon(Icons.add_photo_alternate_outlined),
                label: Text(
                  _proof == null
                      ? 'Add post screenshot (optional)'
                      : 'Change screenshot',
                ),
              ),
              if (_proof != null) ...[
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(
                      Icons.image_outlined,
                      size: 17,
                      color: brandGreen,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        _proof!.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: mutedInk, fontSize: 11),
                      ),
                    ),
                    IconButton(
                      onPressed: () => setState(() => _proof = null),
                      icon: const Icon(Icons.close_rounded),
                      tooltip: 'Remove screenshot',
                    ),
                  ],
                ),
              ],
              if (widget.task['expires_at'] != null) ...[
                const SizedBox(height: 10),
                Text(
                  'Submit before ${formatExpiry(widget.task['expires_at'].toString())}.',
                  style: const TextStyle(color: mutedInk, fontSize: 11),
                ),
              ],
            ],
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _sending ? null : _submit,
            style: FilledButton.styleFrom(
              backgroundColor: brandGreen,
              minimumSize: const Size.fromHeight(52),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            child: _sending
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Submit share'),
          ),
        ],
      ),
    );
  }
}
