import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class PaymentDetailsSetupScreen extends StatefulWidget {
  const PaymentDetailsSetupScreen({super.key, required this.api, required this.token, this.user = const {}});
  final AuthApi api;
  final String token;
  final Map<String, dynamic> user;

  @override
  State<PaymentDetailsSetupScreen> createState() => _PaymentDetailsSetupScreenState();
}

class _PaymentDetailsSetupScreenState extends State<PaymentDetailsSetupScreen> {
  bool _loading = true;
  bool _saving = false;
  bool _resolving = false;
  String? _error;
  String _country = 'NG';
  List<Map<String, dynamic>> _options = [];
  List<Map<String, dynamic>> _banks = [];
  List<Map<String, dynamic>> _mobileProviders = [];
  Map<String, dynamic>? _selected;
  Map<String, dynamic>? _selectedBank;
  Map<String, dynamic> _saved = {};
  String? _verifiedName;
  String? _verifiedKey;
  final Map<String, TextEditingController> _fields = {};

  List<String> get _requiredFields => (_selected?['fields'] as List? ?? const []).map((value) => value.toString()).toList();
  bool get _isBankTransfer => _selected?['option_id'] == 'bank_transfer';
  bool get _isMobileMoney => _selected?['option_id'] == 'mobile_money';
  String get _accountNumber => _fields['account_number']?.text.trim() ?? '';
  String get _accountKey => '${_selectedBank?['id']}:$_accountNumber';

  @override
  void initState() { super.initState(); _load(); }

  @override
  void dispose() { for (final controller in _fields.values) { controller.dispose(); } super.dispose(); }

  Future<void> _load() async {
    try {
      final country = (widget.user['country_code']?.toString() ?? 'NG').toUpperCase();
      final optionsFuture = widget.api.get('withdrawal-options', widget.token);
      final profileFuture = widget.api.get('me/payout-profile', widget.token);
      final banksFuture = widget.api.banks(widget.token, country: country);
      final providersFuture = widget.api.banks(widget.token, country: country, type: 'mobile_money');
      await Future.wait<void>([
        optionsFuture.then<void>((_) {}),
        profileFuture.then<void>((_) {}),
        banksFuture.then<void>((_) {}),
        providersFuture.then<void>((_) {}),
      ]);
      List<Map<String, dynamic>> rows(dynamic input) {
        final value = input is Map ? input['data'] : input;
        return value is List ? value.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList() : [];
      }
      final optionsResponse = await optionsFuture;
      final profileResponse = await profileFuture;
      final options = rows(optionsResponse['data']).where((item) => item['is_available'] != false).toList();
      final profile = profileResponse['data'] is Map ? Map<String, dynamic>.from(profileResponse['data'] as Map) : <String, dynamic>{};
      final optionId = int.tryParse(profile['withdrawal_option_id']?.toString() ?? '');
      final details = profile['details'] is Map ? Map<String, dynamic>.from(profile['details'] as Map) : <String, dynamic>{};
      final banks = await banksFuture;
      final providers = await providersFuture;
      final selectedBankId = details['bank_id']?.toString();
      final selectedBankCode = details['bank_code']?.toString();
      final selectedBankName = details['bank_name']?.toString() ?? details['provider']?.toString();
      final selectedBank = [...banks, ...providers].where((bank) =>
        (selectedBankId != null && bank['id']?.toString() == selectedBankId) ||
        (selectedBankCode != null && bank['code']?.toString() == selectedBankCode) ||
        (selectedBankName != null && bank['name']?.toString() == selectedBankName),
      ).firstOrNull;
      if (!mounted) return;
      setState(() {
        _country = country;
        _options = options;
        _banks = banks.where((bank) => bank['type'] != 'mobile_money').toList();
        _mobileProviders = providers;
        _saved = details;
        _selected = options.where((o) => int.tryParse(o['id'].toString()) == optionId).firstOrNull ?? options.firstOrNull;
        _selectedBank = selectedBank;
        _verifiedName = details['account_name']?.toString();
        _loading = false;
      });
      _syncFields();
      if (_verifiedName != null && _isBankTransfer && _selectedBank != null) _verifiedKey = _accountKey;
    } on AuthException catch (error) {
      if (mounted) setState(() { _error = error.message; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _error = 'Unable to load payment methods and banks.'; _loading = false; });
    }
  }

