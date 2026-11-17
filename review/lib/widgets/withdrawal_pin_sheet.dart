import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/auth_api.dart';

/// Opens the reusable "Set a withdrawal PIN" bottom sheet.
///
/// The sheet collects a new PIN, asks the user to confirm it on a second slide,
/// validates both entries locally and finally sends:
///
/// ```json
/// {"pin": "1234", "pin_confirmation": "1234"}
/// ```
///
/// When [requireCurrentPin] is true the flow gains a first slide that asks for
/// the existing PIN and the body also carries `current_pin`. When
/// [requireAccountPassword] is true a small password prompt appears after the
/// confirmed PIN and its value is sent as `current_password`. Together those
/// options let the same widget cover "set" and "change".
///
/// [endpoint] is the API path for the request — relative paths are resolved
/// against `ApiConfig.baseUrl` (see `api_config.dart`), and an absolute URL is
/// used as-is. [extraFields] are merged into the body (e.g. an OTP), and
/// [onSuccess] receives the decoded response.
///
/// Returns `true` when the PIN was saved, `false` when the sheet was dismissed.
///
/// ```dart
/// final saved = await showWithdrawalPinSheet(
///   context,
///   api: widget.api,
///   token: widget.token,
///   endpoint: 'me/withdrawal-pin',
/// );
/// ```
Future<bool> showWithdrawalPinSheet(
  BuildContext context, {
  required AuthApi api,
  required String token,
  required String endpoint,
  bool requireCurrentPin = false,
  bool requireAccountPassword = false,
  int pinLength = 4,
  String title = 'Set a withdrawal PIN',
  Map<String, dynamic> extraFields = const <String, dynamic>{},
  void Function(Map<String, dynamic> response)? onSuccess,
}) async {
  // Tracks success separately so a swipe-dismiss on the success view still
  // reports `true` to the caller.
  var saved = false;
  final result = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    backgroundColor: Theme.of(context).colorScheme.surface,
    builder: (_) => WithdrawalPinSheet(
      api: api,
      token: token,
      endpoint: endpoint,
      requireCurrentPin: requireCurrentPin,
      requireAccountPassword: requireAccountPassword,
      pinLength: pinLength,
      title: title,
      extraFields: extraFields,
      onSuccess: (response) {
        saved = true;
        onSuccess?.call(response);
      },
    ),
  );
  return saved || (result ?? false);
}

Future<String?> _requestCurrentPassword(BuildContext context) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    backgroundColor: Theme.of(context).colorScheme.surface,
    builder: (_) => const _AccountPasswordSheet(),
  );
}

/// Password prompt shown before the PIN is saved.
///
/// The controller lives in this widget's [State] and is disposed with it, so a
/// closing sheet (cancel, confirm or swipe-down) can keep rendering its
/// TextField through the route's exit animation without ever touching a
/// disposed controller.
class _AccountPasswordSheet extends StatefulWidget {
  const _AccountPasswordSheet();

  @override
  State<_AccountPasswordSheet> createState() => _AccountPasswordSheetState();
}

class _AccountPasswordSheetState extends State<_AccountPasswordSheet> {
  final TextEditingController _controller = TextEditingController();
  bool _obscured = true;

  @override
  void initState() {
    super.initState();
    _controller.addListener(_onTextChanged);
  }

  void _onTextChanged() {
    if (!mounted) return;
    setState(() {});
  }

  @override
  void dispose() {
    _controller.removeListener(_onTextChanged);
    _controller.dispose();
    super.dispose();
  }

  String get _value => _controller.text.trim();

