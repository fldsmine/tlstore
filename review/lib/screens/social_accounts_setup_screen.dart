import 'dart:async';

import 'package:flutter/material.dart';

import '../services/auth_api.dart';
import '../widgets/auth_components.dart';
import '../core/utils/helper.dart';

class SocialAccountsSetupScreen extends StatefulWidget {
  const SocialAccountsSetupScreen({
    super.key,
    required this.api,
    required this.token,
    this.focusPlatform,
  });

  final AuthApi api;
  final String token;
  final String? focusPlatform;

  @override
  State<SocialAccountsSetupScreen> createState() =>
      _SocialAccountsSetupScreenState();
}

class _SocialAccountsSetupScreenState extends State<SocialAccountsSetupScreen> {
  static const _platforms = <(String, String, String)>[
    ('whatsapp', 'WhatsApp', 'Phone number or profile link'),
    ('facebook', 'Facebook', 'Username or profile link'),
    ('instagram', 'Instagram', 'Username or profile link'),
    ('x', 'X (Twitter)', 'Username or profile link'),
    ('tiktok', 'TikTok', 'Username or profile link'),
    ('youtube', 'YouTube', 'Username or channel link'),
    ('telegram', 'Telegram', 'Username or profile link'),
  ];

  final Map<String, TextEditingController> _controllers = {};
  final Map<String, FocusNode> _focusNodes = {};

  Map<String, String> _accounts = {};
  bool _loading = true;
  bool _savingAll = false;
  bool _savedSuccessfully = false;
  int _returnSeconds = 0;
  Timer? _returnTimer;

  List<(String, String, String)> get _orderedPlatforms {
    final focus = widget.focusPlatform?.toLowerCase();

    if (focus == null || focus.isEmpty) return _platforms;

    return [
      ..._platforms,
    ]..sort((a, b) => (a.$1 == focus ? 0 : 1).compareTo(b.$1 == focus ? 0 : 1));
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _returnTimer?.cancel();
    for (final controller in _controllers.values) {
      controller.dispose();
    }

    for (final node in _focusNodes.values) {
      node.dispose();
    }

    super.dispose();
  }

  void _returnToParent() {
    _returnTimer?.cancel();
    if (mounted && Navigator.of(context).canPop()) {
      Navigator.of(context).pop(true);
    }
  }

