import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../services/auth_api.dart';
import '../services/media_url.dart';
import '../widgets/auth_components.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    required this.api,
    required this.token,
    required this.user,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

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
    final current = TextEditingController();
    final next = TextEditingController();
    final confirm = TextEditingController();
    final formKey = GlobalKey<FormState>();
    final submit = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Change password'),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: current,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Current password',
                ),
                validator: (value) => value == null || value.isEmpty
                    ? 'Enter your current password'
                    : null,
              ),
              TextFormField(
                controller: next,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'New password'),
                validator: (value) => value == null || value.length < 10
                    ? 'Use at least 10 characters'
                    : null,
              ),
              TextFormField(
                controller: confirm,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Confirm new password',
                ),
                validator: (value) =>
                    value != next.text ? 'Passwords do not match' : null,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (formKey.currentState!.validate()) {
                Navigator.pop(dialogContext, true);
              }
            },
            child: const Text('Update'),
          ),
        ],
      ),
    );
    if (submit == true) {
      try {
        await widget.api.put('me/password', widget.token, {
          'current_password': current.text,
          'password': next.text,
          'password_confirmation': confirm.text,
        });
        if (mounted) showAuthMessage(context, 'Password updated.');
      } on AuthException catch (error) {
        if (mounted) showAuthMessage(context, error.message);
      }
    }
    current.dispose();
    next.dispose();
    confirm.dispose();
  }

  Future<void> _manageSecurityPin() async {
    if (_securityBusy) return;
    final pinIsSet = _user['security_pin_set'] == true;
    setState(() => _securityBusy = true);
    try {
      final response = await showDialog<Map<String, dynamic>>(
        context: context,
        builder: (_) => _SecurityPinDialog(
          api: widget.api,
          token: widget.token,
          pinIsSet: pinIsSet,
          hasPassword: _user['has_password'] == true,
        ),
      );
      if (response?['data'] is Map<String, dynamic> && mounted) {
        setState(() => _user.addAll(response!['data'] as Map<String, dynamic>));
        showAuthMessage(
          context,
          response?['message']?.toString() ?? 'PIN updated.',
        );
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
              if (dialogContext.mounted)
                showAuthMessage(dialogContext, 'Recovery codes copied.');
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
    final theme = Theme.of(context);
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

    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Edit profile' : 'My profile'),
        actions: [
          if (_editing)
            TextButton(
              onPressed: _saving
                  ? null
                  : () => setState(() {
                      _editing = false;
                      _fillControllers();
                    }),
              child: const Text('Cancel'),
            )
          else
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: TextButton.icon(
                onPressed: () => setState(() => _editing = true),
                icon: const Icon(Icons.edit_outlined, size: 18),
                label: const Text('Edit'),
              ),
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          _ProfileHero(
            name: name.isEmpty ? 'Your profile' : name,
            username: user['username']?.toString() ?? '',
            email: email,
            avatarUrl: avatar,
            coverUrl: cover,
            verified: user['email_verified'] == true,
          ),
          const SizedBox(height: 16),
          if (_editing) ...[
            if (_mediaBusy) const LinearProgressIndicator(minHeight: 2),
            _SectionCard(
              title: 'Profile photos',
              subtitle: 'Add a photo and cover to personalize your profile.',
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
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: _saving ? null : _save,
                      icon: _saving
                          ? const SizedBox(
                              width: 17,
                              height: 17,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.check_rounded),
                      label: Text(_saving ? 'Saving…' : 'Save changes'),
                    ),
                  ),
                ],
              ),
            ),
          ] else ...[
            _SectionCard(
              title: 'Personal details',
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
                        ? Icon(
                            Icons.verified_rounded,
                            color: theme.colorScheme.primary,
                            size: 18,
                          )
                        : null,
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
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            _SectionCard(
              title: 'Account',
              child: Column(
                children: [
                  _DetailRow(
                    icon: Icons.calendar_month_outlined,
                    label: 'Member since',
                    value: _formatDate(user['created_at']?.toString()),
                  ),
                  _DetailRow(
                    icon: Icons.shield_outlined,
                    label: 'Account status',
                    value: _statusLabel(user['status']?.toString()),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            _SectionCard(
              title: 'Security',
              subtitle: 'Protect your account and withdrawals.',
              child: Column(
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor: theme.colorScheme.primaryContainer,
                      child: Icon(
                        Icons.lock_reset_rounded,
                        color: theme.colorScheme.primary,
                      ),
                    ),
                    title: const Text(
                      'Password',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: const Text('Change your account password'),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: _changePassword,
                  ),
                  const Divider(height: 1),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor: theme.colorScheme.primaryContainer,
                      child: Icon(
                        Icons.pin_rounded,
                        color: theme.colorScheme.primary,
                      ),
                    ),
                    title: const Text(
                      'Withdrawal PIN',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      user['security_pin_set'] == true
                          ? 'Required before every withdrawal'
                          : 'Add a 4-digit PIN for withdrawals',
                    ),
                    trailing: TextButton(
                      onPressed: _securityBusy ? null : _manageSecurityPin,
                      child: Text(
                        user['security_pin_set'] == true ? 'Manage' : 'Set up',
                      ),
                    ),
                    onTap: _securityBusy ? null : _manageSecurityPin,
                  ),
                  if (user['security_pin_set'] == true)
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton.icon(
                        onPressed: _securityBusy ? null : _removeSecurityPin,
                        icon: const Icon(
                          Icons.delete_outline_rounded,
                          size: 18,
                        ),
                        label: const Text('Remove withdrawal PIN'),
                      ),
                    ),
                  const Divider(height: 1),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    secondary: CircleAvatar(
                      backgroundColor: theme.colorScheme.primaryContainer,
                      child: Icon(
                        Icons.phonelink_lock_rounded,
                        color: theme.colorScheme.primary,
                      ),
                    ),
                    title: const Text(
                      'Google Authenticator',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      user['two_factor'] == true
                          ? 'A verification code is required after sign in'
                          : 'Add a second verification step at sign in',
                    ),
                    value: user['two_factor'] == true,
                    onChanged: _securityBusy
                        ? null
                        : (enabled) =>
                              enabled ? _setupTwoFactor() : _disableTwoFactor(),
                  ),
                ],
              ),
            ),
          ],
        ],
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

