import 'dart:async';

import 'package:flutter/material.dart';

import '../services/auth_api.dart';
import 'auth_components.dart';

class AuthSpotlightPanel extends StatefulWidget {
  const AuthSpotlightPanel({super.key, required this.api});
  final AuthApi api;

  @override
  State<AuthSpotlightPanel> createState() => _AuthSpotlightPanelState();
}

class _AuthSpotlightPanelState extends State<AuthSpotlightPanel> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = true;
  int _productIndex = 0;
  int _testimonialIndex = 0;
  final _products = PageController(viewportFraction: .88);
  final _testimonials = PageController(viewportFraction: .92);
  Timer? _productTimer;
  Timer? _testimonialTimer;

  List<Map<String, dynamic>> get _list => (_data?['products'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();
  List<Map<String, dynamic>> get _quotes => (_data?['testimonies'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();
  List<Map<String, dynamic>> get _payments => (_data?['pay_option'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final response = await widget.api.spotlights();
      final data = response['data'];
      if (data is! Map<String, dynamic>) throw const AuthException('Unexpected spotlight response.');
      if (!mounted) return;
      setState(() { _data = data; _loading = false; });
      _startTimers();
    } on AuthException catch (error) {
      if (mounted) setState(() { _error = error.message; _loading = false; });
    }
  }

  void _startTimers() {
    _productTimer?.cancel(); _testimonialTimer?.cancel();
    if (_list.length > 1) {
      _productTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!_products.hasClients) { return; }
      _productIndex = (_productIndex + 1) % _list.length;
      _products.animateToPage(_productIndex, duration: const Duration(milliseconds: 500), curve: Curves.easeOutCubic);
      });
    }
    if (_quotes.length > 1) {
      _testimonialTimer = Timer.periodic(const Duration(seconds: 7), (_) {
      if (!_testimonials.hasClients) { return; }
      _testimonialIndex = (_testimonialIndex + 1) % _quotes.length;
      _testimonials.animateToPage(_testimonialIndex, duration: const Duration(milliseconds: 500), curve: Curves.easeOutCubic);
      });
    }
  }

  @override
  void dispose() { _productTimer?.cancel(); _testimonialTimer?.cancel(); _products.dispose(); _testimonials.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const SizedBox(height: 420, child: Center(child: CircularProgressIndicator()));
    if (_error != null) return SizedBox(height: 260, child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.wifi_off_rounded, color: mutedInk, size: 30), const SizedBox(height: 10), Text('Spotlights are unavailable', style: TextStyle(color: Colors.grey.shade700, fontWeight: FontWeight.w700)), TextButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Try again'))])));

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('A better way to earn.', style: TextStyle(fontSize: 31, height: 1.08, fontWeight: FontWeight.w800, letterSpacing: -1.2, color: Colors.grey.shade900)),
      const SizedBox(height: 8),
      const Text('Discover apps, share your honest opinion and get rewarded.', style: TextStyle(fontSize: 15, height: 1.5, color: mutedInk)),
      if (_list.isNotEmpty) ...[
        const SizedBox(height: 28), _sectionLabel('Featured apps'), const SizedBox(height: 13),
        SizedBox(height: 295, child: PageView.builder(controller: _products, itemCount: _list.length, onPageChanged: (i) => setState(() => _productIndex = i), itemBuilder: (context, i) => _ProductCard(product: _list[i]))),
        const SizedBox(height: 12), _dots(_list.length, _productIndex),
      ],
      if (_quotes.isNotEmpty) ...[
        const SizedBox(height: 27), _sectionLabel('What our members say'), const SizedBox(height: 13),
        SizedBox(height: 98, child: PageView.builder(controller: _testimonials, itemCount: _quotes.length, onPageChanged: (i) => setState(() => _testimonialIndex = i), itemBuilder: (context, i) => _TestimonialCard(testimony: _quotes[i]))),
        const SizedBox(height: 11), _dots(_quotes.length, _testimonialIndex),
      ],
      const SizedBox(height: 28),
      Container(padding: const EdgeInsets.all(17), decoration: BoxDecoration(color: softGreen, borderRadius: BorderRadius.circular(16)), child: const Row(children: [Expanded(child: _Benefit(icon: Icons.public_rounded, title: 'Global tasks')), Expanded(child: _Benefit(icon: Icons.bolt_rounded, title: 'Quick rewards')), Expanded(child: _Benefit(icon: Icons.verified_user_outlined, title: 'Human review'))])),
      if (_payments.isNotEmpty) ...[
        const SizedBox(height: 22), const Text('Payment options', style: TextStyle(fontWeight: FontWeight.w700, color: mutedInk, fontSize: 12)), const SizedBox(height: 10),
        Wrap(spacing: 9, runSpacing: 9, children: _payments.map((payment) {
          final logo = payment['logo']?.toString();
          return Container(width: 72, height: 46, padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white, border: Border.all(color: const Color(0xFFE5E9E3)), borderRadius: BorderRadius.circular(10)), child: logo != null && logo.isNotEmpty ? Image.network(logo, fit: BoxFit.contain, errorBuilder: (_, _, _) => const Icon(Icons.account_balance_wallet_outlined, size: 20)) : const Icon(Icons.account_balance_wallet_outlined, size: 20));
        }).toList()),
      ],
    ]);
  }

  Widget _sectionLabel(String text) => Container(padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 7), decoration: BoxDecoration(color: softGreen, borderRadius: BorderRadius.circular(20)), child: Text(text, style: const TextStyle(color: brandGreen, fontSize: 12, fontWeight: FontWeight.w800)));
  Widget _dots(int count, int active) => Row(children: List.generate(count, (i) => AnimatedContainer(duration: const Duration(milliseconds: 220), margin: const EdgeInsets.only(right: 6), width: i == active ? 22 : 6, height: 6, decoration: BoxDecoration(color: i == active ? brandGreen : Colors.black12, borderRadius: BorderRadius.circular(9)))));
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product});
  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final image = product['cover_image']?.toString();
    return Padding(padding: const EdgeInsets.only(right: 12), child: Container(clipBehavior: Clip.antiAlias, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(17), border: Border.all(color: const Color(0xFFE5E9E3)), boxShadow: const [BoxShadow(color: Color(0x10000000), blurRadius: 13, offset: Offset(0, 5))]), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Expanded(child: image != null && image.isNotEmpty ? Image.network(image, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, _, _) => _imagePlaceholder()) : _imagePlaceholder()),
      Padding(padding: const EdgeInsets.fromLTRB(13, 9, 13, 10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(product['title']?.toString() ?? 'Featured app', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, color: ink)), const SizedBox(height: 2), Text(product['name']?.toString() ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: mutedInk)), const SizedBox(height: 5), Text('${product['currency'] ?? 'USD'} ${product['price'] ?? ''} per review', style: const TextStyle(color: brandGreen, fontSize: 12, fontWeight: FontWeight.w800))])),
    ])));
  }

  Widget _imagePlaceholder() => Container(width: double.infinity, color: softGreen, child: const Center(child: Icon(Icons.apps_rounded, color: brandGreen, size: 58)));
}

