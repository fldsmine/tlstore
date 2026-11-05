import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class PayoutScreen extends StatefulWidget {
  const PayoutScreen({
    super.key,
    required this.api,
    required this.token,
    this.walletId,
  });

  final AuthApi api;
  final String token;
  final int? walletId;

  @override
  State<PayoutScreen> createState() => _PayoutScreenState();
}

class _PayoutScreenState extends State<PayoutScreen> {
  final _amount = TextEditingController();
  final Map<String, TextEditingController> _details = {};
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  List<Map<String, dynamic>> _wallets = const [];
  List<Map<String, dynamic>> _options = const [];
  List<Map<String, dynamic>> _requests = const [];
  Map<String, dynamic>? _wallet;
  Map<String, dynamic>? _option;
  bool _securityPinSet = false;

  List<String> get _fields => (_option?['fields'] as List? ?? const [])
      .map((field) => field.toString())
      .toList();
  String get _currency => _wallet?['currency']?.toString() ?? 'USD';
  int get _digits {
    const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF'};
    const three = {'KWD', 'BHD', 'TND'};
    return zero.contains(_currency) ? 0 : (three.contains(_currency) ? 3 : 2);
  }
  double get _enteredAmount =>
      double.tryParse(_amount.text.trim().replaceAll(',', '')) ?? 0;
  double get _feeRate =>
      double.tryParse(_option?['tax']?.toString() ?? '') ?? 0;
  double get _fee => _enteredAmount > 0 ? _enteredAmount * _feeRate / 100 : 0;

  @override
  void initState() {
    super.initState();
    _amount.addListener(_amountChanged);
    _load();
  }

  void _amountChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _amount.removeListener(_amountChanged);
    _amount.dispose();
    for (final controller in _details.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final results = await Future.wait([
        widget.api.get('wallets', widget.token),
        widget.api.get('withdrawal-options', widget.token),
        widget.api.get('withdrawals', widget.token),
        widget.api.get('me', widget.token),
      ]);

      List<Map<String, dynamic>> list(dynamic data) {
        final rows = data is Map<String, dynamic> ? data['data'] : data;
        return rows is List
            ? rows.whereType<Map<String, dynamic>>().toList()
            : const [];
      }

      final wallets = list(results[0]['data'])
          .where((w) => w['type'] != 'topup_fund' &&
              (widget.walletId == null ||
                  (w['id'] as num?)?.toInt() == widget.walletId))
          .toList();
      final options = list(results[1]['data'])
          .where((o) => o['is_available'] != false)
          .toList()
        ..sort((a, b) =>
            ((a['order'] as num?)?.toInt() ?? 999).compareTo(
                (b['order'] as num?)?.toInt() ?? 999));
      final requests = list(results[2]['data']);
      final user = results[3]['data'];
      if (!mounted) return;
      setState(() {
        _wallets = wallets;
        _options = options;
        _requests = requests;
        _securityPinSet =
            user is Map<String, dynamic> && user['security_pin_set'] == true;
        _wallet = wallets.where((w) =>
            (w['id'] as num?)?.toInt() == widget.walletId).firstOrNull ??
            wallets.firstOrNull;
        final oldOptionId = (_option?['id'] as num?)?.toInt();
        _option = options.where((o) =>
            (o['id'] as num?)?.toInt() == oldOptionId).firstOrNull ??
            options.firstOrNull;
        _loading = false;
      });
      _syncDetailFields();
    } on AuthException catch (error) {
      if (mounted) setState(() {
        _error = error.message;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() {
        _error = 'Unable to load payout information. Please try again.';
        _loading = false;
      });
    }
  }

  void _syncDetailFields() {
    for (final field in _fields) {
      _details.putIfAbsent(field, TextEditingController.new);
    }
  }