  void _submit() {
    if (_value.isEmpty) return;
    Navigator.of(context).pop(_value);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        24,
        8,
        24,
        24 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Confirm your password',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w800,
              color: colors.onSurface,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Enter your account password to finish setting your withdrawal PIN.',
            style: TextStyle(color: colors.onSurfaceVariant),
          ),
          const SizedBox(height: 18),
          TextField(
            controller: _controller,
            autofocus: true,
            obscureText: _obscured,
            textInputAction: TextInputAction.done,
            autofillHints: const [AutofillHints.password],
            onSubmitted: (_) => _submit(),
            decoration: InputDecoration(
              labelText: 'Account password',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: IconButton(
                onPressed: () => setState(() => _obscured = !_obscured),
                tooltip: _obscured ? 'Show password' : 'Hide password',
                icon: Icon(
                  _obscured
                      ? Icons.visibility_rounded
                      : Icons.visibility_off_rounded,
                ),
              ),
            ),
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Cancel'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  onPressed: _value.isEmpty ? null : _submit,
                  child: const Text('Confirm'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// The bottom sheet behind [showWithdrawalPinSheet].
///
/// Embed it directly (`showModalBottomSheet(builder: ...)`) when a screen needs
/// its own sheet chrome.
class WithdrawalPinSheet extends StatefulWidget {
  const WithdrawalPinSheet({
    super.key,
    required this.api,
    required this.token,
    required this.endpoint,
    this.requireCurrentPin = false,
    this.requireAccountPassword = false,
    this.pinLength = 4,
    this.title = 'Set a withdrawal PIN',
    this.extraFields = const <String, dynamic>{},
    this.onSuccess,
  });

  final AuthApi api;
  final String token;

  /// API path (relative to `ApiConfig.baseUrl`) or a full URL.
  final String endpoint;

  /// Adds the "current PIN" slide and sends `current_pin` in the body.
  final bool requireCurrentPin;

  /// Requests the account password after PIN confirmation and sends it with
  /// the update request.
  final bool requireAccountPassword;

  /// Number of digits in the PIN. Defaults to 4.
  final int pinLength;

  /// Headline of the "choose a new PIN" slide.
  final String title;

  /// Extra fields merged into the POST body.
  final Map<String, dynamic> extraFields;

  final void Function(Map<String, dynamic> response)? onSuccess;

  @override
  State<WithdrawalPinSheet> createState() => _WithdrawalPinSheetState();
}

enum _PinStep { current, choose, confirm }

class _WithdrawalPinSheetState extends State<WithdrawalPinSheet> {
  static const Duration _slideDuration = Duration(milliseconds: 320);
  static const Duration _autoAdvanceDelay = Duration(milliseconds: 160);

  late final List<_PinStep> _steps;
  late final List<String> _entries;
  late final PageController _pages;

  int _step = 0;
  String? _error;
  bool _saving = false;
  bool _saved = false;

  @override
  void initState() {
    super.initState();
    _steps = [
      if (widget.requireCurrentPin) _PinStep.current,
      _PinStep.choose,
      _PinStep.confirm,
    ];
    _entries = List<String>.filled(_steps.length, '');
    _pages = PageController();
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  String get _entry => _entries[_step];

  bool get _complete => _entry.length == widget.pinLength;

  int _indexOf(_PinStep step) => _steps.indexOf(step);

  void _typeDigit(int digit) {
    if (_saving || _saved) return;
    final entry = _entries[_step];
    if (entry.length >= widget.pinLength) return;
    HapticFeedback.selectionClick();
    setState(() {
      _entries[_step] = '$entry$digit';
      _error = null;
    });
    if (_entries[_step].length == widget.pinLength) {
      final completedStep = _step;
      Future<void>.delayed(_autoAdvanceDelay, () {
        if (mounted &&
            !_saving &&
            !_saved &&
            _step == completedStep &&
            _entries[completedStep].length == widget.pinLength) {
          _continue();
        }
      });
    }
  }

  void _backspace() {
    if (_saving || _saved) return;
    final entry = _entries[_step];
    if (entry.isEmpty) return;
    HapticFeedback.lightImpact();
    setState(() {
      _entries[_step] = entry.substring(0, entry.length - 1);
      _error = null;
    });
  }

  void _clearEntry() {
    if (_saving || _saved || _entries[_step].isEmpty) return;
    HapticFeedback.lightImpact();
    setState(() {
      _entries[_step] = '';
      _error = null;
    });
  }

  void _goTo(int index) {
    if (index < 0 || index >= _steps.length) return;
    _pages.animateToPage(
      index,
      duration: _slideDuration,
      curve: Curves.easeOutCubic,
    );
  }

  void _onPageChanged(int index) {
    setState(() {
      _step = index;
      _error = null;
    });
  }

  Future<void> _continue() async {
    if (!mounted || _saving || _saved) return;
    final entry = _entries[_step];
    if (entry.length != widget.pinLength) {
      setState(() {
        _error = 'Enter all ${widget.pinLength} digits.';
      });
      return;
    }

    final step = _steps[_step];
    if (step == _PinStep.choose && widget.requireCurrentPin) {
      final current = _entries[_indexOf(_PinStep.current)];
      if (entry == current) {
        HapticFeedback.heavyImpact();
        await _restartFlow(
          'Choose a PIN that is different from your current one.',
        );
        return;
      }
    }

    if (step != _PinStep.confirm) {
      _goTo(_step + 1);
      return;
    }

    final chosen = _entries[_indexOf(_PinStep.choose)];
    if (entry != chosen) {
      HapticFeedback.heavyImpact();
      await _restartFlow("Those PINs don't match. Start again.");
      return;
    }

    await _save();
  }

  Future<void> _restartFlow(String error) async {
    if (!mounted) return;
    setState(() {
      for (var i = 0; i < _entries.length; i++) {
        _entries[i] = '';
      }
      _error = null;
    });
    if (_step != 0 && _pages.hasClients) {
      await _pages.animateToPage(
        0,
        duration: _slideDuration,
        curve: Curves.easeOutCubic,
      );
    }
    if (mounted) setState(() => _error = error);
  }

  Future<void> _save() async {
    final chosen = _entries[_indexOf(_PinStep.choose)];
    final confirmation = _entries[_indexOf(_PinStep.confirm)];
    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final body = <String, dynamic>{
        ...widget.extraFields,
        'pin': chosen,
        'pin_confirmation': confirmation,
        if (widget.requireCurrentPin)
          'current_pin': _entries[_indexOf(_PinStep.current)],
      };
      if (widget.requireAccountPassword &&
          !body.containsKey('current_password')) {
        final password = await _requestCurrentPassword(context);
        if (!mounted) return;
        if (password == null) {
          setState(() {
            _saving = false;
            _error = 'Confirm your account password to save the PIN.';
          });
          return;
        }
        body['current_password'] = password;
      }
      final response = await widget.api.put(
        widget.endpoint,
        widget.token,
        body,
      );
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      widget.onSuccess?.call(response);
      setState(() {
        _saving = false;
        _saved = true;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      HapticFeedback.heavyImpact();
      setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'Could not save your PIN. Please try again.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final media = MediaQuery.of(context);
    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.15,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: media.size.height * .88),
        child: SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            20 + media.viewPadding.bottom,
          ),
          child: _saved ? _success(scheme) : _form(scheme),
        ),
      ),
    );
  }

  Widget _form(ColorScheme scheme) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            height: 174,
            child: PageView(
              controller: _pages,
              physics: const NeverScrollableScrollPhysics(),
              onPageChanged: _onPageChanged,
              children: [
                for (var index = 0; index < _steps.length; index++)
                  _StepPanel(
                    headline: _stepHeadline(index),
                    subtitle: _stepSubtitle(index),
                    length: widget.pinLength,
                    filled: _entries[index].length,
                    error: index == _step ? _error : null,
                    scheme: scheme,
                  ),
              ],
            ),
          ),
          _Keypad(
            scheme: scheme,
            enabled: !_saving && !_saved,
            onDigit: _typeDigit,
            onBackspace: _backspace,
            onClear: _clearEntry,
          ),
          if (_saving) ...[
            const SizedBox(height: 12),
            const Center(child: CircularProgressIndicator()),
          ],
        ],
      ),
    );
  }

  String _stepHeadline(int index) {
    switch (_steps[index]) {
      case _PinStep.current:
        return 'Enter your current PIN';
      case _PinStep.choose:
        return widget.title;
      case _PinStep.confirm:
        return 'Confirm your PIN';
    }
  }

  String _stepSubtitle(int index) {
    switch (_steps[index]) {
      case _PinStep.current:
        return 'Enter your current ${widget.pinLength}-digit PIN.';
      case _PinStep.choose:
        return 'You’ll enter this ${widget.pinLength}-digit PIN every time you withdraw.';
      case _PinStep.confirm:
        return 'Enter the same PIN again to confirm it.';
    }
  }

  Widget _success(ColorScheme scheme) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 68,
            height: 68,
            decoration: BoxDecoration(
              color: scheme.primary.withValues(alpha: .12),
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.check_rounded, size: 36, color: scheme.primary),
          ),
          const SizedBox(height: 18),
          Text(
            'Withdrawal PIN set',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: scheme.onSurface,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'You can now withdraw to your payout account with this PIN.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: scheme.onSurfaceVariant),
          ),
          const SizedBox(height: 22),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Done'),
            ),
          ),
        ],
      ),
    );
  }
}

