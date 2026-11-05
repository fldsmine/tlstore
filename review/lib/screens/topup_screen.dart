import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/auth_api.dart';
import '../widgets/auth_components.dart';

class TopUpScreen extends StatefulWidget {
  const TopUpScreen({super.key, required this.api, required this.token});
  final AuthApi api;
  final String token;
  @override
  State<TopUpScreen> createState() => _TopUpScreenState();
}

class _TopUpScreenState extends State<TopUpScreen> with WidgetsBindingObserver {
  final _amount = TextEditingController();
  final _reference = TextEditingController();
  bool _loading = true;
  bool _sending = false;
  String? _error;
  PlatformFile? _proof;
  List<Map<String, dynamic>> _methods = const [];
  List<Map<String, dynamic>> _topups = const [];
  List<String> _gateways = const [];
  Map<String, List<String>> _gatewayCurrencies = const {};
  String _gateway = 'manual';
  String _onlineCurrency = 'USD';
  Map<String, dynamic>? _pendingCheckout;
  final AppLinks _appLinks = AppLinks();
  StreamSubscription<Uri>? _linkSubscription;
  Map<String, dynamic>? _method;
  bool _manualEnabled = false;
  bool _awaitingCheckoutReturn = false;

  String get _currency => _method?['currency']?.toString().toUpperCase() ?? 'USD';
  int get _digits => _precision(_currency);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _linkSubscription = _appLinks.uriLinkStream.listen(_handlePaymentReturn);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _awaitingCheckoutReturn) {
      _awaitingCheckoutReturn = false;
      final intentId = _pendingCheckout?['id'];
      if (intentId != null) _checkCheckoutStatus(intentId);
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    WidgetsBinding.instance.removeObserver(this);
    _linkSubscription?.cancel();
    super.dispose();
  }

  void _handlePaymentReturn(Uri uri) {
    if (uri.scheme != 'trustlens' || uri.host != 'payment-return') return;
    final id = int.tryParse(uri.queryParameters['intent_id'] ?? '');
    if (id == null || !mounted) return;
    setState(() => _pendingCheckout = {'id': id});
    _checkCheckoutStatus(id);
  }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final data = await Future.wait([
        widget.api.spotlights(),
        widget.api.get('payment-gateways', widget.token),
        widget.api.get('me/topups', widget.token),
        widget.api.refreshUser(widget.token),
      ]);
      final spotlight = data[0]['data'];
      final options = spotlight is Map<String, dynamic> ? spotlight['pay_option'] : null;
      // Some manual methods use names such as CEDIS and USDT, not only ISO 3-letter codes.
      final currencies = options is List
          ? options.whereType<Map<String, dynamic>>()
              .where((option) => RegExp(r'^[A-Za-z]{3,5}$').hasMatch(option['currency']?.toString() ?? '') && option['status'] != false)
              .toList()
          : <Map<String, dynamic>>[];
      final topupData = data[2]['data'];
      final topups = topupData is Map<String, dynamic> ? topupData['data'] : topupData;
      final gateways = data[1]['data'];
      final currentUser = data[3]['data'];
      final preferred = currentUser is Map<String, dynamic> ? currentUser['preferred_currency']?.toString().toUpperCase() : null;
      final gatewayCurrencies = <String, List<String>>{};
      if (gateways is List) {
        for (final value in gateways) {
          if (value is Map<String, dynamic>) {
            final name = value['gateway']?.toString();
            final supported = value['supported_currencies'];
            if (name != null) gatewayCurrencies[name] = supported is List ? supported.map((code) => code.toString().toUpperCase()).toList() : <String>[];
          } else if (value is String) {
            gatewayCurrencies[value] = value == 'manual' ? <String>[] : <String>['NGN'];
          }
        }
      }
      final supportedGateways = gatewayCurrencies.keys.toList();
      final selectedGateway = supportedGateways.contains(_gateway) ? _gateway : supportedGateways.firstOrNull ?? 'manual';
      final allowed = gatewayCurrencies[selectedGateway] ?? const <String>[];
      if (!mounted) return;
      setState(() {
        _methods = currencies;
        _method = currencies.where((m) => m['id'] == _method?['id']).firstOrNull ?? currencies.firstOrNull;
        _topups = topups is List ? topups.whereType<Map<String, dynamic>>().toList() : const [];
        _gateways = supportedGateways;
        _gatewayCurrencies = gatewayCurrencies;
        _gateway = selectedGateway;
        _onlineCurrency = allowed.contains(preferred) ? preferred! : allowed.firstOrNull ?? 'USD';
        _manualEnabled = supportedGateways.contains('manual');
        _loading = false;
      });
    } on AuthException catch (error) {
      if (mounted) setState(() { _error = error.message; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _error = 'Could not load top-up options. Please try again.'; _loading = false; });
    }
  }

  Future<void> _pickProof() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.custom, allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'], allowMultiple: false, withData: true);
    if (result != null && result.files.isNotEmpty && mounted) setState(() => _proof = result.files.first);
  }

  Future<void> _submit() async {
    if (_gateway != 'manual') { await _startCheckout(); return; }
    final method = _method;
    final proof = _proof;
    if (!_manualEnabled) { showAuthMessage(context, 'Manual bank deposits are not active.'); return; }
    if (method == null) { showAuthMessage(context, 'There is no deposit method available.'); return; }
    if (proof?.bytes == null) { showAuthMessage(context, 'Choose an image or PDF payment receipt.'); return; }
    final major = double.tryParse(_amount.text.trim().replaceAll(',', ''));
    if (major == null || major <= 0) { showAuthMessage(context, 'Enter a valid deposit amount.'); return; }
    final minor = (major * _pow10(_digits)).round();
    setState(() => _sending = true);
    try {
      await widget.api.postMultipart(path: 'topups/manual-deposits', token: widget.token, fields: {
        'amount_minor': minor.toString(), 'currency': _currency,
        'idempotency_key': 'topup-mobile-${DateTime.now().microsecondsSinceEpoch}',
        if (_reference.text.trim().isNotEmpty) 'bank_reference': _reference.text.trim(),
      }, fileField: 'proof', fileBytes: proof!.bytes!, fileName: proof.name);
      if (!mounted) return;
      _amount.clear(); _reference.clear(); setState(() => _proof = null);
      showAuthMessage(context, 'Deposit proof submitted for admin approval.');
      await _load();
    } on AuthException catch (error) { if (mounted) showAuthMessage(context, error.message); }
    finally { if (mounted) setState(() => _sending = false); }
  }

  Future<void> _startCheckout() async {
    final currency = _onlineCurrency;
    final allowed = _gatewayCurrencies[_gateway] ?? const <String>[];
    if (!allowed.contains(currency)) { showAuthMessage(context, 'Choose a currency accepted by ${_humanize(_gateway)}.'); return; }
    final digits = _precision(currency);
    final amount = double.tryParse(_amount.text.trim().replaceAll(',', ''));
    if (amount == null || amount <= 0) { showAuthMessage(context, 'Enter a valid top-up amount.'); return; }
    final amountMinor = (amount * _pow10(digits)).round();
    setState(() => _sending = true);
    try {
      final response = await widget.api.post('topups/checkout', widget.token, {
        'amount_minor': amountMinor, 'currency': currency, 'gateway': _gateway,
        'idempotency_key': 'checkout-mobile-${DateTime.now().microsecondsSinceEpoch}',
      });
      final data = response['data'];
      final url = data is Map<String, dynamic> ? data['checkout_url']?.toString() : null;
      final intentId = data is Map<String, dynamic> ? data['intent_id'] : null;
      if (url == null || intentId == null) throw const AuthException('The payment provider did not return a checkout link.');
      final uri = Uri.tryParse(url);
      if (uri == null || uri.scheme != 'https') throw const AuthException('The payment provider returned an invalid secure checkout link.');
      setState(() => _pendingCheckout = {'id': intentId, 'currency': currency, 'amount_minor': amountMinor});
      _awaitingCheckoutReturn = true;
      final opened = await launchUrl(uri, mode: LaunchMode.inAppBrowserView);
      if (!opened && mounted) showAuthMessage(context, 'Could not open the secure checkout. Try again.');
      if (!opened) _awaitingCheckoutReturn = false;
      await _load();
    } on AuthException catch (error) { if (mounted) showAuthMessage(context, error.message); }
    finally { if (mounted) setState(() => _sending = false); }
  }

  Future<void> _checkCheckoutStatus([Object? suppliedIntentId]) async {
    final id = suppliedIntentId ?? _pendingCheckout?['id'];
    if (id == null) return;
    setState(() => _sending = true);
    try {
      final response = await widget.api.post('topups/$id/check', widget.token, const {});
      final data = response['data'];
      final status = data is Map<String, dynamic> ? data['status']?.toString() : null;
      if (status == 'paid') {
        if (mounted) { setState(() => _pendingCheckout = null); showAuthMessage(context, 'Payment received. Your top-up wallet has been credited.'); }
        await _load();
      } else if (mounted) {
        showAuthMessage(context, status == 'failed' ? 'The payment did not complete. You can start another checkout.' : status == 'expired' ? 'This checkout request has expired. If you paid before cancelling, check again shortly or contact support.' : 'Payment is still pending provider confirmation. Try checking again shortly.');
      }
    } on AuthException catch (error) { if (mounted) showAuthMessage(context, error.message); }
    finally { if (mounted) setState(() => _sending = false); }
  }

  Future<void> _cancelTopup(Object? intentId) async {
    if (intentId == null) return;
    final confirm = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
      title: const Text('Cancel this checkout?'),
      content: const Text('The top-up request will be marked expired. If you have already paid, use Check payment to confirm it.'),
      actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep it')), FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Cancel checkout'))],
    ));
    if (confirm != true) return;
    try { await widget.api.post('topups/$intentId/cancel', widget.token, const {}); if (mounted) showAuthMessage(context, 'Checkout request expired.'); await _load(); }
    on AuthException catch (error) { if (mounted) showAuthMessage(context, error.message); }
  }

  @override
  Widget build(BuildContext context) {
    final bg = Theme.of(context).scaffoldBackgroundColor;
    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(title: const Text('Top up wallet', style: TextStyle(fontWeight: FontWeight.w800)), backgroundColor: bg, elevation: 0),
      body: _loading ? const Center(child: CircularProgressIndicator()) : _error != null
          ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.cloud_off_rounded, size: 42, color: mutedInk), const SizedBox(height: 12), Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: mutedInk)), TextButton.icon(onPressed: _load, icon: const Icon(Icons.refresh), label: const Text('Try again'))])))
          : RefreshIndicator(onRefresh: _load, child: ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 32), children: [
              _buildHero(),
              const SizedBox(height: 16),
              _buildPaymentPanel(),
              const SizedBox(height: 23),
              Row(children: [const Expanded(child: Text('Recent top-ups', style: TextStyle(color: ink, fontSize: 18, fontWeight: FontWeight.w900))), Text('${_topups.length} record${_topups.length == 1 ? '' : 's'}', style: const TextStyle(color: mutedInk, fontSize: 11))]),
              const SizedBox(height: 11),
              if (_topups.isEmpty) _surface(child: const Padding(padding: EdgeInsets.symmetric(vertical: 14), child: Column(children: [Icon(Icons.receipt_long_outlined, size: 34, color: mutedInk), SizedBox(height: 8), Text('No top-ups yet', style: TextStyle(color: ink, fontWeight: FontWeight.w800)), SizedBox(height: 4), Text('Your deposit and checkout history will appear here.', textAlign: TextAlign.center, style: TextStyle(color: mutedInk, fontSize: 12))])))
              else ..._topups.take(10).map((topup) => Padding(padding: const EdgeInsets.only(bottom: 9), child: _DepositRow(deposit: topup,
                onCheck: topup['provider']?.toString() != 'manual' && const {'pending', 'processing', 'expired'}.contains(topup['status']?.toString()) ? () => _checkCheckoutStatus(topup['id']) : null,
                onCancel: topup['provider']?.toString() != 'manual' && topup['status']?.toString() == 'pending' ? () => _cancelTopup(topup['id']) : null))),
            ])),
    );
  }

  Widget _buildHero() => Container(
    padding: const EdgeInsets.all(19),
    decoration: BoxDecoration(gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFF104A34), Color(0xFF287653), Color(0xFF398765)]), borderRadius: BorderRadius.circular(22), boxShadow: [BoxShadow(color: const Color(0xFF145A3D).withValues(alpha: .18), blurRadius: 20, offset: const Offset(0, 8))]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [Container(width: 43, height: 43, decoration: BoxDecoration(color: Colors.white.withValues(alpha: .13), borderRadius: BorderRadius.circular(14)), child: const Icon(Icons.account_balance_wallet_outlined, color: Color(0xFFF4D58D), size: 23)), const Spacer(), Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: Colors.white.withValues(alpha: .13), borderRadius: BorderRadius.circular(20)), child: const Row(children: [Icon(Icons.lock_outline, color: Colors.white70, size: 12), SizedBox(width: 5), Text('Secure top-up', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700))]))]),
      const SizedBox(height: 17),
      const Text('Add funds to your wallet', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900, letterSpacing: -.3)),
      const SizedBox(height: 6),
      Text('Choose a payment option that works for you. Manual transfers are credited after verification; online payments are confirmed by the provider.', style: TextStyle(color: Colors.white.withValues(alpha: .82), fontSize: 12, height: 1.5)),
      const SizedBox(height: 15),
      const Row(children: [Icon(Icons.verified_user_outlined, color: Color(0xFFF4D58D), size: 16), SizedBox(width: 7), Text('Your wallet is credited only after payment confirmation', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600))]),
    ]),
  );

  Widget _buildPaymentPanel() => _surface(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    const Text('Choose payment method', style: TextStyle(color: ink, fontSize: 17, fontWeight: FontWeight.w900)),
    const SizedBox(height: 4),
    const Text('Select a method, enter an amount and follow the steps.', style: TextStyle(color: mutedInk, fontSize: 11)),
    const SizedBox(height: 16),
    if (_gateways.isEmpty) const _InlineNotice(icon: Icons.info_outline, text: 'No payment method is currently enabled.') else ...[
      _fieldLabel('PAYMENT METHOD'),
      DropdownButtonFormField<String>(value: _gateway, isExpanded: true, decoration: _inputDecoration(prefix: Icons.payments_outlined), items: _gateways.map((g) => DropdownMenuItem(value: g, child: Text(_humanize(g)))).toList(), onChanged: _sending ? null : (value) => setState(() { _gateway = value ?? _gateway; final list = _gatewayCurrencies[_gateway] ?? const <String>[]; if (!list.contains(_onlineCurrency)) _onlineCurrency = list.firstOrNull ?? 'USD'; })),
      const SizedBox(height: 17),
      if (_gateway == 'manual') _buildManualForm() else _buildOnlineForm(),
    ],
    if (_pendingCheckout != null) ...[
      const SizedBox(height: 15),
      Container(padding: const EdgeInsets.all(13), decoration: BoxDecoration(color: const Color(0xFFFFF8E8), borderRadius: BorderRadius.circular(13), border: Border.all(color: const Color(0xFFF0DDAE))), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const Row(children: [Icon(Icons.hourglass_top_rounded, color: Color(0xFF946A18), size: 18), SizedBox(width: 7), Text('Payment awaiting confirmation', style: TextStyle(color: Color(0xFF765516), fontWeight: FontWeight.w800, fontSize: 12))]), const SizedBox(height: 5), const Text('If you have completed checkout, check the payment status. Your wallet is credited after provider confirmation.', style: TextStyle(color: Color(0xFF806B43), fontSize: 11, height: 1.4)), const SizedBox(height: 9), OutlinedButton.icon(onPressed: _sending ? null : _checkCheckoutStatus, icon: const Icon(Icons.refresh_rounded, size: 17), label: const Text('Check payment status'))])),
    ],
  ]));

  Widget _buildManualForm() {
    if (!_manualEnabled || _methods.isEmpty) return const _InlineNotice(icon: Icons.info_outline, text: 'Manual payment instructions are not configured yet. Choose an online payment method or try again later.');
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _fieldLabel('DEPOSIT CHANNEL'),
      DropdownButtonFormField<int>(value: (_method?['id'] as num?)?.toInt(), isExpanded: true, decoration: _inputDecoration(prefix: Icons.account_balance_outlined), items: _methods.map((m) => DropdownMenuItem(value: (m['id'] as num).toInt(), child: Text('${m['name'] ?? 'Deposit method'} · ${m['currency'] ?? ''}', overflow: TextOverflow.ellipsis))).toList(), onChanged: _sending ? null : (id) => setState(() => _method = _methods.where((m) => (m['id'] as num).toInt() == id).firstOrNull)),
      if (_method != null) ...[
        const SizedBox(height: 13),
        _manualInstructions(),
      ],
      const SizedBox(height: 17),
      _fieldLabel('DEPOSIT AMOUNT'),
      TextField(controller: _amount, enabled: !_sending, keyboardType: const TextInputType.numberWithOptions(decimal: true), inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))], decoration: _inputDecoration(hint: 'Enter amount', prefix: Icons.payments_outlined, suffix: _currency)),
      const SizedBox(height: 13),
      _fieldLabel('BANK REFERENCE  ·  OPTIONAL'),
      TextField(controller: _reference, enabled: !_sending, decoration: _inputDecoration(hint: 'Enter transfer reference', prefix: Icons.tag_rounded)),
      const SizedBox(height: 15),
      _fieldLabel('PAYMENT RECEIPT'),
      _proofPicker(),
      const SizedBox(height: 16),
      _primaryButton(label: 'Submit deposit proof', icon: Icons.upload_file_rounded, onPressed: _submit),
      const SizedBox(height: 9),
      const Center(child: Text('Your deposit will be reviewed before funds are added.', textAlign: TextAlign.center, style: TextStyle(color: mutedInk, fontSize: 10))),
    ]);
  }

  Widget _manualInstructions() {
    final method = _method!;
    final addresses = method['addresses'] is List ? (method['addresses'] as List).map((e) => e.toString()).toList() : <String>[];
    return Container(padding: const EdgeInsets.all(13), decoration: BoxDecoration(color: const Color(0xFFF3F8F4), borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFDCE9DE))), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [const Icon(Icons.account_balance, color: brandGreen, size: 18), const SizedBox(width: 7), Expanded(child: Text('${method['name'] ?? 'Payment'} instructions', style: const TextStyle(color: Color(0xFF24583C), fontWeight: FontWeight.w800, fontSize: 12))), if (method['logo'] != null) const Icon(Icons.verified_outlined, color: brandGreen, size: 17)]),
      if (method['description'] != null) ...[const SizedBox(height: 7), Text(method['description'].toString(), style: const TextStyle(color: Color(0xFF587060), fontSize: 11, height: 1.45))],
      if (addresses.isNotEmpty) ...[
        const SizedBox(height: 11),
        ...addresses.asMap().entries.map((entry) => Container(margin: EdgeInsets.only(bottom: entry.key == addresses.length - 1 ? 0 : 7), padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE1EBE2))), child: Row(children: [Expanded(child: SelectableText(entry.value, style: const TextStyle(color: ink, fontSize: 11, fontWeight: FontWeight.w700, height: 1.35))), IconButton(visualDensity: VisualDensity.compact, tooltip: 'Copy payment details', onPressed: () async { await Clipboard.setData(ClipboardData(text: entry.value)); if (mounted) showAuthMessage(context, 'Payment details copied.'); }, icon: const Icon(Icons.copy_rounded, size: 17, color: brandGreen))]))),
      ],
      if (addresses.isEmpty) const Padding(padding: EdgeInsets.only(top: 8), child: Text('No account details were provided for this method.', style: TextStyle(color: mutedInk, fontSize: 11))),
    ]));
  }

  Widget _buildOnlineForm() {
    final currencies = _gatewayCurrencies[_gateway] ?? const <String>[];
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _fieldLabel('PAYMENT CURRENCY'),
      DropdownButtonFormField<String>(value: currencies.contains(_onlineCurrency) ? _onlineCurrency : currencies.firstOrNull, isExpanded: true, decoration: _inputDecoration(prefix: Icons.currency_exchange_rounded), items: currencies.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(), onChanged: _sending ? null : (value) => setState(() => _onlineCurrency = value ?? _onlineCurrency)),
      const SizedBox(height: 17),
      _fieldLabel('TOP-UP AMOUNT'),
      TextField(controller: _amount, enabled: !_sending, keyboardType: const TextInputType.numberWithOptions(decimal: true), inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))], decoration: _inputDecoration(hint: 'Enter amount', prefix: Icons.payments_outlined, suffix: _onlineCurrency)),
      const SizedBox(height: 16),
      _primaryButton(label: 'Continue to ${_humanize(_gateway)}', icon: Icons.lock_outline_rounded, onPressed: _startCheckout),
      const SizedBox(height: 9),
      const Center(child: Text('You will be redirected to the payment provider to complete checkout.', textAlign: TextAlign.center, style: TextStyle(color: mutedInk, fontSize: 10))),
    ]);
  }

  Widget _proofPicker() => InkWell(onTap: _sending ? null : _pickProof, borderRadius: BorderRadius.circular(13), child: Container(width: double.infinity, padding: const EdgeInsets.all(14), decoration: BoxDecoration(color: _proof == null ? const Color(0xFFFAFBFA) : const Color(0xFFF0F7F1), borderRadius: BorderRadius.circular(13), border: Border.all(color: _proof == null ? const Color(0xFFDCE4DC) : brandGreen, style: BorderStyle.solid)), child: Row(children: [Container(width: 40, height: 40, decoration: BoxDecoration(color: const Color(0xFFE8F1E9), borderRadius: BorderRadius.circular(11)), child: Icon(_proof == null ? Icons.cloud_upload_outlined : Icons.task_alt_rounded, color: brandGreen)), const SizedBox(width: 11), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(_proof?.name ?? 'Upload transfer receipt', style: const TextStyle(color: ink, fontWeight: FontWeight.w800, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis), const SizedBox(height: 3), Text(_proof == null ? 'JPG, PNG, WEBP or PDF' : _fileSize(_proof!.size), style: const TextStyle(color: mutedInk, fontSize: 10))])), Text(_proof == null ? 'Browse' : 'Change', style: const TextStyle(color: brandGreen, fontWeight: FontWeight.w800, fontSize: 11))])));

  Widget _primaryButton({required String label, required IconData icon, required Future<void> Function() onPressed}) => SizedBox(width: double.infinity, height: 51, child: FilledButton.icon(onPressed: _sending ? null : onPressed, icon: _sending ? const SizedBox(width: 17, height: 17, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Icon(icon, size: 18), label: Text(_sending ? 'Please wait…' : label, style: const TextStyle(fontWeight: FontWeight.w800)), style: FilledButton.styleFrom(backgroundColor: brandGreen, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)))));

  Widget _fieldLabel(String text) => Padding(padding: const EdgeInsets.only(bottom: 7), child: Text(text, style: const TextStyle(color: Color(0xFF65736A), fontSize: 9, letterSpacing: .8, fontWeight: FontWeight.w900)));
  InputDecoration _inputDecoration({String? hint, IconData? prefix, String? suffix}) => InputDecoration(hintText: hint, prefixIcon: prefix == null ? null : Icon(prefix, size: 18, color: mutedInk), suffixText: suffix, filled: true, fillColor: const Color(0xFFFAFBFA), contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 14), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE1E7E1))), enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE1E7E1))), focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: brandGreen, width: 1.4)));
}