  Future<void> _submit() async {
    final wallet = _wallet;
    final option = _option;
    if (wallet == null || option == null) {
      showAuthMessage(context, 'Choose a wallet and withdrawal method.');
      return;
    }
    final major = _enteredAmount;
    if (major <= 0) {
      showAuthMessage(context, 'Enter a valid withdrawal amount.');
      return;
    }
    final minimum =
        double.tryParse(option['min_withdrawal']?.toString() ?? '') ?? 0;
    if (minimum > 0 && major < minimum) {
      showAuthMessage(context,
          'The minimum withdrawal for this method is ${_formatMajor(minimum, _currency)}.');
      return;
    }
    final balanceMinor =
        (wallet['available_balance_minor'] as num?)?.toInt() ?? 0;
    final available = balanceMinor / math.pow(10, _digits);
    if (major > available) {
      showAuthMessage(context, 'The amount is higher than your available balance.');
      return;
    }
    final payout = {
      for (final field in _fields)
        field: _details[field]?.text.trim() ?? '',
    };
    if (payout.values.any((value) => value.isEmpty)) {
      showAuthMessage(context, 'Complete all payout details for this method.');
      return;
    }
    final pin = _securityPinSet ? await _askForSecurityPin() : null;
    if (_securityPinSet && pin == null) return;
    if (!mounted) return;
    setState(() => _submitting = true);
    try {
      await widget.api.post('withdrawals', widget.token, {
        'wallet_id': wallet['id'],
        'withdrawal_option_id': option['id'],
        'amount_minor': (major * math.pow(10, _digits)).round(),
        'payout_details': payout,
        'idempotency_key':
            'withdraw-mobile-${DateTime.now().microsecondsSinceEpoch}',
        if (pin != null) 'security_pin': pin,
      });
      if (!mounted) return;
      _amount.clear();
      for (final controller in _details.values) {
        controller.clear();
      }
      showAuthMessage(context,
          'Withdrawal request submitted for admin approval.');
      await _load();
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } catch (_) {
      if (mounted) showAuthMessage(context, 'Could not submit the request. Please try again.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<String?> _askForSecurityPin() {
    final pin = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Confirm withdrawal',
            style: TextStyle(fontWeight: FontWeight.w800)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Enter your 4-digit security PIN to submit this withdrawal request.'),
          const SizedBox(height: 12),
          TextField(
            controller: pin,
            obscureText: true,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            maxLength: 4,
            textAlign: TextAlign.center,
            decoration: const InputDecoration(labelText: 'Security PIN', counterText: ''),
          ),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, pin.text.length == 4 ? pin.text : null),
            child: const Text('Continue'),
          ),
        ],
      ),
    ).whenComplete(pin.dispose);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: const Color(0xFFF5F7F5),
        appBar: AppBar(
          title: const Text('Payout', style: TextStyle(fontWeight: FontWeight.w800)),
          backgroundColor: const Color(0xFFF5F7F5),
          surfaceTintColor: Colors.transparent,
          elevation: 0,
          actions: [
            IconButton(onPressed: _loading ? null : _load,
                tooltip: 'Refresh', icon: const Icon(Icons.refresh_rounded)),
            const SizedBox(width: 5),
          ],
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator(color: brandGreen))
            : _error != null
                ? _errorView()
                : RefreshIndicator(
                    color: brandGreen,
                    onRefresh: _load,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(17, 4, 17, 30),
                      children: [
                        _hero(),
                        const SizedBox(height: 16),
                        _balanceCard(),
                        const SizedBox(height: 22),
                        _heading('Request a payout', 'Choose where your earnings should go.'),
                        const SizedBox(height: 12),
                        _requestCard(),
                        const SizedBox(height: 23),
                        _heading('Recent payout requests', 'Track the progress of your withdrawals.',
                            trailing: _requests.isEmpty ? null : '${_requests.length} total'),
                        const SizedBox(height: 12),
                        if (_requests.isEmpty)
                          _emptyRequests()
                        else
                          ..._requests.take(10).map((r) => Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: _PayoutRow(request: r),
                              )),
                        const SizedBox(height: 10),
                        _securityNote(),
                      ],
                    ),
                  ),
      );

  Widget _errorView() => Center(
        child: Padding(
          padding: const EdgeInsets.all(25),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_rounded, size: 42, color: mutedInk),
            const SizedBox(height: 12),
            const Text('Could not load payouts', style: TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 17)),
            const SizedBox(height: 6),
            Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: mutedInk)),
            TextButton(onPressed: _load, child: const Text('Try again')),
          ]),
        ),
      );

  Widget _hero() => Container(
        padding: const EdgeInsets.all(19),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF104A35), Color(0xFF267451), Color(0xFF31835C)],
          ),
          borderRadius: BorderRadius.circular(21),
          boxShadow: [BoxShadow(color: const Color(0xFF145A3D).withValues(alpha: .16), blurRadius: 18, offset: const Offset(0, 8))],
        ),
        child: Row(children: [
          const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Your earnings,\nyour destination.', style: TextStyle(color: Colors.white, fontSize: 21, height: 1.18, fontWeight: FontWeight.w900)),
            SizedBox(height: 8),
            Text('Withdraw eligible earnings to your preferred payout method.', style: TextStyle(color: Colors.white70, fontSize: 12, height: 1.4)),
            SizedBox(height: 12),
            Row(children: [Icon(Icons.verified_user_outlined, color: Color(0xFFF3D58E), size: 15), SizedBox(width: 6), Text('Secure payout request', style: TextStyle(color: Color(0xFFF3D58E), fontSize: 11, fontWeight: FontWeight.w700))]),
          ])),
          const SizedBox(width: 8),
          Container(width: 65, height: 65, decoration: BoxDecoration(color: Colors.white.withValues(alpha: .12), borderRadius: BorderRadius.circular(19)), child: const Icon(Icons.account_balance_wallet_rounded, color: Color(0xFFF4D991), size: 34)),
        ]),
      );

  Widget _balanceCard() {
    final wallet = _wallet;
    final balance = (wallet?['available_balance_minor'] as num?)?.toInt() ?? 0;
    final currency = wallet?['currency']?.toString() ?? _currency;
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(17), border: Border.all(color: const Color(0xFFE7ECE8))),
      child: Row(children: [
        _bubble(Icons.savings_outlined),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Available to withdraw', style: TextStyle(color: mutedInk, fontSize: 11)),
          const SizedBox(height: 3),
          Text(wallet == null ? 'No eligible wallet' : _formatMinor(balance, currency), style: const TextStyle(color: ink, fontSize: 18, fontWeight: FontWeight.w900)),
        ])),
        if (wallet != null) Container(padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6), decoration: BoxDecoration(color: const Color(0xFFEAF4ED), borderRadius: BorderRadius.circular(20)), child: Text(_pretty(wallet['type']?.toString() ?? 'Earnings'), style: const TextStyle(color: brandGreen, fontSize: 10, fontWeight: FontWeight.w800))),
      ]),
    );
  }

  Widget _requestCard() => Container(
        padding: const EdgeInsets.all(17),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), border: Border.all(color: const Color(0xFFE6EBE7)), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .025), blurRadius: 14, offset: const Offset(0, 5))]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _stepLabel('1', 'Select earnings wallet'),
          const SizedBox(height: 9),
          DropdownButtonFormField<int>(
            value: (_wallet?['id'] as num?)?.toInt(),
            isExpanded: true,
            decoration: _decoration('Earnings wallet', Icons.account_balance_wallet_outlined),
            items: _wallets.map((w) => DropdownMenuItem<int>(
              value: (w['id'] as num).toInt(),
              child: Text('${_pretty(w['type']?.toString() ?? 'Earnings')} • ${_formatMinor((w['available_balance_minor'] as num?)?.toInt() ?? 0, w['currency']?.toString() ?? 'USD')}', overflow: TextOverflow.ellipsis),
            )).toList(),
            onChanged: (id) => setState(() => _wallet = _wallets.where((w) => (w['id'] as num).toInt() == id).firstOrNull),
          ),
          if (_wallets.isEmpty) _notice('No eligible earnings wallet was found. Top-up funds cannot be withdrawn.'),
          const SizedBox(height: 18),
          _stepLabel('2', 'Choose payout method'),
          const SizedBox(height: 9),
          if (_options.isEmpty)
            _notice('There are currently no payout methods available.')
          else
            ..._options.map(_methodTile),
          if (_option?['description'] != null) Padding(padding: const EdgeInsets.only(top: 1), child: Text(_option!['description'].toString(), style: const TextStyle(color: mutedInk, fontSize: 11, height: 1.4))),
          const SizedBox(height: 18),
          _stepLabel('3', 'Enter withdrawal amount'),
          const SizedBox(height: 9),
          TextField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
            decoration: _decoration('Amount ($_currency)', Icons.payments_outlined, hint: _option?['min_withdrawal'] == null ? 'Enter amount' : 'Minimum ${_option!['min_withdrawal']}'),
          ),
          if (_option != null) ...[const SizedBox(height: 9), _feeBreakdown()],
          if (_fields.isNotEmpty) ...[
            const SizedBox(height: 19),
            _stepLabel('4', 'Payout details'),
            const SizedBox(height: 5),
            const Text('Enter the destination details for this method.', style: TextStyle(color: mutedInk, fontSize: 11)),
            const SizedBox(height: 10),
            ..._fields.map((field) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: TextField(
                controller: _details[field],
                keyboardType: _keyboardFor(field),
                textCapitalization: _capitalizationFor(field),
                decoration: _decoration(_pretty(field), _fieldIcon(field), hint: _fieldHint(field)),
              ),
            )),
          ],
          const SizedBox(height: 6),
          SizedBox(width: double.infinity, height: 52, child: FilledButton(
            onPressed: _submitting || _wallets.isEmpty || _options.isEmpty ? null : _submit,
            style: FilledButton.styleFrom(backgroundColor: brandGreen, disabledBackgroundColor: const Color(0xFFB9C8BE), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), elevation: 0),
            child: _submitting
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Row(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.lock_outline_rounded, size: 17), SizedBox(width: 8), Text('Submit payout request', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13)), SizedBox(width: 5), Icon(Icons.arrow_forward_rounded, size: 17)]),
          )),
          const SizedBox(height: 9),
          const Center(child: Text('Your request will be reviewed before it is processed.', textAlign: TextAlign.center, style: TextStyle(color: mutedInk, fontSize: 10))),
        ]),
      );

  Widget _methodTile(Map<String, dynamic> option) {
    final selected = (option['id'] as num?)?.toInt() == (_option?['id'] as num?)?.toInt();
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: () => setState(() { _option = option; _syncDetailFields(); }),
        borderRadius: BorderRadius.circular(14),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
          decoration: BoxDecoration(color: selected ? const Color(0xFFEDF6EF) : Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: selected ? brandGreen : const Color(0xFFE7ECE8), width: selected ? 1.4 : 1)),
          child: Row(children: [
            Container(width: 41, height: 41, alignment: Alignment.center, decoration: BoxDecoration(color: selected ? const Color(0xFFDCEEE1) : const Color(0xFFF3F5F3), borderRadius: BorderRadius.circular(12)), child: Text(option['icon']?.toString() ?? '•', style: const TextStyle(fontSize: 20))),
            const SizedBox(width: 11),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(option['name']?.toString() ?? 'Payout method', style: const TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 13)),
              const SizedBox(height: 3),
              Text('${option['tax'] ?? '0'}% method fee', style: const TextStyle(color: mutedInk, fontSize: 10)),
            ])),
            Icon(selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: selected ? brandGreen : const Color(0xFFADB7B0), size: 21),
          ]),
        ),
      ),
    );
  }

  Widget _feeBreakdown() {
    final minimum = double.tryParse(_option?['min_withdrawal']?.toString() ?? '') ?? 0;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: const Color(0xFFF7F9F7), borderRadius: BorderRadius.circular(13)),
      child: Column(children: [
        if (minimum > 0) _feeLine('Minimum withdrawal', _formatMajor(minimum, _currency)),
        _feeLine('Method fee (${_feeRate.toStringAsFixed(2)}%)', _enteredAmount > 0 ? _formatMajor(_fee, _currency) : '—'),
        const Divider(height: 15, color: Color(0xFFE5EAE6)),
        _feeLine('Estimated payout', _enteredAmount > 0 ? _formatMajor(math.max(0, _enteredAmount - _fee).toDouble(), _currency) : '—', bold: true),
      ]),
    );
  }

  Widget _feeLine(String label, String value, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Expanded(child: Text(label, style: TextStyle(color: bold ? ink : mutedInk, fontSize: bold ? 12 : 10, fontWeight: bold ? FontWeight.w800 : FontWeight.w500))),
          Text(value, style: TextStyle(color: bold ? brandGreen : ink, fontSize: bold ? 13 : 11, fontWeight: FontWeight.w800)),
        ]),
      );

  Widget _emptyRequests() => Container(
        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 18),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(17), border: Border.all(color: const Color(0xFFE7ECE8))),
        child: Column(children: [
          _bubble(Icons.receipt_long_outlined),
          const SizedBox(height: 10),
          const Text('Nothing here yet', style: TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 14)),
          const SizedBox(height: 4),
          const Text('Your payout requests will appear here once submitted.', textAlign: TextAlign.center, style: TextStyle(color: mutedInk, fontSize: 11, height: 1.4)),
        ]),
      );

  Widget _securityNote() => Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(color: const Color(0xFFEFF5F0), borderRadius: BorderRadius.circular(14)),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.shield_outlined, color: brandGreen, size: 19),
          SizedBox(width: 9),
          Expanded(child: Text('For your protection, only withdraw earnings from eligible wallets. Never share your security PIN with anyone.', style: TextStyle(color: Color(0xFF52685A), fontSize: 10, height: 1.45))),
        ]),
      );

  Widget _heading(String title, String subtitle, {String? trailing}) => Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(color: ink, fontSize: 17, fontWeight: FontWeight.w900)),
          const SizedBox(height: 3),
          Text(subtitle, style: const TextStyle(color: mutedInk, fontSize: 11)),
        ])),
        if (trailing != null) Text(trailing, style: const TextStyle(color: brandGreen, fontSize: 10, fontWeight: FontWeight.w800)),
      ]);

  Widget _stepLabel(String number, String label) => Row(children: [
        Container(width: 23, height: 23, alignment: Alignment.center, decoration: const BoxDecoration(color: Color(0xFFEAF3EC), shape: BoxShape.circle), child: Text(number, style: const TextStyle(color: brandGreen, fontSize: 11, fontWeight: FontWeight.w900))),
        const SizedBox(width: 8),
        Text(label, style: const TextStyle(color: ink, fontSize: 12, fontWeight: FontWeight.w800)),
      ]);

  Widget _notice(String message) => Container(
        width: double.infinity,
        margin: const EdgeInsets.only(top: 8),
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(color: const Color(0xFFFFF7E8), borderRadius: BorderRadius.circular(12)),
        child: Row(children: [const Icon(Icons.info_outline_rounded, color: Color(0xFF9B7129), size: 17), const SizedBox(width: 8), Expanded(child: Text(message, style: const TextStyle(color: Color(0xFF80632F), fontSize: 10, height: 1.4)))]),
      );

  InputDecoration _decoration(String label, IconData icon, {String? hint}) => InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: Icon(icon, size: 18, color: const Color(0xFF839188)),
        filled: true,
        fillColor: const Color(0xFFFAFBFA),
        contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFE4EAE5))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFE4EAE5))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: brandGreen, width: 1.4)),
      );

  Widget _bubble(IconData icon) => Container(width: 43, height: 43, decoration: BoxDecoration(color: const Color(0xFFEAF4ED), borderRadius: BorderRadius.circular(13)), child: Icon(icon, color: brandGreen, size: 21));

  TextInputType _keyboardFor(String field) {
    final key = field.toLowerCase();
    if (key.contains('email')) return TextInputType.emailAddress;
    if (key.contains('phone') || key.contains('account_number')) return TextInputType.phone;
    return TextInputType.text;
  }

  TextCapitalization _capitalizationFor(String field) {
    final key = field.toLowerCase();
    return key.contains('email') || key.contains('number') || key.contains('phone')
        ? TextCapitalization.none : TextCapitalization.words;
  }

  String _fieldHint(String field) {
    final key = field.toLowerCase();
    if (key.contains('email')) return 'name@example.com';
    if (key.contains('phone')) return 'Enter mobile number';
    if (key.contains('account_number')) return 'Enter account number';
    if (key.contains('wallet_address')) return 'Paste wallet address';
    return 'Enter ${_pretty(field).toLowerCase()}';
  }

  IconData _fieldIcon(String field) {
    final key = field.toLowerCase();
    if (key.contains('email')) return Icons.alternate_email_rounded;
    if (key.contains('phone')) return Icons.phone_rounded;
    if (key.contains('account')) return Icons.account_balance_outlined;
    if (key.contains('wallet')) return Icons.currency_bitcoin_rounded;
    if (key.contains('bank')) return Icons.account_balance_rounded;
    return Icons.edit_outlined;
  }
}