class _StepPanel extends StatelessWidget {
  const _StepPanel({
    required this.headline,
    required this.subtitle,
    required this.length,
    required this.filled,
    required this.error,
    required this.scheme,
  });

  final String headline;
  final String subtitle;
  final int length;
  final int filled;
  final String? error;
  final ColorScheme scheme;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          headline,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 22,
            height: 1.15,
            fontWeight: FontWeight.w800,
            letterSpacing: -.3,
            color: scheme.onSurface,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          subtitle,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 13,
            height: 1.35,
            color: scheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 18),
        Semantics(
          label: 'PIN, $filled of $length digits entered',
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var slot = 0; slot < length; slot++) ...[
                if (slot > 0) const SizedBox(width: 16),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: slot < filled ? scheme.primary : Colors.transparent,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: error != null ? scheme.error : scheme.primary,
                      width: slot < filled ? 0 : 2,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 32,
          child: error == null
              ? null
              : Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.error_outline_rounded,
                      size: 15,
                      color: scheme.error,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        error!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12, color: scheme.error),
                      ),
                    ),
                  ],
                ),
        ),
      ],
    );
  }
}

class _Keypad extends StatelessWidget {
  const _Keypad({
    required this.scheme,
    required this.enabled,
    required this.onDigit,
    required this.onBackspace,
    required this.onClear,
  });