class _TestimonialCard extends StatelessWidget {
  const _TestimonialCard({required this.testimony});
  final Map<String, dynamic> testimony;
  @override
  Widget build(BuildContext context) {
    final avatar = testimony['avatar']?.toString();
    return Padding(padding: const EdgeInsets.only(right: 10), child: Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(13), border: Border.all(color: const Color(0xFFE5E9E3))), child: Row(children: [
      CircleAvatar(radius: 26, backgroundColor: softGreen, backgroundImage: avatar != null && avatar.isNotEmpty ? NetworkImage(avatar) : null, child: avatar == null || avatar.isEmpty ? const Icon(Icons.person_outline, color: brandGreen) : null),
      const SizedBox(width: 11), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [Text(testimony['content']?.toString() ?? '', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, height: 1.3, fontWeight: FontWeight.w600, color: ink)), const SizedBox(height: 5), Text('${testimony['name'] ?? 'Member'}  •  ★ ${testimony['rating'] ?? ''}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: brandGreen))]))
    ])));
  }
}

class _Benefit extends StatelessWidget {
  const _Benefit({required this.icon, required this.title});
  final IconData icon; final String title;
  @override
  Widget build(BuildContext context) => Column(children: [Icon(icon, color: brandGreen, size: 21), const SizedBox(height: 5), Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 10, color: Color(0xFF446151), fontWeight: FontWeight.w700))]);
}