  void _syncFields() {
    for (final field in _requiredFields) {
      _fields.putIfAbsent(field, () => TextEditingController(text: _saved[field]?.toString() ?? ''));
    }
    if (_isMobileMoney && _selectedBank == null) {
      final previous = _saved['provider']?.toString();
      _selectedBank = _mobileProviders.where((bank) => bank['name'] == previous).firstOrNull;
    }
  }

  String _label(String field) => field.replaceAll('_', ' ').split(' ').map((part) => part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}').join(' ');

  Future<Map<String, dynamic>?> _chooseBank(
    List<Map<String, dynamic>> choices,
    String title,
  ) {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (_) => _PayoutProviderPickerSheet(
        choices: choices,
        title: title,
        isMobileMoney: _isMobileMoney,
        selectedId: _selectedBank?['id']?.toString(),
      ),
    );
  }

  Future<void> _selectPayoutProvider() async {
    final choices = _isMobileMoney ? _mobileProviders : _banks;
    final bank = await _chooseBank(choices, _isMobileMoney ? 'Select mobile money provider' : 'Select your bank');
    if (bank == null || !mounted) return;
    setState(() {
      _selectedBank = bank;
      _verifiedName = null;
      _verifiedKey = null;
      if (_isMobileMoney) _fields['provider']?.text = bank['name'].toString();
      if (_isBankTransfer) _fields['bank_name']?.text = bank['name'].toString();
    });
  }

  Future<void> _resolveAccount() async {
    final bank = _selectedBank;
    if (bank == null) { showAuthMessage(context, 'Select your bank first.'); return; }
    if (!RegExp(r'^\d{6,20}$').hasMatch(_accountNumber)) { showAuthMessage(context, 'Enter a valid account number.'); return; }
    setState(() { _resolving = true; _verifiedName = null; _verifiedKey = null; });
    try {
      final response = await widget.api.post('me/payout-profile/resolve-account', widget.token, {'bank_id': bank['id'], 'account_number': _accountNumber});
      final data = response['data'];
      final name = data is Map ? data['account_name']?.toString() : null;
      if (name == null || name.trim().isEmpty) throw const AuthException('The bank did not return an account name. Check the details and try again.');
      if (!mounted) return;
      setState(() { _verifiedName = name.trim(); _verifiedKey = _accountKey; _resolving = false; });
      showAuthMessage(context, 'Account name verified.');
    } on AuthException catch (error) {
      if (mounted) { setState(() { _resolving = false; _verifiedName = null; _verifiedKey = null; }); showAuthMessage(context, error.message); }
    }
  }

  Future<void> _save() async {
    final option = _selected;
    if (option == null) return;
    final details = {for (final field in _requiredFields) field: _fields[field]?.text.trim() ?? ''};
    if (_isBankTransfer) {
      if (_selectedBank == null) { showAuthMessage(context, 'Select your bank.'); return; }
      if (_verifiedName == null || _verifiedKey != _accountKey) { showAuthMessage(context, 'Verify the account number to retrieve its account name before saving.'); return; }
      details['bank_name'] = _selectedBank!['name'].toString();
      details['account_name'] = _verifiedName!;
    } else if (_isMobileMoney && _selectedBank == null) {
      showAuthMessage(context, 'Select your mobile money provider.'); return;
    }
    if (details.values.any((value) => value.isEmpty)) { showAuthMessage(context, 'Complete all payment details.'); return; }
    setState(() => _saving = true);
    try {
      await widget.api.put('me/payout-profile', widget.token, {'withdrawal_option_id': option['id'], 'details': details, if (_selectedBank != null) 'bank_id': _selectedBank!['id']});
      if (!mounted) return;
      showAuthMessage(context, 'Payment details saved.');
      Navigator.of(context).pop(true);
    } on AuthException catch (error) { if (mounted) showAuthMessage(context, error.message); }
    finally { if (mounted) setState(() => _saving = false); }
  }

  Widget _textField(String field) {
    final theme = Theme.of(context);
    final isNumber = field == 'phone_number' || field == 'account_number';
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextField(
        controller: _fields[field],
        keyboardType: isNumber ? TextInputType.phone : TextInputType.text,
        inputFormatters: field == 'account_number'
            ? [FilteringTextInputFormatter.digitsOnly]
            : null,
        onChanged: field == 'account_number'
            ? (_) => setState(() {
                  _verifiedName = null;
                  _verifiedKey = null;
                })
            : null,
        decoration: InputDecoration(
          labelText: _label(field),
          hintText: field == 'account_number'
              ? 'Enter your account number'
              : field == 'phone_number'
                  ? 'Enter your mobile number'
                  : null,
          prefixIcon: Icon(
            field == 'account_number'
                ? Icons.numbers_rounded
                : field == 'phone_number'
                    ? Icons.phone_iphone_rounded
                    : Icons.edit_outlined,
          ),
          filled: true,
          fillColor: theme.colorScheme.surfaceContainerHighest.withOpacity(.35),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 17,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(15),
            borderSide: BorderSide(color: theme.dividerColor.withOpacity(.5)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(15),
            borderSide: BorderSide(color: theme.dividerColor.withOpacity(.45)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(15),
            borderSide: BorderSide(color: theme.colorScheme.primary, width: 1.5),
          ),
        ),
      ),
    );
  }

  Widget _sectionLabel(String title, {String? subtitle}) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
            )),
            if (subtitle != null) ...[
              const SizedBox(height: 4),
              Text(subtitle, style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontSize: 12,
                height: 1.4,
              )),
            ],
          ],
        ),
      );

  Widget _methodCard(Map<String, dynamic> option) {
    final theme = Theme.of(context);
    final selected = _selected?['id']?.toString() == option['id']?.toString();
    final name = option['name']?.toString() ?? 'Payment method';
    final description = option['description']?.toString() ?? '';
    final optionId = option['option_id']?.toString() ?? '';
    final icon = optionId == 'bank_transfer'
        ? Icons.account_balance_rounded
        : optionId == 'mobile_money'
            ? Icons.phone_android_rounded
            : Icons.account_balance_wallet_rounded;

    return InkWell(
      borderRadius: BorderRadius.circular(17),
      onTap: () {
        setState(() {
          _selected = option;
          _selectedBank = null;
          _verifiedName = null;
          _verifiedKey = null;
        });
        _syncFields();
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected
              ? theme.colorScheme.primary.withOpacity(.07)
              : theme.colorScheme.surface,
          borderRadius: BorderRadius.circular(17),
          border: Border.all(
            color: selected
                ? theme.colorScheme.primary.withOpacity(.7)
                : theme.dividerColor.withOpacity(.45),
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: selected
                    ? theme.colorScheme.primary.withOpacity(.13)
                    : theme.colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(icon, color: selected
                  ? theme.colorScheme.primary
                  : theme.colorScheme.onSurfaceVariant),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                  )),
                  if (description.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(description, style: TextStyle(
                      color: theme.colorScheme.onSurfaceVariant,
                      fontSize: 12,
                      height: 1.35,
                    )),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(
              selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
              color: selected ? theme.colorScheme.primary : theme.colorScheme.onSurfaceVariant,
            ),
          ],
        ),
      ),
    );
  }

  Widget _providerSelector() {
    final theme = Theme.of(context);
    final label = _isMobileMoney ? 'Mobile money provider' : 'Bank';
    final icon = _isMobileMoney
        ? Icons.phone_android_rounded
        : Icons.account_balance_rounded;
    return InkWell(
      borderRadius: BorderRadius.circular(15),
      onTap: _selectPayoutProvider,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15),
        decoration: BoxDecoration(
          color: theme.colorScheme.surfaceContainerHighest.withOpacity(.35),
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: theme.dividerColor.withOpacity(.5)),
        ),
        child: Row(
          children: [
            Icon(icon, color: theme.colorScheme.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: TextStyle(
                    fontSize: 11,
                    color: theme.colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  )),
                  const SizedBox(height: 3),
                  Text(
                    _selectedBank?['name']?.toString() ??
                        (_isMobileMoney ? 'Choose a provider' : 'Choose your bank'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
            const Icon(Icons.keyboard_arrow_down_rounded),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Payment details')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Payment details')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.account_balance_wallet_outlined,
                    size: 46, color: colors.error),
                const SizedBox(height: 12),
                Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: () {
                    setState(() {
                      _loading = true;
                      _error = null;
                    });
                    _load();
                  },
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Try again'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Payment details',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        scrolledUnderElevation: 0,
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(22),
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    colors.primary,
                    Color.lerp(colors.primary, colors.tertiary, .35)!,
                  ],
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(.16),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.account_balance_wallet_rounded,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 13),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Where should we send your money?',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        SizedBox(height: 6),
                        Text(
                          'Choose a payout method and add the details for your approved withdrawals.',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            height: 1.45,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            _sectionLabel(
              'Choose a payout method',
              subtitle: 'Select how you would like to receive your withdrawals.',
            ),
            ..._options.map(_methodCard),
            if (_selected != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: colors.surface,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: theme.dividerColor.withOpacity(.45)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Payout information',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: colors.primary.withOpacity(.09),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            'Required',
                            style: TextStyle(
                              color: colors.primary,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      'Enter accurate details so your payout can be processed correctly.',
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 12,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_isBankTransfer) ...[
                      _providerSelector(),
                      const SizedBox(height: 14),
                      _textField('account_number'),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: _resolving ? null : _resolveAccount,
                          icon: _resolving
                              ? const SizedBox(
                                  width: 17,
                                  height: 17,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.verified_user_outlined),
                          label: Text(
                            _resolving
                                ? 'Verifying account…'
                                : 'Verify account details',
                          ),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(48),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                        ),
                      ),
                      if (_verifiedName != null && _verifiedKey == _accountKey) ...[
                        const SizedBox(height: 13),
                        Container(
                          padding: const EdgeInsets.all(13),
                          decoration: BoxDecoration(
                            color: colors.primaryContainer.withOpacity(.65),
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: colors.primary.withOpacity(.25),
                            ),
                          ),
                          child: Row(
                            children: [
                              Icon(Icons.check_circle_rounded,
                                  color: colors.primary),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Account verified',
                                      style: TextStyle(
                                        color: colors.primary,
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      _verifiedName!,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ] else if (_isMobileMoney) ...[
                      _providerSelector(),
                      const SizedBox(height: 14),
                      _textField('phone_number'),
                    ] else ...[
                      for (final field in _requiredFields) _textField(field),
                    ],
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _saving ? null : _save,
                        style: FilledButton.styleFrom(
                          backgroundColor: colors.primary,
                          minimumSize: const Size.fromHeight(53),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(15),
                          ),
                        ),
                        child: _saving
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.lock_outline_rounded, size: 18),
                                  SizedBox(width: 8),
                                  Text(
                                    'Save payment details',
                                    style: TextStyle(fontWeight: FontWeight.w800),
                                  ),
                                ],
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.shield_outlined,
                    size: 17, color: colors.onSurfaceVariant),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Your payout information is used to process withdrawals. Bank account names must be verified before saving.',
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontSize: 11,
                      height: 1.45,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}