class _PayoutRow extends StatelessWidget {
  const _PayoutRow({required this.request});
  final Map<String, dynamic> request;

  @override
  Widget build(BuildContext context) {
    final option = request['option'] is Map ? request['option'] as Map : const {};
    final amount = (request['amount_minor'] as num?)?.toInt() ?? 0;
    final fee = (request['fee_minor'] as num?)?.toInt();
    final currency = request['currency']?.toString() ?? 'USD';
    final status = request['status']?.toString() ?? 'pending';
    final date = request['requested_at']?.toString() ?? request['created_at']?.toString();
    final color = _statusColor(status.toLowerCase());
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(17), border: Border.all(color: const Color(0xFFE7ECE8))),
      child: Column(children: [
        Row(children: [
          Container(width: 42, height: 42, alignment: Alignment.center, decoration: BoxDecoration(color: const Color(0xFFEAF3EC), borderRadius: BorderRadius.circular(12)), child: Text(option['icon']?.toString() ?? '💸', style: const TextStyle(fontSize: 19))),
          const SizedBox(width: 11),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(option['name']?.toString() ?? 'Withdrawal', style: const TextStyle(color: ink, fontSize: 13, fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text(date == null ? 'Date unavailable' : _formatDate(date), style: const TextStyle(color: mutedInk, fontSize: 10)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(_formatMinor(amount, currency), style: const TextStyle(color: ink, fontSize: 14, fontWeight: FontWeight.w900)),
            const SizedBox(height: 5),
            Container(padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4), decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(20)), child: Text(_pretty(status), style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800))),
          ]),
        ]),
        if (fee != null) ...[
          const Padding(padding: EdgeInsets.symmetric(vertical: 11), child: Divider(height: 1, color: Color(0xFFEEF1EE))),
          Row(children: [const Expanded(child: Text('Processing fee', style: TextStyle(color: mutedInk, fontSize: 10))), Text(_formatMinor(fee, currency), style: const TextStyle(color: ink, fontSize: 10, fontWeight: FontWeight.w700))]),
        ],
      ]),
    );
  }
}