class _DepositRow extends StatelessWidget {
  const _DepositRow({required this.deposit, this.onCheck, this.onCancel});
  final Map<String, dynamic> deposit;
  final VoidCallback? onCheck;
  final VoidCallback? onCancel;
  @override
  Widget build(BuildContext context) {
    final currency = deposit['currency']?.toString() ?? 'USD';
    final amount = (deposit['amount_minor'] as num?)?.toInt() ?? 0;
    final digits = _precision(currency);
    final provider = _humanize(deposit['provider']?.toString() ?? 'manual');
    final status = deposit['status']?.toString() ?? 'pending';
    final color = _statusColor(status);
    return _surface(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [Container(width: 39, height: 39, decoration: BoxDecoration(color: const Color(0xFFEAF2EB), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.receipt_long_outlined, color: brandGreen, size: 20)), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('$currency ${(amount / _pow10(digits)).toStringAsFixed(digits)}', style: const TextStyle(color: ink, fontSize: 14, fontWeight: FontWeight.w900)), const SizedBox(height: 2), Text(provider, style: const TextStyle(color: mutedInk, fontSize: 10))])), Container(padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5), decoration: BoxDecoration(color: color.withValues(alpha: .10), borderRadius: BorderRadius.circular(20)), child: Text(_humanize(status), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800)))]),
      if (deposit['created_at'] != null) Padding(padding: const EdgeInsets.only(top: 9), child: Row(children: [const Icon(Icons.calendar_today_outlined, size: 12, color: mutedInk), const SizedBox(width: 6), Text(_formatDate(deposit['created_at'].toString()), style: const TextStyle(color: mutedInk, fontSize: 10))])),
      if (onCheck != null || onCancel != null) ...[const Divider(height: 20, color: Color(0xFFECEFEC)), Align(alignment: Alignment.centerRight, child: Wrap(spacing: 3, children: [if (onCheck != null) TextButton.icon(onPressed: onCheck, icon: const Icon(Icons.refresh_rounded, size: 15), label: const Text('Check payment')), if (onCancel != null) TextButton.icon(onPressed: onCancel, icon: const Icon(Icons.cancel_outlined, size: 15), label: const Text('Cancel'))]))],
    ]));
  }
}