class _PayoutProviderPickerSheet extends StatefulWidget {
  const _PayoutProviderPickerSheet({
    required this.choices,
    required this.title,
    required this.isMobileMoney,
    this.selectedId,
  });

  final List<Map<String, dynamic>> choices;
  final String title;
  final bool isMobileMoney;
  final String? selectedId;

  @override
  State<_PayoutProviderPickerSheet> createState() =>
      _PayoutProviderPickerSheetState();
}

class _PayoutProviderPickerSheetState
    extends State<_PayoutProviderPickerSheet> {
  late final TextEditingController _searchController;
  late final FocusNode _searchFocus;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
    _searchFocus = FocusNode();
  }

  @override
  void dispose() {
    _searchFocus.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _closeWith(Map<String, dynamic> choice) {
    // Dismiss the keyboard before the route and its text field are disposed.
    _searchFocus.unfocus();
    Navigator.of(context).pop(choice);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final media = MediaQuery.of(context);
    final keyboardInset = media.viewInsets.bottom;
    final availableHeight = (media.size.height - keyboardInset)
        .clamp(240.0, media.size.height)
        .toDouble();
    final sheetHeight = availableHeight * .78;
    final needle = _query.trim().toLowerCase();

    final matches = widget.choices.where((bank) {
      final name = bank['name']?.toString().toLowerCase() ?? '';
      final code = bank['code']?.toString().toLowerCase() ?? '';
      return needle.isEmpty || name.contains(needle) || code.contains(needle);
    }).toList();

    return AnimatedPadding(
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOut,
      padding: EdgeInsets.only(bottom: keyboardInset),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: sheetHeight,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 2, 20, 5),
                child: Text(
                  widget.title,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 14),
                child: Text(
                  widget.isMobileMoney
                      ? 'Choose the wallet provider linked to your mobile number.'
                      : 'Search for your bank by name or bank code.',
                  style: TextStyle(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontSize: 12,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: TextField(
                  controller: _searchController,
                  focusNode: _searchFocus,
                  textInputAction: TextInputAction.search,
                  onChanged: (value) => setState(() => _query = value),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.search_rounded),
                    hintText: widget.isMobileMoney
                        ? 'Search wallet providers'
                        : 'Search banks',
                    suffixIcon: _query.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'Clear search',
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _query = '');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                    filled: true,
                    fillColor: theme.colorScheme.surfaceContainerHighest
                        .withOpacity(.4),
                    contentPadding: const EdgeInsets.symmetric(vertical: 13),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(15),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  children: [
                    Text(
                      widget.isMobileMoney
                          ? 'AVAILABLE WALLETS'
                          : 'AVAILABLE BANKS',
                      style: TextStyle(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: .8,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${matches.length}',
                      style: TextStyle(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 5),
              Expanded(
                child: matches.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.search_off_rounded,
                                size: 36,
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                              const SizedBox(height: 8),
                              const Text(
                                'No matching providers found.',
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        ),
                      )
                    : ListView.separated(
                        keyboardDismissBehavior:
                            ScrollViewKeyboardDismissBehavior.onDrag,
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                        itemCount: matches.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: 5),
                        itemBuilder: (context, index) {
                          final bank = matches[index];
                          final selected =
                              widget.selectedId == bank['id']?.toString();

                          return Material(
                            color: selected
                                ? theme.colorScheme.primary.withOpacity(.08)
                                : theme.colorScheme.surface,
                            borderRadius: BorderRadius.circular(14),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(14),
                              onTap: () => _closeWith(bank),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 13,
                                  vertical: 12,
                                ),
                                child: Row(
                                  children: [
                                    Container(
                                      width: 40,
                                      height: 40,
                                      decoration: BoxDecoration(
                                        color: theme.colorScheme
                                            .surfaceContainerHighest,
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: Icon(
                                        widget.isMobileMoney
                                            ? Icons.phone_android_rounded
                                            : Icons.account_balance_rounded,
                                        color: theme.colorScheme.primary,
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            bank['name']?.toString() ?? '',
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                              fontSize: 13,
                                            ),
                                          ),
                                          if ((bank['code']?.toString() ?? '')
                                              .isNotEmpty) ...[
                                            const SizedBox(height: 3),
                                            Text(
                                              bank['code'].toString(),
                                              style: TextStyle(
                                                color: theme.colorScheme
                                                    .onSurfaceVariant,
                                                fontSize: 11,
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                    Icon(
                                      selected
                                          ? Icons.check_circle_rounded
                                          : Icons.chevron_right_rounded,
                                      color: selected
                                          ? theme.colorScheme.primary
                                          : theme.colorScheme
                                              .onSurfaceVariant,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