Color _statusColor(String status) {
  if (['paid', 'completed', 'approved'].contains(status)) return const Color(0xFF218653);
  if (['processing', 'pending', 'review'].contains(status)) return const Color(0xFFB27A20);
  if (['failed', 'rejected', 'cancelled'].contains(status)) return const Color(0xFFC34D45);
  return const Color(0xFF64736A);
}

String _pretty(String value) => value.replaceAll('_', ' ').split(' ').map((word) => word.isEmpty ? word : '${word[0].toUpperCase()}${word.substring(1)}').join(' ');

String _formatMinor(int amount, String currency) {
  const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF'};
  const three = {'KWD', 'BHD', 'TND'};
  final digits = zero.contains(currency) ? 0 : (three.contains(currency) ? 3 : 2);
  const symbols = {'USD': r'$', 'NGN': '₦', 'GBP': '£', 'EUR': '€', 'GHS': '₵'};
  final value = amount / math.pow(10, digits);
  return '${symbols[currency] ?? '$currency '}${value.toStringAsFixed(digits)}';
}

String _formatMajor(double amount, String currency) {
  const zero = {'JPY', 'KRW', 'XOF', 'XAF', 'CLP', 'BIF'};
  const three = {'KWD', 'BHD', 'TND'};
  const symbols = {'USD': r'$', 'NGN': '₦', 'GBP': '£', 'EUR': '€', 'GHS': '₵'};
  final digits = zero.contains(currency) ? 0 : (three.contains(currency) ? 3 : 2);
  return '${symbols[currency] ?? '$currency '}${amount.toStringAsFixed(digits)}';
}

String _formatDate(String value) {
  final date = DateTime.tryParse(value)?.toLocal();
  if (date == null) return value;
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  return '${months[date.month - 1]} ${date.day}, ${date.year} • $hour:$minute ${date.hour >= 12 ? 'PM' : 'AM'}';
}
