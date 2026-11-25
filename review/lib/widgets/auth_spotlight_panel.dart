import 'dart:async';

import 'package:flutter/material.dart';

import '../features/marketplace/presentation/listing_kind.dart';
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
  int _advertIndex = 0;
  int _testimonialIndex = 0;
  final _products = PageController(viewportFraction: .90);
  final _adverts = PageController(viewportFraction: .88);
  final _testimonials = PageController(viewportFraction: .92);
  Timer? _productTimer;
  Timer? _testimonialTimer;
  Timer? _advertTimer;

  List<Map<String, dynamic>> get _featuredAds => (_data?['ads'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();
  List<Map<String, dynamic>> get _quotes => (_data?['testimonies'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();
  List<Map<String, dynamic>> get _payments => (_data?['pay_option'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();
  List<Map<String, dynamic>> get _featuredApps => (_data?['products'] as List? ?? const []).whereType<Map<String, dynamic>>().toList();

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final response = await widget.api.spotlights();
      final data = response['data'];
      if (data is! Map<String, dynamic>) throw const AuthException('Unexpected spotlight response.');
      if (!mounted) return;
      setState(() {
         _data = data; _loading = false;
      });
      _startTimers();
    } on AuthException catch (error) {
      if (mounted) setState(() { _error = error.message; _loading = false; });
    }
  }

  void _startTimers() {
    _productTimer?.cancel(); _testimonialTimer?.cancel();  _advertTimer?.cancel();

    if (_featuredApps.length > 1) {
      _productTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!_products.hasClients) { return; }
      _productIndex = (_productIndex + 1) % _featuredApps.length;
      _products.animateToPage(_productIndex, duration: const Duration(milliseconds: 500), curve: Curves.easeOutCubic);
      });
    }

    if (_featuredAds.length > 1) {
      _advertTimer = Timer.periodic(const Duration(seconds: 6), (_) {
      if (!_adverts.hasClients) { return; }
      _advertIndex = (_advertIndex + 1) % _featuredAds.length;
      _adverts.animateToPage(_advertIndex, duration: const Duration(milliseconds: 500), curve: Curves.easeOutCubic);
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
  void dispose() {
     _productTimer?.cancel(); _testimonialTimer?.cancel(); _advertTimer?.cancel(); 
     _products.dispose(); _testimonials.dispose(); _adverts.dispose(); 
     super.dispose(); 
  }


  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const SizedBox(
        height: 420,
        child: Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    if (_error != null) {
      return SizedBox(
        height: 260,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.wifi_off_rounded,
                color: mutedInk,
                size: 30,
              ),
              const SizedBox(height: 10),
              Text(
                'Spotlights are unavailable',
                style: TextStyle(
                  color: Colors.grey.shade700,
                  fontWeight: FontWeight.w700,
                ),
              ),
              TextButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Try again'),
              ),
            ],
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'A better way to earn.',
          style: TextStyle(
            fontSize: 31,
            height: 1.08,
            fontWeight: FontWeight.w800,
            letterSpacing: -1.2,
            color: Colors.grey.shade900,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Discover apps, share your honest opinion and get rewarded.',
          style: TextStyle(
            fontSize: 15,
            height: 1.5,
            color: mutedInk,
          ),
        ),

        if (_featuredAds.isNotEmpty) ...[
          const SizedBox(height: 28),
          _sectionLabel('Featured Ads'),
          const SizedBox(height: 13),
          SizedBox(
            height: 295,
            child: PageView.builder(
              controller: _adverts,
              itemCount: _featuredAds.length,
              onPageChanged: (i) => setState(() => _advertIndex = i),
              itemBuilder: (context, i) => _ProductCard(
                product: _featuredAds[i],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _dots(_featuredAds.length, _advertIndex),
        ],

        if (_featuredApps.isNotEmpty) ...[
          const SizedBox(height: 28),
          _sectionLabel('Featured Apps'),
          const SizedBox(height: 13),
          SizedBox(
            height: 100,
            child: PageView.builder(
              controller: _products,
              itemCount: _featuredApps.length,
              onPageChanged: (i) => setState(() => _productIndex = i),
              itemBuilder: (context, index) => _FeaturedListing(
                kind: ListingKind.appReview,
                item: _featuredApps[index],
                onTap: () {}
              ),
            ),
          ),
          const SizedBox(height: 12),
          _dots(_featuredApps.length, _productIndex),
        ],

//        _buildFeaturedSection(),

        if (_quotes.isNotEmpty) ...[
          const SizedBox(height: 27),
          _sectionLabel('What our members say'),
          const SizedBox(height: 13),
          SizedBox(
            height: 98,
            child: PageView.builder(
              controller: _testimonials,
              itemCount: _quotes.length,
              onPageChanged: (i) =>
                  setState(() => _testimonialIndex = i),
              itemBuilder: (context, i) => _TestimonialCard(
                testimony: _quotes[i],
              ),
            ),
          ),
          const SizedBox(height: 11),
          _dots(_quotes.length, _testimonialIndex),
        ],

        const SizedBox(height: 28),

        Container(
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(
            color: softGreen,
            borderRadius: BorderRadius.circular(16),
          ),
          child: const Row(
            children: [
              Expanded(
                child: _Benefit(
                  icon: Icons.public_rounded,
                  title: 'Global tasks',
                ),
              ),
              Expanded(
                child: _Benefit(
                  icon: Icons.bolt_rounded,
                  title: 'Quick rewards',
                ),
              ),
              Expanded(
                child: _Benefit(
                  icon: Icons.verified_user_outlined,
                  title: 'Human review',
                ),
              ),
            ],
          ),
        ),

        if (_payments.isNotEmpty) ...[
          const SizedBox(height: 22),
          const Text(
            'Payment options',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: mutedInk,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: _payments.map((payment) {
              final logo = payment['logo']?.toString();

              return Container(
                width: 72,
                height: 46,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border.all(
                    color: const Color(0xFFE5E9E3),
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: logo != null && logo.isNotEmpty
                    ? Image.network(
                        logo,
                        fit: BoxFit.contain,
                        errorBuilder: (_, _, _) => const Icon(
                          Icons.account_balance_wallet_outlined,
                          size: 20,
                        ),
                      )
                    : const Icon(
                        Icons.account_balance_wallet_outlined,
                        size: 20,
                      ),
              );
            }).toList(),
          ),
        ],
      ],
    );
  }

  Widget _sectionLabel(String text) => Container(padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 7), decoration: BoxDecoration(color: softGreen, borderRadius: BorderRadius.circular(20)), child: Text(text, style: const TextStyle(color: brandGreen, fontSize: 12, fontWeight: FontWeight.w800)));
  Widget _dots(int count, int active) => Row(children: List.generate(count, (i) => AnimatedContainer(duration: const Duration(milliseconds: 220), margin: const EdgeInsets.only(right: 6), width: i == active ? 22 : 6, height: 6, decoration: BoxDecoration(color: i == active ? brandGreen : Colors.black12, borderRadius: BorderRadius.circular(9)))));
}


class _ProductCard extends StatelessWidget {
  const _ProductCard({
    required this.product,
  });

  final Map<String, dynamic> product;

  @override
  Widget build(BuildContext context) {
    final image = product['creative_path']?.toString();

    return Padding(
      padding: const EdgeInsets.only(right: 12),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(17),
          border: Border.all(
            color: const Color(0xFFE5E9E3),
          ),
          boxShadow: const [
            BoxShadow(
              color: Color(0x10000000),
              blurRadius: 13,
              offset: Offset(0, 5),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: image != null && image.isNotEmpty
                  ? Image.network(
                      image,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => _imagePlaceholder(),
                    )
                  : _imagePlaceholder(),
            ),

            Padding(
              padding: const EdgeInsets.fromLTRB(13, 9, 13, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product['title']?.toString() ?? 'Featured app',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: ink,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    product['share_message']?.toString() ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      color: mutedInk,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    '${product['currency'] ?? 'USD'} '
                    '${product['price'] ?? ''} Per Share',
                    style: const TextStyle(
                      color: brandGreen,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _imagePlaceholder() {
    return Container(
      width: double.infinity,
      color: softGreen,
      child: const Center(
        child: Icon(
          Icons.apps_rounded,
          color: brandGreen,
          size: 58,
        ),
      ),
    );
  }
}

class _FeaturedListing extends StatelessWidget {
  const _FeaturedListing({
    required this.kind,
    required this.item,
    required this.onTap,
  });

  final ListingKind kind;
  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isApp = kind == ListingKind.appReview;
    final app = item['app'] is Map<String, dynamic>
        ? item['app'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final title = isApp
        ? item['name']?.toString() ?? app['name']?.toString() ?? 'Featured app'
        : item['title']?.toString() ?? 'Featured ad';
    final subtitle = isApp
        ? item['title']?.toString() ?? app['store']?.toString() ?? 'App review'
        : 'Sponsored share';
    final image = isApp
        ? item['cover_image']?.toString()
        : item['creative_path']?.toString();
    final sourceCurrency = item['currency']?.toString() ?? 'USD';

    final reward = '$sourceCurrency ${item['price'] ?? ''} ${isApp ? 'Review' : 'Share'} Reward';

    return Padding(
      padding: const EdgeInsets.only(right: 12),
      child: SizedBox(
      width: 265,
      child: Material(
        color: Theme.of(context).colorScheme.onSecondary
            .withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(15),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(15),
          child: Container(
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(15),
              border: Border.all(
                color: Theme.of(context).colorScheme.outlineVariant
                    .withValues(alpha: 0.45),
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 66,
                  height: 66,
                  clipBehavior: Clip.antiAlias,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.onSurface
                        .withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: image != null && image.startsWith('http')
                      ? Image.network(
                          image,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => Icon(
                            isApp
                                ? Icons.apps_rounded
                                : Icons.campaign_outlined,
                            color: brandGreen,
                          ),
                        )
                      : Icon(
                          isApp ? Icons.apps_rounded : Icons.campaign_outlined,
                          color: brandGreen,
                        ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: mutedInk, fontSize: 9),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        reward,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: brandGreen,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: mutedInk),
              ],
            ),
          ),
        ),
      ),
      ),
    );
  }
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