class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: const Color(0xFFFFF8E8), borderRadius: BorderRadius.circular(12)), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: const Color(0xFF987127), size: 18), const SizedBox(width: 8), Expanded(child: Text(text, style: const TextStyle(color: Color(0xFF765F32), fontSize: 11, height: 1.4)))]));
}

Widget _surface({required Widget child}) => Container(width: double.infinity, padding: const EdgeInsets.all(15), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFFE6EBE5)), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .025), blurRadius: 12, offset: const Offset(0, 4))]), child: child);

String _humanize(String value) => value.toLowerCase() == 'bachs' ? 'BACHS PAY' : value.replaceAll('_', ' ').split(' ').map((part) => part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}').join(' ');
int _precision(String currency) {
  if (const {'BIF','CLP','DJF','GNF','ISK','JPY','KMF','KRW','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'}.contains(currency.toUpperCase())) return 0;
  if (const {'BHD','IQD','JOD','KWD','LYD','OMR','TND'}.contains(currency.toUpperCase())) return 3;
  return 2;
}
double _pow10(int digits) => digits == 0 ? 1 : digits == 3 ? 1000 : 100;
Color _statusColor(String status) => switch (status.toLowerCase()) {
  'paid' || 'approved' || 'completed' || 'success' => const Color(0xFF21834D),
  'failed' || 'rejected' || 'cancelled' => const Color(0xFFB54747),
  'expired' => const Color(0xFF8A6A32),
  _ => const Color(0xFF9A762E),
};
String _formatDate(String value) {
  final date = DateTime.tryParse(value)?.toLocal();
  if (date == null) return value;
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return '${date.day} ${months[date.month - 1]} ${date.year} · ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}
String _fileSize(int bytes) => bytes < 1024 ? '$bytes B' : bytes < 1024 * 1024 ? '${(bytes / 1024).toStringAsFixed(1)} KB' : '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
