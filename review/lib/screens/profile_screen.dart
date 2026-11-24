import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:review/core/links/referral_link.dart';
import 'package:share_plus/share_plus.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../services/media_url.dart';
import '../widgets/auth_components.dart';
import '../widgets/withdrawal_pin_sheet.dart';
import 'referral_team_screen.dart';
import 'settings_screen.dart';
import 'subscriptions_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
    required this.onLogout,
    required this.onDeleted,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;
  final Future<void> Function() onLogout;
  final VoidCallback onDeleted;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _name = TextEditingController();
  final _username = TextEditingController();
  final _phone = TextEditingController();
  final _currency = TextEditingController();
  final _email = TextEditingController();
  final _country = TextEditingController();

  bool _editing = false;
  bool _saving = false;
  bool _mediaBusy = false;
  bool _securityBusy = false;
  String referralCode = '';

  Map<String, dynamic> get _user => widget.user;

  @override
  void initState() {
    super.initState();
    _fillControllers();
    _refreshProfile();
  }

  void _fillControllers() {
    _name.text = _user['full_name']?.toString() ?? '';
    _username.text = _user['username']?.toString() ?? '';
    _phone.text = _user['phone']?.toString() ?? '';
    _currency.text = _user['preferred_currency']?.toString() ?? '';
    _email.text = _user['email']?.toString() ?? '';
    final countryName = _user['country_name']?.toString();
    final countryCode = _user['country_code']?.toString() ?? '';
    _country.text = countryName == null || countryName.isEmpty
        ? (countryCode.isEmpty ? 'Not set' : countryCode)
        : '$countryName ($countryCode)';
    referralCode = _user['referral_code']?.toString() ?? '';
  }

  Future<void> _refreshProfile() async {
    try {
      final response = await widget.api.get('me', widget.token);
      final data = response['data'];
      if (data is Map<String, dynamic> && mounted) {
        setState(() {
          _user.addAll(data);
          _fillControllers();
        });
      }
    } on AuthException {
      // Keep the session profile visible when the device is offline.
    }
  }

  Future<void> _openMyTeam() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ReferralTeamScreen(
          api: widget.api,
          token: widget.token,
          referralCode: referralCode,
          referralLink: ReferralLink.referralWebLink(referralCode).toString(),
        ),
      ),
    );
    if (mounted) {
      await _refreshProfile();
    }
  }

  Future<void> _copyReferralLink() async {
    final link = ReferralLink.referralWebLink(referralCode);
    await Clipboard.setData(ClipboardData(text: link));
    if (mounted) showAuthMessage(context, 'Referral link copied.');
  }

  Future<void> _shareReferralLink() async {
    await SharePlus.instance.share(
      ShareParams(text: ReferralLink.shareMessage(referralCode), subject: 'Join Trust Lens Store'),
    );
  }

  Future<void> _openSubscriptions() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => SubscriptionsScreen(
          api: widget.api,
          token: widget.token,
          user: _user,
        ),
      ),
    );
    if (mounted) {
      await _refreshProfile();
    }
  }

  Future<void> _openSettings() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => SettingsScreen(
          api: widget.api,
          token: widget.token,
          user: _user,
          onLogout: widget.onLogout,
          onDeleted: widget.onDeleted,
        ),
      ),
    );
  }

  @override
  void dispose() {
    _name.dispose();
    _username.dispose();
    _phone.dispose();
    _currency.dispose();
    _email.dispose();
    _country.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final response = await widget.api.patch('me', widget.token, {
        'full_name': _name.text.trim(),
        'username': _username.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        if (_currency.text.trim().isNotEmpty)
          'preferred_currency': _currency.text.trim().toUpperCase(),
      });
      if (response['data'] is Map<String, dynamic>) {
        _user.addAll(response['data'] as Map<String, dynamic>);
      }
      if (mounted) {
        setState(() => _editing = false);
        _fillControllers();
        showAuthMessage(context, 'Profile updated.');
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickImage(String type) async {
    try {
      final picked = await FilePicker.platform.pickFiles(
        type: FileType.image,
        withData: true,
      );
      if (picked == null || picked.files.isEmpty) return;
      final image = picked.files.first;
      if (image.size > 5 * 1024 * 1024) {
        if (mounted)
          showAuthMessage(context, 'Choose an image smaller than 5 MB.');
        return;
      }
      setState(() => _mediaBusy = true);
      final response = await widget.api.uploadProfileImage(
        widget.token,
        type,
        image,
      );
      if (response['data'] is Map<String, dynamic>) {
        _user.addAll(response['data'] as Map<String, dynamic>);
      }
      if (mounted) {
        setState(() {});
        showAuthMessage(
          context,
          '${type == 'avatar' ? 'Profile photo' : 'Cover image'} updated.',
        );
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _mediaBusy = false);
    }
  }

  Future<void> _removeImage(String type) async {
    try {
      final response = await widget.api.delete('me/media/$type', widget.token);
      if (response['data'] is Map<String, dynamic>) {
        _user.addAll(response['data'] as Map<String, dynamic>);
      }
      if (mounted) setState(() {});
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    }
  }

  Future<void> _changePassword() async {
    final changed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) =>
          _ChangePasswordDialog(api: widget.api, token: widget.token),
    );
    if (changed == true && mounted) {
      showAuthMessage(context, 'Password updated.');
    }
  }

  Future<void> _manageSecurityPin() async {
    if (_securityBusy) return;
    final pinIsSet = _user['security_pin_set'] == true;
    setState(() => _securityBusy = true);
    try {
      final saved = await showWithdrawalPinSheet(
        context,
        api: widget.api,
        token: widget.token,
        endpoint: 'me/security-pin',
        requireCurrentPin: pinIsSet,
        requireAccountPassword: !pinIsSet && _user['has_password'] == true,
        title: pinIsSet
            ? 'Choose a new withdrawal PIN'
            : 'Create a withdrawal PIN',
        onSuccess: (response) {
          final data = response['data'];
          if (mounted && data is Map) {
            setState(() => _user.addAll(Map<String, dynamic>.from(data)));
          }
        },
      );
      if (saved && mounted) {
        showAuthMessage(context, pinIsSet ? 'PIN updated.' : 'PIN saved.');
      }
    } finally {
      if (mounted) setState(() => _securityBusy = false);
    }
  }

  Future<void> _removeSecurityPin() async {
    final pin = TextEditingController();
    final code = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('Remove withdrawal PIN'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Enter your current PIN to turn off withdrawal verification.',
            ),
            const SizedBox(height: 12),
            _pinField(pin, 'Current 4-digit PIN'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, pin.text),
            child: const Text('Remove PIN'),
          ),
        ],
      ),
    );
    if (code == null) {
      pin.dispose();
      return;
    }
    try {
      final response = await widget.api.delete(
        'me/security-pin',
        widget.token,
        body: {'current_pin': code},
      );
      if (response['data'] is Map<String, dynamic> && mounted) {
        setState(() => _user.addAll(response['data'] as Map<String, dynamic>));
        showAuthMessage(context, 'Withdrawal PIN removed.');
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      pin.dispose();
    }
  }

  Widget _pinField(
    TextEditingController controller,
    String label, {
    String? Function(String?)? validator,
  }) => TextFormField(
    controller: controller,
    obscureText: true,
    keyboardType: TextInputType.number,
    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
    maxLength: 4,
    textAlign: TextAlign.center,
    decoration: InputDecoration(labelText: label, counterText: ''),
    validator:
        validator ??
        (value) => value == null || value.length != 4
            ? 'Enter exactly 4 digits.'
            : null,
  );

  Future<void> _setupTwoFactor() async {
    if (_securityBusy) return;
    setState(() => _securityBusy = true);
    try {
      final setup = await widget.api.post(
        'me/two-factor/setup',
        widget.token,
        const {},
      );
      final details = setup['data'];
      if (details is! Map<String, dynamic> || !mounted) return;
      final response = await showDialog<Map<String, dynamic>>(
        context: context,
        barrierDismissible: false,
        builder: (_) => _TwoFactorSetupDialog(
          api: widget.api,
          token: widget.token,
          setup: details,
        ),
      );
      final data = response?['data'];
      final recoveryCodes = response?['recovery_codes'];
      if (data is Map<String, dynamic> && mounted) {
        setState(() => _user.addAll(data));
        if (recoveryCodes is List) await _showRecoveryCodes(recoveryCodes);
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _securityBusy = false);
    }
  }

  Future<void> _showRecoveryCodes(List<dynamic> codes) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('Save your recovery codes'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Each code works once if you lose access to Google Authenticator. Save these now; they will not be shown again.',
              ),
              const SizedBox(height: 12),
              SelectableText(
                codes.join('\n'),
                style: const TextStyle(fontFamily: 'monospace', height: 1.6),
              ),
            ],
          ),
        ),
        actions: [
          TextButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: codes.join('\n')));
              if (dialogContext.mounted) {
                showAuthMessage(dialogContext, 'Recovery codes copied.');
              }
            },
            icon: const Icon(Icons.copy_rounded),
            label: const Text('Copy codes'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('I saved them'),
          ),
        ],
      ),
    );
  }

  Future<void> _disableTwoFactor() async {
    final code = TextEditingController();
    final enteredCode = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('Turn off authenticator verification'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Confirm with a current authenticator code or an unused recovery code.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: code,
              keyboardType: TextInputType.visiblePassword,
              textCapitalization: TextCapitalization.characters,
              maxLength: 20,
              textAlign: TextAlign.center,
              decoration: const InputDecoration(
                labelText: 'Authenticator or recovery code',
                counterText: '',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, code.text),
            child: const Text('Turn off'),
          ),
        ],
      ),
    );
    if (enteredCode == null) {
      code.dispose();
      return;
    }
    setState(() => _securityBusy = true);
    try {
      final response = await widget.api.delete(
        'me/two-factor',
        widget.token,
        body: {'code': enteredCode},
      );
      if (response['data'] is Map<String, dynamic> && mounted) {
        setState(() => _user.addAll(response['data'] as Map<String, dynamic>));
        showAuthMessage(context, 'Authenticator verification disabled.');
      }
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      code.dispose();
      if (mounted) setState(() => _securityBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = _user;
    final name = user['full_name']?.toString().trim() ?? '';
    final email = user['email']?.toString() ?? '';
    final avatar = MediaUrl.resolve(user['avatar']?.toString());
    final cover = MediaUrl.resolve(user['cover_image']?.toString());
    final countryName = user['country_name']?.toString();
    final countryCode = user['country_code']?.toString() ?? '';
    final country = (countryName == null || countryName.isEmpty)
        ? (countryCode.isEmpty ? 'Not set' : countryCode)
        : '$countryName ($countryCode)';
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final subscriptionContext = user['subscription_context'] is Map
        ? Map<String, dynamic>.from(user['subscription_context'] as Map)
        : <String, dynamic>{};
    final activeSubscriptions =
        (subscriptionContext['active_marketplaces'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(growable: false);
    final catalog = subscriptionContext['catalog'] is Map
        ? Map<String, dynamic>.from(subscriptionContext['catalog'] as Map)
        : <String, dynamic>{};
    final plansById = <String, Map<String, dynamic>>{};
    for (final group
        in (catalog['subscription_groups'] as List? ?? const [])
            .whereType<Map>()) {
      for (final plan
          in (group['plans'] as List? ?? const []).whereType<Map>()) {
        final id = plan['id']?.toString();
        if (id != null) plansById[id] = Map<String, dynamic>.from(plan);
      }
    }

    return Theme(
      data: theme.copyWith(
        scaffoldBackgroundColor: theme.scaffoldBackgroundColor,
        cardTheme: CardThemeData(
          color: theme.cardTheme.color,
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(19),
            side: BorderSide(color: colors.outlineVariant),
          ),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: theme.inputDecorationTheme.fillColor,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 15,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(13),
            borderSide: BorderSide(color: colors.outlineVariant),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(13),
            borderSide: BorderSide(color: colors.outlineVariant),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(13),
            borderSide: BorderSide(color: colors.primary, width: 1.4),
          ),
        ),
      ),
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            _editing ? 'Edit profile' : 'My profile',
            style: const TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 20,
              letterSpacing: -0.4,
            ),
          ),
          backgroundColor: theme.scaffoldBackgroundColor,
          surfaceTintColor: Colors.transparent,
          elevation: 0,
          actions: [
            if (!_editing)
              IconButton(
                tooltip: 'Settings',
                onPressed: _openSettings,
                icon: const Icon(Icons.settings_outlined),
              ),
            if (_editing)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: TextButton(
                  onPressed: _saving
                      ? null
                      : () => setState(() {
                          _editing = false;
                          _fillControllers();
                        }),
                  style: TextButton.styleFrom(
                    foregroundColor: colors.onSurfaceVariant,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                  ),
                  child: const Text(
                    'Cancel',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              )
            else
              Padding(
                padding: const EdgeInsets.only(right: 10),
                child: TextButton.icon(
                  onPressed: () => setState(() => _editing = true),
                  style: TextButton.styleFrom(
                    backgroundColor: colors.primary.withValues(alpha: 0.10),
                    foregroundColor: colors.primary,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 8,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(30),
                    ),
                  ),
                  icon: const Icon(Icons.edit_outlined, size: 16),
                  label: const Text(
                    'Edit',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ),
          ],
        ),
        body: RefreshIndicator(
          onRefresh: _refreshProfile,
          color: colors.primary,
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 760),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 5, 16, 30),
                children: [
                  _ProfileHero(
                    name: name.isEmpty ? 'Your profile' : name,
                    username: user['username']?.toString() ?? '',
                    referralCode: referralCode,
                    onTeamClick: _openMyTeam,
                    onShareClick:_shareReferralLink,
                    onCopyLink: _copyReferralLink,
                    email: email,
                    avatarUrl: avatar,
                    coverUrl: cover,
                    verified: user['email_verified'] == true,
                    memberSince: _formatDate(
                      user['created_at']?.toString(),
                    ),
                    activePlans: activeSubscriptions.length,
                  ),
                  const SizedBox(height: 15),
                  if (_editing) ...[
                    if (_mediaBusy)
                      Padding(
                        padding: EdgeInsets.only(bottom: 10),
                        child: LinearProgressIndicator(
                          minHeight: 3,
                          color: colors.primary,
                        ),
                      ),
                    _SectionCard(
                      title: 'Profile appearance',
                      icon: Icons.auto_awesome_outlined,
                      subtitle:
                          'Add a photo and cover to personalize your profile.',
                      child: Column(
                        children: [
                          _MediaRow(
                            title: 'Profile photo',
                            url: avatar,
                            isAvatar: true,
                            busy: _mediaBusy,
                            onPick: () => _pickImage('avatar'),
                            onRemove: avatar == null
                                ? null
                                : () => _removeImage('avatar'),
                          ),
                          const Divider(height: 22),
                          _MediaRow(
                            title: 'Cover image',
                            url: cover,
                            busy: _mediaBusy,
                            onPick: () => _pickImage('cover'),
                            onRemove: cover == null
                                ? null
                                : () => _removeImage('cover'),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _SectionCard(
                      title: 'Personal details',
                      icon: Icons.badge_outlined,
                      subtitle:
                          'Keep your contact and account information current.',
                      child: Column(
                        children: [
                          _EditField(
                            controller: _name,
                            label: 'Full name',
                            icon: Icons.person_outline,
                          ),
                          _EditField(
                            controller: _username,
                            label: 'Username',
                            icon: Icons.alternate_email,
                          ),
                          _EditField(
                            controller: _email,
                            label: 'Email address',
                            icon: Icons.email_outlined,
                            readOnly: true,
                          ),
                          _EditField(
                            controller: _phone,
                            label: 'Phone number',
                            icon: Icons.phone_outlined,
                            keyboard: TextInputType.phone,
                          ),
                          _EditField(
                            controller: _country,
                            label: 'Country',
                            icon: Icons.public,
                            readOnly: true,
                          ),
                          _EditField(
                            controller: _currency,
                            label: 'Preferred currency',
                            icon: Icons.currency_exchange,
                            capitalization: TextCapitalization.characters,
                          ),
                          const SizedBox(height: 4),
                          _GradientSaveButton(
                            busy: _saving,
                            onPressed: _save,
                          ),
                        ],
                      ),
                    ),
                  ] else ...[
                    _SectionCard(
                      title: 'Personal details',
                      icon: Icons.badge_outlined,
                      subtitle: 'Your profile and contact information.',
                      child: Column(
                        children: [
                          _DetailRow(
                            icon: Icons.person_outline,
                            label: 'Full name',
                            value: name,
                          ),
                          _DetailRow(
                            icon: Icons.alternate_email,
                            label: 'Username',
                            value: user['username']?.toString(),
                          ),
                          _DetailRow(
                            icon: Icons.email_outlined,
                            label: 'Email address',
                            value: email,
                            trailing: user['email_verified'] == true
                                ? _TinyStatus(
                                    label: 'Verified',
                                    color: colors.primary,
                                  )
                                : _TinyStatus(
                                    label: 'Unverified',
                                    color: colors.tertiary,
                                  ),
                          ),
                          _DetailRow(
                            icon: Icons.phone_outlined,
                            label: 'Phone number',
                            value: user['phone']?.toString(),
                          ),
                          _DetailRow(
                            icon: Icons.public,
                            label: 'Country',
                            value: country,
                          ),
                          _DetailRow(
                            icon: Icons.currency_exchange,
                            label: 'Preferred currency',
                            value: user['preferred_currency']?.toString(),
                            last: true,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    _SectionCard(
                      title: 'My subscriptions',
                      icon: Icons.workspace_premium_outlined,
                      subtitle: activeSubscriptions.isEmpty
                          ? 'Your active packages will appear here.'
                          : 'Your currently active earning packages and their terms.',
                      child: activeSubscriptions.isEmpty
                          ? _NoActiveSubscription(onBrowse: _openSubscriptions)
                          : Column(
                              children: [
                                for (
                                  var index = 0;
                                  index < activeSubscriptions.length;
                                  index++
                                ) ...[
                                  _ActiveSubscriptionCard(
                                    subscription: activeSubscriptions[index],
                                    plan:
                                        plansById[activeSubscriptions[index]['subscription_plan_id']
                                            ?.toString()],
                                    preferredCurrency:
                                        user['preferred_currency']
                                            ?.toString() ??
                                        'USD',
                                    api: widget.api,
                                    formatDate: _formatDate,
                                  ),
                                  if (index < activeSubscriptions.length - 1)
                                    const SizedBox(height: 10),
                                ],
                                const SizedBox(height: 12),
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: TextButton.icon(
                                    onPressed: _openSubscriptions,
                                    icon: const Icon(
                                      Icons.manage_accounts_outlined,
                                    ),
                                    label: const Text('Manage packages'),
                                  ),
                                ),
                              ],
                            ),
                    ),

                    const SizedBox(height: 12),
                    _SectionCard(
                      title: 'Account overview',
                      icon: Icons.insights_outlined,
                      child: Column(
                        children: [
                          _DetailRow(
                            icon: Icons.calendar_month_outlined,
                            label: 'Member since',
                            value: _formatDate(user['created_at']?.toString()),
                          ),
                          _DetailRow(
                            icon: Icons.verified_user_outlined,
                            label: 'Account status',
                            value: _statusLabel(user['status']?.toString()),
                            trailing: _StatusDot(
                              active:
                                  user['status'] == null ||
                                  user['status'] == 'active',
                            ),
                            last: true,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _SectionCard(
                      title: 'Security centre',
                      icon: Icons.shield_outlined,
                      subtitle: 'Manage the ways your account and earnings are protected.',
                      child: Column(
                        children: [
                          _SecurityAction(
                            icon: Icons.lock_reset_rounded,
                            title: 'Password',
                            subtitle: 'Change your account password',
                            onTap: _changePassword,
                          ),
                          const Divider(height: 1),
                          _SecurityAction(
                            icon: Icons.pin_rounded,
                            title: 'Withdrawal PIN',
                            subtitle: user['security_pin_set'] == true
                                ? 'A PIN is enabled for withdrawal requests'
                                : 'Add a 4-digit PIN for withdrawals',
                            trailing: Text(
                              user['security_pin_set'] == true
                                  ? 'Manage'
                                  : 'Set up',
                              style: TextStyle(
                                color: colors.primary,
                                fontWeight: FontWeight.w800,
                                fontSize: 12,
                              ),
                            ),
                            onTap: _securityBusy ? null : _manageSecurityPin,
                          ),
                          if (user['security_pin_set'] == true)
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton.icon(
                                onPressed: _securityBusy
                                    ? null
                                    : _removeSecurityPin,
                                icon: const Icon(
                                  Icons.delete_outline_rounded,
                                  size: 17,
                                ),
                                label: const Text('Remove withdrawal PIN'),
                              ),
                            ),
                          const Divider(height: 1),
                          SwitchListTile.adaptive(
                            contentPadding: EdgeInsets.zero,
                            secondary: _securityIcon(
                              context,
                              Icons.phonelink_lock_rounded,
                            ),
                            title: const Text(
                              'Google Authenticator',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                              ),
                            ),
                            subtitle: Text(
                              user['two_factor'] == true
                                  ? 'Extra sign-in verification is enabled'
                                  : 'Add a second verification step at sign in',
                              style: TextStyle(
                                fontSize: 12,
                                color: colors.onSurfaceVariant,
                              ),
                            ),
                            value: user['two_factor'] == true,
                            activeColor: colors.primary,
                            onChanged: _securityBusy
                                ? null
                                : (enabled) => enabled
                                      ? _setupTwoFactor()
                                      : _disableTwoFactor(),
                          ),
                          if (_securityBusy)
                            Padding(
                              padding: EdgeInsets.only(top: 8),
                              child: LinearProgressIndicator(
                                minHeight: 2,
                                color: colors.primary,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 13),
                  const _PrivacyNote(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _formatDate(String? value) {
    final date = DateTime.tryParse(value ?? '')?.toLocal();
    if (date == null) return '—';
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  String _statusLabel(String? status) => switch (status) {
    'active' => 'Active',
    'pending' => 'Pending',
    'suspended' => 'Suspended',
    null || '' => 'Active',
    _ => status,
  };
}

// ---------------------------------------------------------------------------
// Change password — three-step dialog
// ---------------------------------------------------------------------------

/// Password change flow: verify the current password, choose a new one, show a
/// loading page and then the result with a close button.
class _ChangePasswordDialog extends StatefulWidget {
  const _ChangePasswordDialog({required this.api, required this.token});

  final AuthApi api;
  final String token;

  @override
  State<_ChangePasswordDialog> createState() => _ChangePasswordDialogState();
}

class _ChangePasswordDialogState extends State<_ChangePasswordDialog> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  final _currentForm = GlobalKey<FormState>();
  final _newForm = GlobalKey<FormState>();

  /// 0 = current password, 1 = new password, 2 = working, 3 = result.
  int _step = 0;
  bool _done = false;
  String? _error;

  bool _showCurrent = false;
  bool _showNext = false;
  bool _showConfirm = false;

  bool get _working => _step == 2;
  bool get _finished => _step == 3;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  void _goTo(int step) {
    HapticFeedback.selectionClick();
    setState(() {
      _step = step;
      _error = null;
    });
  }

  void _proceed() {
    if (_currentForm.currentState?.validate() != true) return;
    _goTo(1);
  }

  Future<void> _submit() async {
    if (_newForm.currentState?.validate() != true) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _step = 2;
      _error = null;
    });

    try {
      await widget.api.put('me/password', widget.token, {
        'current_password': _current.text,
        'password': _next.text,
        'password_confirmation': _confirm.text,
      });
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      setState(() {
        _done = true;
        _step = 3;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      HapticFeedback.heavyImpact();
      setState(() {
        _done = false;
        _error = error.message;
        _step = 3;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _done = false;
        _error =
            'Something went wrong while updating your password. Please try again.';
        _step = 3;
      });
    }
  }

  void _close() => Navigator.of(context).pop(_done);

  static const int _minLength = 8;

  bool get _hasLength => _next.text.length >= _minLength;
  bool get _hasVariety {
    final value = _next.text;
    if (value.length < _minLength) return false;
    final hasLetter = RegExp('[A-Za-z]').hasMatch(value);
    final hasNumberOrSymbol = RegExp(r'[^A-Za-z]').hasMatch(value);
    return hasLetter && hasNumberOrSymbol;
  }

  bool get _differsFromCurrent =>
      _next.text.isNotEmpty && _next.text != _current.text;

  bool get _matches => _confirm.text.isNotEmpty && _confirm.text == _next.text;

  int get _strength {
    var score = 0;
    if (_hasLength) score++;
    if (_hasVariety) score++;
    if (_next.text.length >= 12) score++;
    if (_differsFromCurrent) score++;
    return score;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final media = MediaQuery.of(context);

    // A Dialog is lifted by the keyboard inset itself, so the card has to stay
    // inside what is left of the screen. Without this the fields overflow
    // whenever the keypad is open, so cap the height and let the body scroll.
    const insetPadding = EdgeInsets.symmetric(horizontal: 18, vertical: 24);
    final availableHeight =
        media.size.height -
        media.viewInsets.bottom -
        insetPadding.vertical -
        8;

    return PopScope(
      // Never let a tap outside throw away a request that is in flight, and make
      // the user acknowledge the result with the close button.
      canPop: !_working && !_finished,
      child: Dialog(
        backgroundColor: Colors.transparent,
        elevation: 0,
        insetPadding: insetPadding,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxWidth: 460,
            maxHeight: availableHeight > 320 ? availableHeight : 320,
          ),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: colors.surface,
              borderRadius: BorderRadius.circular(26),
              border: Border.all(
                color: colors.outlineVariant.withValues(alpha: 0.7),
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: colors.shadow.withValues(alpha: 0.24),
                  blurRadius: 42,
                  offset: const Offset(0, 20),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(26),
              child: AnimatedSize(
                duration: const Duration(milliseconds: 240),
                curve: Curves.easeOutCubic,
                alignment: Alignment.topCenter,
                clipBehavior: Clip.none,
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 240),
                  switchInCurve: Curves.easeOutCubic,
                  switchOutCurve: Curves.easeInCubic,
                  child: Column(
                    key: ValueKey<String>('step-$_step'),
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      _header(theme, colors),
                      Flexible(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
                          keyboardDismissBehavior:
                              ScrollViewKeyboardDismissBehavior.onDrag,
                          child: _body(theme, colors),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _header(ThemeData theme, ColorScheme colors) {
    final subtitle = switch (_step) {
      0 => 'Step 1 of 3 · Confirm it is you',
      1 => 'Step 2 of 3 · Choose a new password',
      2 => 'Step 3 of 3 · Saving',
      _ => _done ? 'All set' : 'Update failed',
    };

    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 10, 16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: _finished && !_done
              ? <Color>[colors.error, colors.errorContainer]
              : <Color>[colors.primary, colors.secondary],
        ),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: colors.onPrimary.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(15),
              border: Border.all(
                color: colors.onPrimary.withValues(alpha: 0.3),
              ),
            ),
            child: Icon(
              _finished && !_done
                  ? Icons.priority_high_rounded
                  : Icons.lock_reset_rounded,
              color: colors.onPrimary,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Change password',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.2,
                    color: colors.onPrimary,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.onPrimary.withValues(alpha: 0.85),
                  ),
                ),
              ],
            ),
          ),
          if (!_working && !_finished)
            IconButton(
              tooltip: 'Cancel',
              onPressed: () => Navigator.of(context).pop(false),
              icon: const Icon(Icons.close_rounded, size: 20),
              color: colors.onPrimary,
              visualDensity: VisualDensity.compact,
            ),
        ],
      ),
    );
  }

  Widget _body(ThemeData theme, ColorScheme colors) {
    switch (_step) {
      case 0:
        return _currentStep(theme, colors);
      case 1:
        return _newStep(theme, colors);
      case 2:
        return _workingStep(theme, colors);
      default:
        return _resultStep(theme, colors);
    }
  }

  Widget _currentStep(ThemeData theme, ColorScheme colors) {
    return Form(
      key: _currentForm,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(
            "Confirm it's you",
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Enter the password you use today. We check it before letting you '
            'choose a new one.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 18),
          _PasswordField(
            controller: _current,
            label: 'Current password',
            icon: Icons.password_rounded,
            visible: _showCurrent,
            onToggle: () => setState(() => _showCurrent = !_showCurrent),
            onSubmitted: _proceed,
            validator: (value) => value == null || value.isEmpty
                ? 'Enter your current password'
                : null,
          ),
          const SizedBox(height: 20),
          _PrimaryAction(
            label: 'Proceed',
            icon: Icons.arrow_forward_rounded,
            onPressed: _proceed,
          ),
          const SizedBox(height: 4),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () => Navigator.of(context).pop(false),
              icon: const Icon(Icons.close_rounded, size: 16),
              label: const Text('Cancel'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _newStep(ThemeData theme, ColorScheme colors) {
    return Form(
      key: _newForm,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(
            'Set a new password',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Use at least 8 characters. A mix of letters, numbers and symbols '
            'keeps your earnings safe.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 18),
          _PasswordField(
            controller: _next,
            label: 'New password',
            icon: Icons.lock_outline_rounded,
            visible: _showNext,
            onToggle: () => setState(() => _showNext = !_showNext),
            onChanged: (_) => setState(() {}),
            validator: (value) {
              if (value == null || value.length < _minLength) {
                return 'Use at least 8 characters';
              }
              if (value == _current.text) {
                return 'Choose a password different from your current one';
              }
              return null;
            },
          ),
          const SizedBox(height: 12),
          _StrengthMeter(score: _strength),
          const SizedBox(height: 14),
          _PasswordField(
            controller: _confirm,
            label: 'Confirm new password',
            icon: Icons.lock_reset_rounded,
            visible: _showConfirm,
            onToggle: () => setState(() => _showConfirm = !_showConfirm),
            onChanged: (_) => setState(() {}),
            onSubmitted: _submit,
            validator: (value) {
              if (value == null || value.isEmpty) {
                return 'Re-enter your new password';
              }
              if (value != _next.text) return 'Passwords do not match';
              return null;
            },
          ),
          const SizedBox(height: 14),
          _RuleRow(ok: _hasLength, label: 'At least 8 characters'),
          _RuleRow(ok: _hasVariety, label: 'Letters with numbers or symbols'),
          _RuleRow(ok: _differsFromCurrent, label: 'Different from your password'),
          _RuleRow(ok: _matches, label: 'Both passwords match'),
          const SizedBox(height: 18),
          _PrimaryAction(
            label: 'Update now',
            icon: Icons.check_rounded,
            onPressed: _submit,
          ),
          const SizedBox(height: 4),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () => _goTo(0),
              icon: const Icon(Icons.arrow_back_rounded, size: 16),
              label: const Text('Back'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _workingStep(ThemeData theme, ColorScheme colors) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 18),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const _PulsingLock(),
          const SizedBox(height: 22),
          Text(
            'Updating your password…',
            textAlign: TextAlign.center,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Hang tight, this only takes a moment.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }

  Widget _resultStep(ThemeData theme, ColorScheme colors) {
    final accent = _done ? colors.primary : colors.error;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Stack(
          alignment: Alignment.center,
          children: <Widget>[
            if (_done) _SuccessRings(color: colors.primary),
            Container(
              width: 92,
              height: 92,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[accent, accent.withValues(alpha: 0.72)],
                ),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: accent.withValues(alpha: 0.32),
                    blurRadius: 26,
                    offset: const Offset(0, 12),
                  ),
                ],
              ),
              child: Icon(
                _done
                    ? Icons.check_rounded
                    : Icons.close_rounded,
                size: 46,
                color: colors.onPrimary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 20),
        Text(
          _done ? 'Password updated' : 'Could not update',
          textAlign: TextAlign.center,
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w900,
            letterSpacing: -0.4,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _done
              ? 'Your new password is active. Use it the next time you sign in.'
              : (_error ?? 'Please try again.'),
          textAlign: TextAlign.center,
          style: theme.textTheme.bodySmall?.copyWith(
            color: colors.onSurfaceVariant,
            height: 1.55,
          ),
        ),
        const SizedBox(height: 22),
        if (_done)
          _PrimaryAction(
            label: 'Close',
            icon: Icons.done_all_rounded,
            onPressed: _close,
          )
        else ...<Widget>[
          _PrimaryAction(
            label: 'Try again',
            icon: Icons.refresh_rounded,
            onPressed: () => _goTo(1),
          ),
          const SizedBox(height: 4),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: _close,
              icon: const Icon(Icons.close_rounded, size: 16),
              label: const Text('Close'),
            ),
          ),
        ],
      ],
    );
  }
}

class _PasswordField extends StatelessWidget {
  const _PasswordField({
    required this.controller,
    required this.label,
    required this.icon,
    required this.visible,
    required this.onToggle,
    this.validator,
    this.onChanged,
    this.onSubmitted,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final bool visible;
  final VoidCallback onToggle;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onSubmitted;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return TextFormField(
      controller: controller,
      obscureText: !visible,
      validator: validator,
      onChanged: onChanged,
      autofillHints: const <String>[AutofillHints.password],
      textInputAction: onSubmitted == null
          ? TextInputAction.next
          : TextInputAction.done,
      onFieldSubmitted: onSubmitted == null ? null : (_) => onSubmitted!(),
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, size: 19),
        suffixIcon: IconButton(
          tooltip: visible ? 'Hide password' : 'Show password',
          onPressed: onToggle,
          icon: Icon(
            visible
                ? Icons.visibility_off_outlined
                : Icons.visibility_outlined,
            size: 19,
          ),
        ),
        filled: true,
        fillColor: colors.surfaceContainerLow,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(color: colors.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(color: colors.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(color: colors.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(color: colors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(color: colors.error, width: 1.5),
        ),
      ),
    );
  }
}

class _PrimaryAction extends StatelessWidget {
  const _PrimaryAction({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final radius = BorderRadius.circular(16);
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[colors.primary, colors.secondary],
        ),
        borderRadius: radius,
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: colors.primary.withValues(alpha: 0.26),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: radius,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onPressed,
          child: SizedBox(
            height: 52,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                Icon(icon, size: 18, color: colors.onPrimary),
                const SizedBox(width: 8),
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: colors.onPrimary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _GradientSaveButton extends StatelessWidget {
  const _GradientSaveButton({required this.busy, required this.onPressed});

  final bool busy;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final radius = BorderRadius.circular(15);

    return AnimatedOpacity(
      duration: const Duration(milliseconds: 180),
      opacity: busy ? 0.72 : 1,
      child: Container(
        height: 52,
        width: double.infinity,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[colors.primary, colors.secondary],
          ),
          borderRadius: radius,
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: colors.primary.withValues(alpha: 0.28),
              blurRadius: 20,
              offset: const Offset(0, 9),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: radius,
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: busy ? null : onPressed,
            child: Center(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  if (busy)
                    SizedBox.square(
                      dimension: 17,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: colors.onPrimary,
                      ),
                    )
                  else
                    Icon(Icons.check_rounded, size: 19, color: colors.onPrimary),
                  const SizedBox(width: 8),
                  Text(
                    busy ? 'Saving…' : 'Save changes',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: colors.onPrimary,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _RuleRow extends StatelessWidget {
  const _RuleRow({required this.ok, required this.label});

  final bool ok;
  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final color = ok ? colors.primary : colors.onSurfaceVariant;
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: <Widget>[
          AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            width: 18,
            height: 18,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: ok ? colors.primary.withValues(alpha: 0.12) : null,
              border: Border.all(
                color: ok
                    ? colors.primary
                    : colors.outlineVariant.withValues(alpha: 0.9),
                width: 1.4,
              ),
            ),
            child: ok
                ? Icon(Icons.check_rounded, size: 12, color: colors.primary)
                : null,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 11.5,
                color: color,
                fontWeight: ok ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StrengthMeter extends StatelessWidget {
  const _StrengthMeter({required this.score});

  /// 0-4, produced by the dialog from length, variety and uniqueness.
  final int score;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    const labels = <String>['Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
    final index = score < 0 ? 0 : (score > 4 ? 4 : score);
    final label = labels[index];
    final color = score >= 4
        ? colors.primary
        : score == 3
        ? colors.tertiary
        : colors.error;

    return Row(
      children: <Widget>[
        Expanded(
          child: Row(
            children: <Widget>[
              for (var index = 0; index < 4; index++) ...<Widget>[
                if (index > 0) const SizedBox(width: 4),
                Expanded(
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 220),
                    height: 4,
                    decoration: BoxDecoration(
                      color: index < score
                          ? color
                          : colors.outlineVariant.withValues(alpha: 0.7),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(width: 10),
        Text(
          label,
          style: TextStyle(
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
            color: color,
          ),
        ),
      ],
    );
  }
}

class _PulsingLock extends StatefulWidget {
  const _PulsingLock();

  @override
  State<_PulsingLock> createState() => _PulsingLockState();
}

class _PulsingLockState extends State<_PulsingLock>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return ScaleTransition(
      scale: Tween<double>(begin: 0.96, end: 1.05).animate(
        CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: <Widget>[
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[colors.primary, colors.secondary],
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: colors.primary.withValues(alpha: 0.3),
                  blurRadius: 24,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Icon(
              Icons.lock_outline_rounded,
              color: colors.onPrimary,
              size: 34,
            ),
          ),
          SizedBox(
            width: 108,
            height: 108,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: colors.primary.withValues(alpha: 0.45),
            ),
          ),
        ],
      ),
    );
  }
}

class _SuccessRings extends StatefulWidget {
  const _SuccessRings({required this.color});

  final Color color;

  @override
  State<_SuccessRings> createState() => _SuccessRingsState();
}

class _SuccessRingsState extends State<_SuccessRings>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..forward();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 92,
      height: 92,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          return Stack(
            alignment: Alignment.center,
            children: <Widget>[
              for (final offset in <double>[0.0, 0.28])
                _ring(_progress(offset)),
            ],
          );
        },
      ),
    );
  }

  double _progress(double offset) {
    final span = 1 - offset;
    final raw = span <= 0 ? 1.0 : (_controller.value - offset) / span;
    if (raw < 0) return 0;
    return raw > 1 ? 1 : raw;
  }

  Widget _ring(double t) {
    return Transform.scale(
      scale: 1 + t,
      child: Container(
        width: 92,
        height: 92,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: widget.color.withValues(alpha: 0.5 * (1 - t)),
            width: 2,
          ),
        ),
      ),
    );
  }
}

class _TwoFactorSetupDialog extends StatefulWidget {
  const _TwoFactorSetupDialog({
    required this.api,
    required this.token,
    required this.setup,
  });
  final AuthApi api;
  final String token;
  final Map<String, dynamic> setup;

  @override
  State<_TwoFactorSetupDialog> createState() => _TwoFactorSetupDialogState();
}

class _TwoFactorSetupDialogState extends State<_TwoFactorSetupDialog> {
  final _code = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _confirm() async {
    if (_code.text.trim().length != 6) {
      showAuthMessage(
        context,
        'Enter the 6-digit code shown in your authenticator app.',
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final result = await widget.api.post(
        'me/two-factor/confirm',
        widget.token,
        {'code': _code.text.trim()},
      );
      if (mounted) Navigator.pop(context, result);
    } on AuthException catch (error) {
      if (mounted) {
        setState(() => _busy = false);
        showAuthMessage(context, error.message);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final secret = widget.setup['secret']?.toString() ?? '';
    final uri = widget.setup['otpauth_uri']?.toString() ?? '';
    final screen = MediaQuery.sizeOf(context);
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      child: SizedBox(
        width: screen.width < 432 ? screen.width - 40 : 392,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: screen.height * .84),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 22, 24, 8),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Set up Google Authenticator',
                    style: Theme.of(context).textTheme.titleLarge
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Scan this QR code with Google Authenticator, or enter the setup key manually.',
                      ),
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.surface,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: Theme.of(context).colorScheme.outlineVariant,
                          ),
                        ),
                        child: SizedBox.square(
                          dimension: 190,
                          child: QrImageView(data: uri),
                        ),
                      ),
                      const SizedBox(height: 12),
                      SelectableText(
                        secret,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: 'monospace',
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.2,
                        ),
                      ),
                      TextButton.icon(
                        onPressed: () async {
                          await Clipboard.setData(ClipboardData(text: secret));
                          if (context.mounted)
                            showAuthMessage(context, 'Setup key copied.');
                        },
                        icon: const Icon(Icons.copy_rounded, size: 18),
                        label: const Text('Copy setup key'),
                      ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _code,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        maxLength: 6,
                        textAlign: TextAlign.center,
                        decoration: const InputDecoration(
                          labelText: '6-digit authenticator code',
                          counterText: '',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: Wrap(
                  alignment: WrapAlignment.end,
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    TextButton(
                      onPressed: _busy ? null : () => Navigator.pop(context),
                      child: const Text('Cancel'),
                    ),
                    FilledButton.icon(
                      onPressed: _busy ? null : _confirm,
                      style: FilledButton.styleFrom(
                        backgroundColor: Theme.of(context).colorScheme.primary,
                      ),
                      icon: _busy
                          ? SizedBox.square(
                              dimension: 17,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Theme.of(context).colorScheme.onPrimary,
                              ),
                            )
                          : const Icon(Icons.verified_user_outlined),
                      label: Text(
                        _busy ? 'Verifying…' : 'Enable 2-step verification',
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileHero extends StatelessWidget {
  const _ProfileHero({
    required this.name,
    required this.username,
    required this.email,
    required this.avatarUrl,
    required this.coverUrl,
    required this.verified,
    required this.referralCode,
    this.memberSince,
    this.activePlans, 
    required this.onTeamClick,
    required this.onShareClick,
    required this.onCopyLink,
  });

  final String name;
  final String username;
  final String email;
  final String? avatarUrl;
  final String referralCode;
  final String? coverUrl;
  final bool verified;
  final String? memberSince;
  final int? activePlans;
  final Future<void> Function() onTeamClick;
  final Future<void> Function() onShareClick;
  final Future<void> Function() onCopyLink;


  static const double _coverHeight = 152;
  static const double _avatarSize = 92;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: 0.8)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: scheme.shadow.withValues(alpha: 0.10),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            height: _coverHeight + 46,
            child: Stack(
              clipBehavior: Clip.none,
              children: <Widget>[
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  height: _coverHeight,
                  child: _cover(scheme),
                ),
                Positioned(
                  left: 20,
                  bottom: 0,
                  child: _avatar(scheme),
                ),
                Positioned(
                  right: 18,
                  top: 16,
                  child: verified
                      ? _VerifiedChip(color: scheme.onPrimary)
                      : const SizedBox.shrink(),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: scheme.onSurface,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.4,
                    height: 1.1,
                  ),
                ),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (username.isNotEmpty) ...<Widget>[
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: scheme.primary.withValues(alpha: 0.09),
                                borderRadius: BorderRadius.circular(30),
                                border: Border.all(
                                  color: scheme.primary.withValues(alpha: 0.18),
                                ),
                              ),
                              child: Text(
                                '@$username',
                                style: theme.textTheme.labelMedium?.copyWith(
                                  color: scheme.primary,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                          if (email.isNotEmpty) ...<Widget>[
                            const SizedBox(height: 10),
                            Row(
                              children: <Widget>[
                                Icon(
                                  Icons.mail_outline_rounded,
                                  size: 15,
                                  color: scheme.onSurfaceVariant,
                                ),
                                const SizedBox(width: 7),
                                Expanded(
                                  child: Text(
                                    email,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: scheme.onSurfaceVariant,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    OutlinedButton.icon(
                      onPressed: onTeamClick,
                      icon: const Icon(
                        Icons.groups_rounded,
                        size: 17,
                      ),
                      label: const Text('My Team'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: scheme.onSurface,
                        backgroundColor: scheme.onPrimary,
                        side: BorderSide(
                          color: scheme.primary,
                          width: 1.2,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12.0),
                        ),
                        textStyle: const TextStyle(
                          fontWeight: FontWeight.w800,
                        ),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 15,
                          vertical: 13,
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerLow,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: scheme.outlineVariant.withValues(alpha: 0.7),
                    ),
                  ),
                  child: Row(
                    children: <Widget>[
                      Expanded(
                        child: _HeroStat(
                          icon: Icons.calendar_month_outlined,
                          label: 'Member since',
                          value: (memberSince == null || memberSince!.isEmpty)
                              ? '—'
                              : memberSince!,
                        ),
                      ),
                      Container(
                        width: 1,
                        height: 34,
                        color: scheme.outlineVariant,
                      ),
                      Expanded(
                        child: _HeroStat(
                          icon: Icons.workspace_premium_outlined,
                          label: 'Active packs',
                          value: activePlans == null ? '—' : '$activePlans',
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          Container(
          padding: const EdgeInsets.fromLTRB(13, 11, 8, 8),
          decoration: BoxDecoration(
            color: Colors.black.withValues(alpha: .60),
            borderRadius: BorderRadius.only(
              bottomLeft: Radius.circular(26),
              bottomRight: Radius.circular(26),
            ),
            border: Border.all(
            color: Colors.white.withValues(alpha: .16),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
            Text(
              'YOUR PERSONAL INVITE LINK',
              style: TextStyle(
              color: Color(0xFFD9F3DF),
              fontSize: 9,
              letterSpacing: .8,
              fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 5),
            SelectableText(
              ReferralLink.referralWebLink(referralCode),
              style: TextStyle(
              color: Colors.white,
              fontSize: 11,
              height: 1.3,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
              const Spacer(),
              TextButton.icon(
                onPressed: onCopyLink,
                style: TextButton.styleFrom(
                foregroundColor: Colors.white,
                visualDensity: VisualDensity.compact,
                ),
                icon: const Icon(Icons.copy_rounded, size: 15),
                label: Text('Copy link'),
              ),
              const SizedBox(width: 5),
              FilledButton.icon(
                onPressed: onShareClick,
                style: FilledButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: Colors.green,
                minimumSize: const Size(0, 38),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                ),
                icon: const Icon(Icons.share_rounded, size: 15),
                label: Text(
                'Share',
                style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              ],
            ),
            ],
          ),
          ),

        ],
      ),
    );
  }

  Widget _cover(ColorScheme scheme) {
    return Stack(
      fit: StackFit.expand,
      children: <Widget>[
        DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[
                scheme.primary,
                Color.lerp(scheme.primary, scheme.secondary, 0.6)!,
                scheme.secondary,
              ],
            ),
          ),
        ),
        if (coverUrl != null)
          Positioned.fill(
            child: Image.network(
              coverUrl!,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stack) => const SizedBox.shrink(),
            ),
          ),
        if (coverUrl == null) ...<Widget>[
          Positioned(
            right: -40,
            top: -70,
            child: Container(
              width: 210,
              height: 210,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: scheme.onPrimary.withValues(alpha: 0.14),
                  width: 24,
                ),
              ),
            ),
          ),
          Positioned(
            right: 56,
            bottom: -70,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: scheme.onPrimary.withValues(alpha: 0.08),
              ),
            ),
          ),
          Positioned(
            left: -30,
            bottom: -60,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: scheme.onPrimary.withValues(alpha: 0.05),
              ),
            ),
          ),
        ],
        // Depth for the avatar and text that sit on top of the cover.
        DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: <Color>[
                scheme.scrim.withValues(alpha: 0.02),
                scheme.scrim.withValues(alpha: coverUrl == null ? 0.16 : 0.34),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _avatar(ColorScheme scheme) {
    return Container(
      width: _avatarSize + 8,
      height: _avatarSize + 8,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[scheme.primary, scheme.secondary],
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: scheme.shadow.withValues(alpha: 0.20),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Container(
        padding: const EdgeInsets.all(3),
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: scheme.surface,
        ),
        child: ClipOval(
          child: avatarUrl == null
              ? Container(
                  color: scheme.secondaryContainer,
                  alignment: Alignment.center,
                  child: Icon(
                    Icons.person_rounded,
                    size: 44,
                    color: scheme.onSecondaryContainer,
                  ),
                )
              : Image.network(
                  avatarUrl!,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stack) => Container(
                    color: scheme.secondaryContainer,
                    alignment: Alignment.center,
                    child: Icon(
                      Icons.person_rounded,
                      size: 44,
                      color: scheme.onSecondaryContainer,
                    ),
                  ),
                ),
        ),
      ),
    );
  }
}

/// Small verified pill used on the hero cover.
class _VerifiedChip extends StatelessWidget {
  const _VerifiedChip({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.26),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: color.withValues(alpha: 0.38)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.verified_rounded, size: 13, color: color),
          const SizedBox(width: 5),
          Text(
            'Verified',
            style: TextStyle(
              color: color,
              fontSize: 10.5,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}

/// Label/value pair used inside the hero summary strip.
class _HeroStat extends StatelessWidget {
  const _HeroStat({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: <Widget>[
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(icon, size: 13, color: colors.onSurfaceVariant),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                label.toUpperCase(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 9,
                  letterSpacing: 0.7,
                  fontWeight: FontWeight.w800,
                  color: colors.onSurfaceVariant,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.labelLarge?.copyWith(
            fontWeight: FontWeight.w800,
            color: colors.onSurface,
          ),
        ),
      ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.child,
    this.subtitle,
    this.icon,
  });

  final String title;
  final String? subtitle;
  final Widget child;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final accent = icon;

    return Container(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.8)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: colors.shadow.withValues(alpha: 0.05),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: <Widget>[
              if (accent != null) ...<Widget>[
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: <Color>[
                        colors.primary.withValues(alpha: 0.16),
                        colors.secondary.withValues(alpha: 0.10),
                      ],
                    ),
                    borderRadius: BorderRadius.circular(13),
                    border: Border.all(
                      color: colors.primary.withValues(alpha: 0.18),
                    ),
                  ),
                  child: Icon(accent, size: 20, color: colors.primary),
                ),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: colors.onSurface,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.2,
                      ),
                    ),
                    if (subtitle != null) ...<Widget>[
                      const SizedBox(height: 3),
                      Text(
                        subtitle!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                          height: 1.4,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _NoActiveSubscription extends StatelessWidget {
  const _NoActiveSubscription({required this.onBrowse});

  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            colors.primary.withValues(alpha: 0.07),
            colors.secondary.withValues(alpha: 0.05),
          ],
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.primary.withValues(alpha: 0.16)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: colors.primary.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.layers_outlined,
                  color: colors.primary,
                  size: 19,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Text(
                  'No active package',
                  style: TextStyle(
                    color: colors.onSurface,
                    fontSize: 14.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          Text(
            'Join a package to unlock its app review or ad sharing tasks.',
            style: TextStyle(
              color: colors.onSurfaceVariant,
              fontSize: 12.5,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: onBrowse,
              style: TextButton.styleFrom(
                backgroundColor: colors.primary,
                foregroundColor: colors.onPrimary,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(30),
                ),
              ),
              icon: const Icon(Icons.arrow_forward_rounded, size: 17),
              label: const Text(
                'Browse packages',
                style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActiveSubscriptionCard extends StatelessWidget {
  const _ActiveSubscriptionCard({
    required this.subscription,
    required this.plan,
    required this.preferredCurrency,
    required this.api,
    required this.formatDate,
  });

  final Map<String, dynamic> subscription;
  final Map<String, dynamic>? plan;
  final String preferredCurrency;
  final AuthApi api;
  final String Function(String?) formatDate;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final source = plan ?? const <String, dynamic>{};
    final groupKey =
        subscription['group_key']?.toString() ??
        source['group_key']?.toString() ??
        '';
    final planName =
        source['name']?.toString() ??
        subscription['plan_name']?.toString() ??
        'Marketplace package';
    final sourceCurrency = source['currency']?.toString() ?? 'USD';
    final price = source['price_minor'];
    final billingInterval = source['billing_interval']?.toString();
    final durationCount = (source['duration_count'] as num?)?.toInt() ?? 1;
    final withdrawalDays = (source['withdrawal_days'] as List? ?? const [])
        .map((day) => day.toString())
        .where((day) => day.isNotEmpty)
        .toList();
    final groupLabel = switch (groupKey) {
      'group_a' => 'Ad sharing',
      'group_b' => 'Store app reviews',
      'group_c' => 'Apple app reviews',
      _ => 'Marketplace access',
    };
    final intervalUnit = switch (billingInterval?.toLowerCase()) {
      'monthly' => 'month',
      'weekly' => 'week',
      'quarterly' => 'quarter',
      'yearly' || 'annual' => 'year',
      _ => null,
    };
    final durationLabel = intervalUnit == null
        ? null
        : '$durationCount $intervalUnit${durationCount == 1 ? '' : 's'}';
    final endsAt = formatDate(subscription['ends_at']?.toString());
    final startsAt = formatDate(subscription['starts_at']?.toString());

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.8)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: colors.shadow.withValues(alpha: 0.04),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: <Color>[
                      colors.primary.withValues(alpha: 0.16),
                      colors.secondary.withValues(alpha: 0.10),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: colors.primary.withValues(alpha: 0.18),
                  ),
                ),
                child: Icon(
                  groupKey == 'group_a'
                      ? Icons.campaign_outlined
                      : Icons.rate_review_outlined,
                  color: colors.primary,
                  size: 21,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      planName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.onSurface,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      groupLabel,
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: colors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(30),
                  border: Border.all(
                    color: colors.primary.withValues(alpha: 0.26),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: colors.primary,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 5),
                    Text(
                      'ACTIVE',
                      style: TextStyle(
                        color: colors.primary,
                        fontSize: 9,
                        letterSpacing: .6,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (price != null) ...[
            const SizedBox(height: 13),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: Text(
                    moneyConverted(
                      api,
                      price,
                      sourceCurrency,
                      source['currency_minor_units'],
                      preferredCurrency,
                      isMinor: true,
                    ),
                    style: TextStyle(
                      color: colors.primary,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                if (durationLabel != null)
                  Text(
                    'Term · $durationLabel',
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontSize: 10,
                    ),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => _showSubscriptionFeatures(
                context,
                planName,
                [
                  if (startsAt.isNotEmpty)
                    const MapEntry(
                      Icons.play_circle_outline_rounded,
                      'Started',
                    ),
                  if (endsAt.isNotEmpty)
                    const MapEntry(
                      Icons.event_available_outlined,
                      'Valid until',
                    ),
                  if (source['max_review_tasks'] is num &&
                      (source['max_review_tasks'] as num).toInt() > 0)
                    MapEntry(
                      Icons.rate_review_outlined,
                      '${source['max_review_tasks']} reviews',
                    ),
                  if (source['max_ad_shares'] is num &&
                      (source['max_ad_shares'] as num).toInt() > 0)
                    MapEntry(
                      Icons.campaign_outlined,
                      '${source['max_ad_shares']} ad shares',
                    ),
                  if (source['daily_task_limit'] is num &&
                      (source['daily_task_limit'] as num).toInt() > 0)
                    MapEntry(
                      Icons.today_outlined,
                      '${source['daily_task_limit']} tasks daily',
                    ),
                  if (source['daily_revenue_minor'] is num &&
                      (source['daily_revenue_minor'] as num).toInt() > 0)
                    MapEntry(
                      Icons.trending_up_rounded,
                      'Daily earning limit: ${moneyConverted(api, source['daily_revenue_minor'], sourceCurrency, source['currency_minor_units'], preferredCurrency, isMinor: true)}',
                    ),
                  if (source['minimum_withdrawal_minor'] is num &&
                      (source['minimum_withdrawal_minor'] as num).toInt() > 0)
                    MapEntry(
                      Icons.account_balance_wallet_outlined,
                      'Minimum withdrawal: ${moneyConverted(api, source['minimum_withdrawal_minor'], sourceCurrency, source['currency_minor_units'], preferredCurrency, isMinor: true)}',
                    ),
                  if (withdrawalDays.isNotEmpty)
                    MapEntry(
                      Icons.calendar_month_outlined,
                      'Withdrawal days: ${withdrawalDays.join(' & ')}',
                    ),
                ],
                startedAt: startsAt,
                validUntil: endsAt,
              ),
              icon: const Icon(Icons.visibility_outlined, size: 18),
              label: const Text('View package features'),
            ),
          ),
        ],
      ),
    );
  }
}

void _showSubscriptionFeatures(
  BuildContext context,
  String planName,
  List<MapEntry<IconData, String>> features, {
  required String startedAt,
  required String validUntil,
}) {
  final colors = Theme.of(context).colorScheme;
  final items = <MapEntry<IconData, String>>[
    if (startedAt.isNotEmpty)
      MapEntry(Icons.play_circle_outline_rounded, 'Started: $startedAt'),
    if (validUntil.isNotEmpty)
      MapEntry(Icons.event_available_outlined, 'Valid until: $validUntil'),
    ...features.where(
      (item) =>
          item.key != Icons.play_circle_outline_rounded &&
          item.key != Icons.event_available_outlined,
    ),
  ];

  showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text('$planName features'),
      content: SizedBox(
        width: 420,
        child: items.isEmpty
            ? const Text(
                'No additional feature details are available for this package.',
              )
            : ListView.separated(
                shrinkWrap: true,
                itemCount: items.length,
                separatorBuilder: (_, __) =>
                    Divider(height: 1, color: colors.outlineVariant),
                itemBuilder: (context, index) {
                  final item = items[index];
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 9),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(item.key, size: 19, color: colors.primary),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            item.value,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text('Close'),
        ),
      ],
    ),
  );
}

class _SubscriptionInfoPill extends StatelessWidget {
  const _SubscriptionInfoPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: colors.onSurfaceVariant),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: colors.onSurfaceVariant,
              fontSize: 9,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.icon,
    required this.label,
    this.value,
    this.trailing,
    this.last = false,
  });

  final IconData icon;
  final String label;
  final String? value;
  final Widget? trailing;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final hasValue = value != null && value!.isNotEmpty;

    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 11),
          child: Row(
            children: <Widget>[
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: <Color>[
                      colors.primary.withValues(alpha: 0.14),
                      colors.secondary.withValues(alpha: 0.08),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(13),
                  border: Border.all(
                    color: colors.primary.withValues(alpha: 0.14),
                  ),
                ),
                child: Icon(icon, size: 19, color: colors.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      label.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 9.5,
                        letterSpacing: 0.7,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      hasValue ? value! : 'Not added',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: hasValue
                            ? colors.onSurface
                            : colors.onSurfaceVariant,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              if (trailing != null) ...<Widget>[
                const SizedBox(width: 8),
                trailing!,
              ],
            ],
          ),
        ),
        if (!last)
          Divider(
            height: 1,
            color: colors.outlineVariant.withValues(alpha: 0.6),
          ),
      ],
    );
  }
}

class _EditField extends StatelessWidget {
  const _EditField({
    required this.controller,
    required this.label,
    required this.icon,
    this.readOnly = false,
    this.keyboard,
    this.capitalization = TextCapitalization.none,
  });
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final bool readOnly;
  final TextInputType? keyboard;
  final TextCapitalization capitalization;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextField(
      controller: controller,
      readOnly: readOnly,
      keyboardType: keyboard,
      textCapitalization: capitalization,
      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, size: 19),
        filled: true,
        fillColor: Theme.of(
          context,
        ).colorScheme.surfaceContainerLow.withValues(alpha: 0.7),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(
            color: Theme.of(context).colorScheme.outlineVariant,
          ),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: BorderSide(
            color: Theme.of(context).colorScheme.primary,
            width: 1.6,
          ),
        ),
        helperText: label == 'Country'
            ? 'Country is fixed from registration.'
            : null,
      ),
    ),
  );
}

class _MediaRow extends StatelessWidget {
  const _MediaRow({
    required this.title,
    required this.onPick,
    required this.busy,
    this.url,
    this.isAvatar = false,
    this.onRemove,
  });
  final String title;
  final String? url;
  final bool isAvatar;
  final bool busy;
  final VoidCallback onPick;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final preview = Container(
      decoration: BoxDecoration(
        shape: isAvatar ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: isAvatar ? null : BorderRadius.circular(12),
        border: Border.all(
          color: colors.primary.withValues(alpha: 0.22),
          width: 1.4,
        ),
      ),
      padding: const EdgeInsets.all(2),
      child: isAvatar
          ? CircleAvatar(
              radius: 24,
              backgroundColor: colors.primaryContainer,
              backgroundImage: url == null ? null : NetworkImage(url!),
              child: url == null
                  ? Icon(
                      Icons.person_outline,
                      color: colors.onPrimaryContainer,
                    )
                  : null,
            )
          : ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: SizedBox(
                width: 74,
                height: 46,
                child: url == null
                    ? ColoredBox(
                        color: colors.primaryContainer,
                        child: Icon(
                          Icons.panorama_outlined,
                          color: colors.onPrimaryContainer,
                        ),
                      )
                    : Image.network(url!, fit: BoxFit.cover),
              ),
            ),
    );
    return Row(
      children: [
        preview,
        const SizedBox(width: 13),
        Expanded(
          child: Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: colors.onSurface,
              fontSize: 13.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        TextButton(
          onPressed: busy ? null : onPick,
          style: TextButton.styleFrom(
            backgroundColor: colors.primary.withValues(alpha: 0.10),
            foregroundColor: colors.primary,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
          ),
          child: Text(
            url == null ? 'Add' : 'Change',
            style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800),
          ),
        ),
        if (onRemove != null)
          IconButton(
            onPressed: busy ? null : onRemove,
            tooltip: 'Remove $title',
            visualDensity: VisualDensity.compact,
            icon: Icon(
              Icons.delete_outline_rounded,
              size: 19,
              color: colors.onSurfaceVariant,
            ),
          ),
      ],
    );
  }
}

class _SecurityAction extends StatelessWidget {
  const _SecurityAction({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final radius = BorderRadius.circular(16);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: radius,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 2),
          child: Row(
            children: <Widget>[
              _securityIcon(context, icon),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: colors.onSurface,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colors.onSurfaceVariant,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              trailing ?? _Chevron(color: colors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chevron extends StatelessWidget {
  const _Chevron({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 30,
      height: 30,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color.withValues(alpha: 0.10),
      ),
      child: Icon(Icons.chevron_right_rounded, size: 19, color: color),
    );
  }
}

Widget _securityIcon(BuildContext context, IconData icon) {
  final colors = Theme.of(context).colorScheme;
  return Container(
    width: 45,
    height: 45,
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: <Color>[
          colors.primary.withValues(alpha: 0.18),
          colors.secondary.withValues(alpha: 0.10),
        ],
      ),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: colors.primary.withValues(alpha: 0.18)),
    ),
    child: Icon(icon, color: colors.primary, size: 21),
  );
}

class _TinyStatus extends StatelessWidget {
  const _TinyStatus({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .10),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: color.withValues(alpha: .22)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(
          label,
          style: TextStyle(
            color: color,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _StatusDot extends StatelessWidget {
  const _StatusDot({required this.active});
  final bool active;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final statusColor = active ? colors.primary : colors.tertiary;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(
          active ? 'Active' : 'Review',
          style: TextStyle(
            color: statusColor,
            fontSize: 11,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _PrivacyNote extends StatelessWidget {
  const _PrivacyNote();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: colors.primaryContainer,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.shield_outlined,
            color: colors.onPrimaryContainer,
            size: 19,
          ),
          SizedBox(width: 9),
          Expanded(
            child: Text(
              'Your personal information is used to manage your account. Keep your password, withdrawal PIN, and authenticator recovery codes private.',
              style: TextStyle(
                color: colors.onPrimaryContainer,
                fontSize: 11,
                height: 1.45,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