  final ColorScheme scheme;
  final bool enabled;
  final ValueChanged<int> onDigit;
  final VoidCallback onBackspace;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    Widget key(String label, VoidCallback onTap) {
      return Expanded(
        child: Padding(
          padding: const EdgeInsets.all(5),
          child: SizedBox(
            height: 64,
            child: Material(
              color: scheme.primaryContainer.withValues(alpha: .62),
              borderRadius: BorderRadius.circular(23),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: enabled ? onTap : null,
                child: Center(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 27,
                      fontWeight: FontWeight.w700,
                      color: scheme.onPrimaryContainer,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
    }

    Widget action(IconData icon, VoidCallback onTap, {VoidCallback? onLong}) {
      return Expanded(
        child: Padding(
          padding: const EdgeInsets.all(5),
          child: SizedBox(
            height: 64,
            child: Material(
              color: Colors.transparent,
              borderRadius: BorderRadius.circular(23),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: enabled ? onTap : null,
                onLongPress: enabled ? onLong : null,
                child: Center(
                  child: Icon(icon, size: 27, color: scheme.onSurface),
                ),
              ),
            ),
          ),
        ),
      );
    }

    return Column(
      children: [
        for (final row in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ])
          Row(
            children: [
              for (final digit in row)
                key(digit, () => onDigit(int.parse(digit))),
            ],
          ),
        Row(
          children: [
            const Expanded(child: SizedBox.shrink()),
            key('0', () => onDigit(0)),
            action(Icons.backspace_outlined, onBackspace, onLong: onClear),
          ],
        ),
      ],
    );
  }
}