class _SecurityPinDialog extends StatefulWidget {
  const _SecurityPinDialog({
    required this.api,
    required this.token,
    required this.pinIsSet,
    required this.hasPassword,
  });

  final AuthApi api;
  final String token;
  final bool pinIsSet;
  final bool hasPassword;

  @override
  State<_SecurityPinDialog> createState() => _SecurityPinDialogState();
}

class _SecurityPinDialogState extends State<_SecurityPinDialog> {
  final _formKey = GlobalKey<FormState>();
  final _currentPin = TextEditingController();
  final _newPin = TextEditingController();
  final _confirmPin = TextEditingController();
  final _password = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _currentPin.dispose();
    _newPin.dispose();
    _confirmPin.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final result = await widget.api.put('me/security-pin', widget.token, {
        'pin': _newPin.text,
        'pin_confirmation': _confirmPin.text,
        if (widget.pinIsSet) 'current_pin': _currentPin.text,
        if (!widget.pinIsSet && widget.hasPassword)
          'current_password': _password.text,
      });
      if (mounted) Navigator.of(context).pop(result);
    } on AuthException catch (error) {
      if (mounted) {
        setState(() => _saving = false);
        showAuthMessage(context, error.message);
      }
    }
  }

  Widget _pinInput(
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

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      child: SizedBox(
        width: size.width < 432 ? size.width - 40 : 392,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: size.height * 0.84),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 22, 24, 8),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    widget.pinIsSet
                        ? 'Change withdrawal PIN'
                        : 'Set withdrawal PIN',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
              ),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          widget.pinIsSet
                              ? 'Enter your current PIN, then choose a new 4-digit PIN.'
                              : 'This 4-digit PIN will be required before every withdrawal request.',
                        ),
                        if (widget.pinIsSet) ...[
                          const SizedBox(height: 14),
                          _pinInput(_currentPin, 'Current PIN'),
                        ] else if (widget.hasPassword) ...[
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _password,
                            obscureText: true,
                            decoration: const InputDecoration(
                              labelText: 'Account password',
                            ),
                            validator: (value) => value == null || value.isEmpty
                                ? 'Confirm your account password'
                                : null,
                          ),
                        ],
                        const SizedBox(height: 14),
                        _pinInput(_newPin, 'New 4-digit PIN'),
                        const SizedBox(height: 12),
                        _pinInput(
                          _confirmPin,
                          'Confirm PIN',
                          validator: (value) => value != _newPin.text
                              ? 'The PINs do not match.'
                              : null,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: Wrap(
                  alignment: WrapAlignment.end,
                  spacing: 8,
                  children: [
                    TextButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      child: const Text('Cancel'),
                    ),
                    FilledButton(
                      onPressed: _saving ? null : _submit,
                      child: Text(
                        _saving
                            ? 'Saving…'
                            : widget.pinIsSet
                            ? 'Update PIN'
                            : 'Set PIN',
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
      child: SizedBox(
        width: screen.width < 432 ? screen.width - 40 : 392,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: screen.height * 0.84),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 22, 24, 8),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Set up Google Authenticator',
                    style: Theme.of(context).textTheme.titleLarge,
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
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
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
                          if (context.mounted) {
                            showAuthMessage(context, 'Setup key copied.');
                          }
                        },
                        icon: const Icon(Icons.copy_rounded, size: 18),
                        label: const Text('Copy setup key'),
                      ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _code,
                        keyboardType: TextInputType.number,
                        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
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
                      icon: _busy
                          ? const SizedBox.square(
                              dimension: 17,
                              child: CircularProgressIndicator(strokeWidth: 2),
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
  });

  final String name;
  final String username;
  final String email;
  final String? avatarUrl;
  final String? coverUrl;
  final bool verified;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 132,
            width: double.infinity,
            decoration: BoxDecoration(
              color: scheme.primaryContainer,
              image: coverUrl == null
                  ? null
                  : DecorationImage(
                      image: NetworkImage(coverUrl!),
                      fit: BoxFit.cover,
                    ),
            ),
            child: coverUrl == null
                ? Align(
                    alignment: Alignment.topRight,
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Icon(
                        Icons.person_pin_circle_outlined,
                        size: 45,
                        color: scheme.onPrimaryContainer.withValues(alpha: .35),
                      ),
                    ),
                  )
                : null,
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Transform.translate(
              offset: const Offset(0, -33),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    radius: 39,
                    backgroundColor: theme.cardColor,
                    child: CircleAvatar(
                      radius: 35,
                      backgroundColor: scheme.secondaryContainer,
                      backgroundImage: avatarUrl == null
                          ? null
                          : NetworkImage(avatarUrl!),
                      child: avatarUrl == null
                          ? Icon(
                              Icons.person_outline_rounded,
                              size: 38,
                              color: scheme.onSecondaryContainer,
                            )
                          : null,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      if (verified)
                        Icon(
                          Icons.verified_rounded,
                          size: 19,
                          color: scheme.primary,
                        ),
                    ],
                  ),
                  if (username.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      '@$username',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: scheme.primary,
                      ),
                    ),
                  ],
                  if (email.isNotEmpty) ...[
                    const SizedBox(height: 5),
                    Text(
                      email,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child, this.subtitle});
  final String title;
  final String? subtitle;
  final Widget child;
  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
          const SizedBox(height: 12),
          child,
        ],
      ),
    ),
  );
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
    final scheme = theme.colorScheme;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            children: [
              Icon(icon, size: 19, color: scheme.primary),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      value == null || value!.isEmpty ? 'Not added' : value!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
        ),
        if (!last) const Divider(height: 1),
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
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon),
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
    final scheme = Theme.of(context).colorScheme;
    Widget preview;
    if (isAvatar) {
      preview = CircleAvatar(
        radius: 26,
        backgroundColor: scheme.secondaryContainer,
        backgroundImage: url == null ? null : NetworkImage(url!),
        child: url == null
            ? Icon(Icons.person_outline, color: scheme.onSecondaryContainer)
            : null,
      );
    } else {
      preview = ClipRRect(
        borderRadius: BorderRadius.circular(9),
        child: SizedBox(
          width: 76,
          height: 48,
          child: url == null
              ? ColoredBox(
                  color: scheme.secondaryContainer,
                  child: Icon(
                    Icons.panorama_outlined,
                    color: scheme.onSecondaryContainer,
                  ),
                )
              : Image.network(url!, fit: BoxFit.cover),
        ),
      );
    }
    return Row(
      children: [
        preview,
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        TextButton(
          onPressed: busy ? null : onPick,
          child: Text(url == null ? 'Add' : 'Change'),
        ),
        if (onRemove != null)
          IconButton(
            onPressed: busy ? null : onRemove,
            tooltip: 'Remove $title',
            icon: const Icon(Icons.delete_outline_rounded),
          ),
      ],
    );
  }
}