  void _startReturnTimer() {
    _returnTimer?.cancel();
    _returnTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_returnSeconds <= 1) {
        timer.cancel();
        _returnToParent();
        return;
      }
      setState(() => _returnSeconds--);
    });
  }

  TextEditingController _controllerFor(String platform) {
    return _controllers.putIfAbsent(platform, TextEditingController.new);
  }

  FocusNode _focusNodeFor(String platform) {
    return _focusNodes.putIfAbsent(platform, FocusNode.new);
  }

  void _focusPlatform(String platform) {
    if ((_accounts[platform] ?? '').isNotEmpty || _savingAll) return;

    final node = _focusNodeFor(platform);
    node.requestFocus();
  }

  Future<void> _load() async {
    try {
      final accounts = await widget.api.socialAccounts(widget.token);

      if (!mounted) return;

      setState(() {
        // AuthApi may return an unmodifiable response map. Keep a mutable
        // screen-owned copy because successful saves update this map locally.
        _accounts = Map<String, String>.of(accounts);
        _loading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;

      setState(() => _loading = false);
      showAuthMessage(context, error.message);
    }
  }

  Map<String, String> get _enteredAccounts => Map.fromEntries(
    _controllers.entries
        .map((entry) => MapEntry(entry.key, entry.value.text.trim()))
        .where(
          (entry) =>
              entry.value.isNotEmpty && (_accounts[entry.key] ?? '').isEmpty,
        ),
  );

  Future<void> _saveAll() async {
    final pending = _enteredAccounts;

    if (pending.isEmpty) {
      showAuthMessage(
        context,
        'Enter at least one social account before saving.',
      );
      return;
    }

    setState(() => _savingAll = true);

    final failed = <String>[];

    for (final entry in pending.entries) {
      try {
        final response = await widget.api.setSocialAccount(
          widget.token,
          entry.key,
          entry.value,
        );

        final data = response['data'];

        if (!mounted) return;

        setState(() {
          _accounts[entry.key] = data is Map
              ? (data['handle']?.toString() ?? entry.value)
              : entry.value;
        });
      } on AuthException {
        failed.add(_label(entry.key));
      }
    }

    if (!mounted) return;

    if (failed.isEmpty) {
      setState(() {
        _savingAll = false;
        _savedSuccessfully = true;
        _returnSeconds = 2;
      });
      _startReturnTimer();
    } else {
      setState(() => _savingAll = false);
      showAuthMessage(
        context,
        'Some accounts could not be saved: '
        '${failed.join(', ')}. You can retry the remaining entries.',
      );
    }
  }

  String _label(String platform) {
    return _platforms.firstWhere((row) => row.$1 == platform).$2;
  }

  Widget _buildPlatformIcon(String platform) {
    final brandColor = platformColor(platform);

    return Container(
      width: 58,
      height: 58,
      decoration: BoxDecoration(
        color: brandColor.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(15),
      ),
      alignment: Alignment.center,
      child: Icon(platformIcon(platform), color: brandColor, size: 30),
    );
  }

  Widget _buildAccountCard((String, String, String) platform) {
    final colors = Theme.of(context).colorScheme;
    final key = platform.$1;
    final savedHandle = _accounts[key] ?? '';
    final isSaved = savedHandle.isNotEmpty;

    final controller = _controllerFor(key);
    final focusNode = _focusNodeFor(key);

    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 13),
      color: colors.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(
          color: focusNode.hasFocus
              ? colors.primary.withValues(alpha: 0.65)
              : colors.outlineVariant.withValues(alpha: 0.55),
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: isSaved ? null : () => _focusPlatform(key),
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Tapping the icon also focuses the handle field.
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: isSaved ? null : () => _focusPlatform(key),
                child: _buildPlatformIcon(key),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${platform.$2} ${platform.$1 == 'whatsapp' ? 'phone number' : 'username or link'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall
                                ?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: colors.onSurfaceVariant.withValues(
                                    alpha: 0.75,
                                  ),
                                ),
                          ),
                        ),
                        if (isSaved)
                          Icon(
                            Icons.lock_rounded,
                            size: 17,
                            color: colors.onSurfaceVariant,
                          ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    if (isSaved)
                      SelectableText(
                        savedHandle,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      )
                    else
                      TextField(
                        controller: controller,
                        focusNode: focusNode,
                        onTap: () => _focusPlatform(key),
                        onChanged: (_) => setState(() {}),
                        enabled: !_savingAll,
                        cursorColor: colors.primary,
                        style: Theme.of(context).textTheme.bodyMedium,
                        decoration: InputDecoration(
                          hintText: platform.$1 == 'whatsapp'
                              ? '08012345678'
                              : platform.$1 == 'facebook'
                              ? 'yourname'
                              : '@yourname',
                          hintStyle: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(color: colors.onSurfaceVariant),

                          // Keep the input visually transparent.
                          // It blends into the account card background.
                          filled: false,
                          fillColor: Colors.transparent,
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          disabledBorder: InputBorder.none,
                          isDense: true,
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                  ],
                ),
              ),
              if (!isSaved) ...[
                const SizedBox(width: 8),
                Icon(
                  Icons.edit_outlined,
                  size: 17,
                  color: colors.onSurfaceVariant,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSaveButton() {
    final colors = Theme.of(context).colorScheme;
    if (_savedSuccessfully) {
      return SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 16),
          decoration: BoxDecoration(
            color: colors.surface,
            border: Border(
              top: BorderSide(
                color: colors.outlineVariant.withValues(alpha: 0.45),
              ),
            ),
          ),
          child: SizedBox(
            width: double.infinity,
            height: 54,
            child: FilledButton.icon(
              onPressed: _returnToParent,
              icon: const Icon(Icons.check_circle_outline_rounded),
              label: Text(
                'Saved · returning in ${_returnSeconds}s',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ),
      );
    }
    final canSave = !_savingAll && _enteredAccounts.isNotEmpty;

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 16),
        decoration: BoxDecoration(
          color: colors.surface,
          border: Border(
            top: BorderSide(
              color: colors.outlineVariant.withValues(alpha: 0.45),
            ),
          ),
        ),
        child: SizedBox(
          width: double.infinity,
          height: 54,
          child: FilledButton(
            onPressed: canSave ? _saveAll : null,
            style: FilledButton.styleFrom(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            child: _savingAll
                ? const SizedBox(
                    width: 21,
                    height: 21,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.lock_open_rounded, size: 19),
                      const SizedBox(width: 9),
                      const Text(
                        'Save Accounts',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(width: 8),
                      if (canSave)
                        Text(
                          '(${_enteredAccounts.length})',
                          style: TextStyle(
                            color: colors.onPrimary.withValues(alpha: 0.8),
                          ),
                        ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Social accounts'),
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    keyboardDismissBehavior:
                        ScrollViewKeyboardDismissBehavior.onDrag,
                    padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                    children: [
                      Text(
                        'Connect your accounts',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        'Add the social accounts you use for tasks. Each account can be saved once and is then locked.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 22),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Your platforms',
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                          ),
                          Text(
                            '${_accounts.values.where((value) => value.isNotEmpty).length}/${_platforms.length} saved',
                            style: Theme.of(context).textTheme.labelMedium
                                ?.copyWith(
                                  color: colors.onSurfaceVariant,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 13),
                      for (final platform in _orderedPlatforms)
                        _buildAccountCard(platform),
                    ],
                  ),
                ),
                _buildSaveButton(),
              ],
            ),
    );
  }
}
