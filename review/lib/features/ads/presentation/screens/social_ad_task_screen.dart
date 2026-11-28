import 'package:cached_network_image/cached_network_image.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/currency/money.dart';
import '../../../../screens/social_accounts_setup_screen.dart';
import '../../../../services/auth_api.dart';
import '../../../../services/media_url.dart';
import '../../../../widgets/auth_components.dart';
import '../../../../widgets/task_completion_page.dart';

class SocialAdTaskScreen extends StatefulWidget {
  const SocialAdTaskScreen({
    super.key,
    required this.api,
    required this.token,
    required this.task,
    this.user = const {},
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> task;
  final Map<String, dynamic> user;

  @override
  State<SocialAdTaskScreen> createState() => _SocialAdTaskScreenState();
}

class _SocialAdTaskScreenState extends State<SocialAdTaskScreen> {
  final _postUrl = TextEditingController();
  PlatformFile? _proof;
  String? _handle;
  bool _loadingHandle = true;
  bool _opening = false;
  bool _submitting = false;

  Map<String, dynamic> get _ad => widget.task['ad'] is Map
      ? Map<String, dynamic>.from(widget.task['ad'] as Map)
      : const {};

  String get _platform => _normalize(_ad['type']?.toString() ?? 'others');
  String get _platformName => switch (_platform) {
    'x' => 'X (Twitter)',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'whatsapp' => 'WhatsApp',
    'others' => 'social',
    _ => '${_platform[0].toUpperCase()}${_platform.substring(1)}',
  };

  String get _title => _ad['title']?.toString() ?? 'Social task';
  String get _reward => moneyConverted(
    widget.api,
    _ad['share_reward'],
    _ad['currency']?.toString() ?? 'USD',
    _ad['currency_minor_units'],
    widget.api.preferredCurrency,
    isMinor: false,
  );

  @override
  void initState() {
    super.initState();
    _postUrl.text = _ad['destination_url']?.toString() ?? '';
    _loadHandle();
  }

  @override
  void dispose() {
    _postUrl.dispose();
    super.dispose();
  }

  Future<void> _loadHandle() async {
    try {
      final accounts = await widget.api.socialAccounts(widget.token);
      if (!mounted) return;
      setState(() {
        _handle = accounts[_platform];
        _loadingHandle = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingHandle = false);
    }
  }

  Future<void> _openTaskLink() async {
    final uri = Uri.tryParse(_ad['destination_url']?.toString() ?? '');
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      showAuthMessage(
        context,
        'This task does not have a working destination link.',
      );
      return;
    }
    setState(() => _opening = true);
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && mounted)
        showAuthMessage(context, 'Could not open the task link.');
    } catch (_) {
      if (mounted) showAuthMessage(context, 'Could not open the task link.');
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  Future<void> _pickScreenshot() async {
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
          'Could not read that image. Choose another screenshot.',
        );
        return;
      }
      setState(() => _proof = file);
    } on PlatformException {
      if (mounted)
        showAuthMessage(
          context,
          'Could not open your photos. Please try again.',
        );
    }
  }

  Future<void> _submit() async {
    final uri = Uri.tryParse(_postUrl.text.trim());
    if (_proof?.bytes == null) {
      showAuthMessage(context, 'Add a screenshot of your completed task.');
      return;
    }
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      showAuthMessage(context, 'Enter the public post or task URL as proof.');
      return;
    }
    setState(() => _submitting = true);
    try {
      await widget.api.postMultipart(
        path: 'ad-share-tasks/${widget.task['id']}/submit',
        token: widget.token,
        fields: {'channel': _platformName, 'post_url': uri.toString()},
        fileField: 'screenshot',
        fileBytes: _proof!.bytes!,
        fileName: _proof!.name,
      );
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => TaskCompletionPage(
            api: widget.api,
            token: widget.token,
            user: widget.user,
            taskLabel: 'Ad share',
          ),
        ),
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final image = MediaUrl.resolve(_ad['creative_path']?.toString());
    final description = _ad['description']?.toString().trim() ?? '';
    return Scaffold(
      appBar: AppBar(title: const Text('Task details')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Container(
                    width: 54,
                    height: 54,
                    clipBehavior: Clip.antiAlias,
                    decoration: BoxDecoration(
                      color: colors.primaryContainer,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: image == null
                        ? Icon(
                            _icon(_platform),
                            color: colors.onPrimaryContainer,
                          )
                        : CachedNetworkImage(
                            imageUrl: image,
                            fit: BoxFit.cover,
                            errorWidget: (_, _, _) => Icon(
                              _icon(_platform),
                              color: colors.onPrimaryContainer,
                            ),
                          ),
                  ),
                  const SizedBox(width: 13),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _platformName,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          _title,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    _reward,
                    style: TextStyle(
                      color: colors.primary,
                      fontWeight: FontWeight.w900,
                      fontSize: 15,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            color: colors.surfaceContainerLow,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'How to do this',
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _step(
                    '1',
                    description.isEmpty
                        ? 'Complete the task described by the sponsor.'
                        : description,
                  ),
                  _step(
                    '2',
                    'Tap Proceed to task to open the $_platformName link.',
                  ),
                  _step(
                    '3',
                    'Complete the requested action on $_platformName.',
                  ),
                  _step(
                    '4',
                    'Take a screenshot, return here, and send it as proof.',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (_loadingHandle)
            const LinearProgressIndicator()
          else if ((_handle ?? '').trim().isEmpty)
            Card(
              color: colors.tertiaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(15),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Your $_platformName account is not set',
                      style: TextStyle(
                        color: colors.onTertiaryContainer,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      'Save this account once to continue. Saved social accounts cannot be edited.',
                      style: TextStyle(color: colors.onTertiaryContainer),
                    ),
                    TextButton.icon(
                      onPressed: _setSocialAccount,
                      icon: const Icon(Icons.add_link_rounded),
                      label: const Text('Set account'),
                    ),
                  ],
                ),
              ),
            )
          else
            ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 4),
              leading: Icon(_icon(_platform), color: colors.primary),
              title: Text('Using $_platformName account'),
              subtitle: Text(
                _handle!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          const SizedBox(height: 8),
          SizedBox(
            height: 52,
            child: FilledButton.icon(
              onPressed: _opening || _loadingHandle || (_handle ?? '').isEmpty
                  ? null
                  : _openTaskLink,
              icon: _opening
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.open_in_new_rounded),
              label: const Text('Proceed to task'),
            ),
          ),
          const SizedBox(height: 5),
          Text(
            'Opens ${_ad['destination_url'] ?? 'the task link'}. When finished, come back here to send your proof.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 18),
          TextField(
            controller: _postUrl,
            keyboardType: TextInputType.url,
            decoration: const InputDecoration(
              labelText: 'Post or task link',
              hintText: 'https://…',
              prefixIcon: Icon(Icons.link_rounded),
            ),
          ),
          const SizedBox(height: 11),
          InkWell(
            onTap: _submitting ? null : _pickScreenshot,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              height: 150,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: colors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: colors.outlineVariant,
                  style: BorderStyle.solid,
                ),
              ),
              child: _proof?.bytes != null
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(15),
                      child: Image.memory(
                        _proof!.bytes!,
                        width: double.infinity,
                        height: 150,
                        fit: BoxFit.cover,
                      ),
                    )
                  : Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.cloud_upload_outlined,
                          color: colors.primary,
                          size: 30,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Tap to add your screenshot',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'PNG, JPG or WebP · up to 10 MB',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
          if (_proof != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                _proof!.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall,
              ),
            ),
          const SizedBox(height: 14),
          SizedBox(
            height: 52,
            child: FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const CircularProgressIndicator()
                  : const Text('Send proof'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _setSocialAccount() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => SocialAccountsSetupScreen(api: widget.api, token: widget.token, focusPlatform: _platform)));
    if (mounted) await _loadHandle();
  }

  Widget _step(String number, String text) => Padding(
    padding: const EdgeInsets.only(bottom: 9),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '$number. ',
          style: TextStyle(
            color: Theme.of(context).colorScheme.primary,
            fontWeight: FontWeight.w900,
          ),
        ),
        Expanded(
          child: Text(
            text,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );

  String _normalize(String value) {
    final type = value.toLowerCase().trim();
    if (type.contains('twitter') || type == 'x') return 'x';
    for (final platform in const [
      'telegram',
      'facebook',
      'youtube',
      'instagram',
      'tiktok',
      'whatsapp',
    ]) {
      if (type.contains(platform)) return platform;
    }
    return 'others';
  }

  IconData _icon(String platform) => switch (platform) {
    'telegram' => Icons.send_rounded,
    'facebook' => Icons.facebook_rounded,
    'youtube' => Icons.smart_display_rounded,
    'instagram' => Icons.camera_alt_outlined,
    'tiktok' => Icons.music_video_rounded,
    'whatsapp' => Icons.chat_rounded,
    'x' => Icons.alternate_email_rounded,
    _ => Icons.public_rounded,
  };
}
