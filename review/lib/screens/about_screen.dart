import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/auth_api.dart';
import '../services/media_url.dart';
import 'legal_document_screen.dart';

class AboutScreen extends StatefulWidget {
  const AboutScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AboutScreen> createState() => _AboutScreenState();
}

class _AboutScreenState extends State<AboutScreen> {
  Map<String, dynamic> _content = const {};
  Map<String, dynamic> _stats = const {};
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.about();
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _content = _asMap(data is Map ? data['content'] : null);
        _stats = _asMap(data is Map ? data['stats'] : null);
        _loading = false;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'About information could not be loaded.';
        _loading = false;
      });
    }
  }

  Map<String, dynamic> _asMap(Object? value) => value is Map
      ? value.map((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

  String _text(String key, String fallback) {
    final value = _content[key];
    return value == null || value.toString().trim().isEmpty
        ? fallback
        : value.toString();
  }

  List<String> _strings(String key, List<String> fallback) {
    final value = _content[key];
    if (value is List) {
      return value
          .map((item) => item.toString())
          .where((item) => item.isNotEmpty)
          .toList();
    }
    return fallback;
  }

  Future<void> _open(String value, {bool external = true}) async {
    final uri = Uri.tryParse(value);
    if (uri == null) return;
    try {
      await launchUrl(
        uri,
        mode: external
            ? LaunchMode.externalApplication
            : LaunchMode.platformDefault,
      );
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('This link could not be opened.')),
        );
      }
    }
  }

  int _stat(String key) {
    final value = _stats[key];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final heroUrl = MediaUrl.resolve(_text('hero_image_url', ''));
    final bullets = _strings('what_we_do_bullets', const [
      'Review apps and answer guided questions',
      'Share sponsor ads publicly and submit evidence',
      'Earn rewards after approval',
      'Invite friends and earn referral rewards',
    ]);
    final features = _strings('features', const [
      'App Reviews',
      'Sponsored Sharing',
      'Earn Rewards',
      'Referrals',
      'Location Access',
      'Human Review',
    ]);
    return Scaffold(
      appBar: AppBar(centerTitle: true, title: const Text('About')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: _load,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Try again'),
                    ),
                  ],
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
              children: [
                _hero(context, heroUrl),
                const SizedBox(height: 20),
                _infoCard(
                  context,
                  icon: Icons.flag_rounded,
                  title: _text('mission_title', 'Our Mission'),
                  child: Text(
                    _text(
                      'mission_body',
                      'TrustLens connects app creators with people who can provide thoughtful feedback and earn rewards after approval.',
                    ),
                    style: theme.textTheme.bodyLarge?.copyWith(height: 1.55),
                  ),
                ),
                const SizedBox(height: 16),
                _infoCard(
                  context,
                  icon: Icons.work_outline_rounded,
                  title: _text('what_we_do_title', 'What We Do'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _text(
                          'what_we_do_body',
                          'A community marketplace for app feedback and sponsored sharing. Members can:',
                        ),
                        style: theme.textTheme.bodyLarge?.copyWith(height: 1.5),
                      ),
                      const SizedBox(height: 10),
                      ...bullets.map(
                        (item) => Padding(
                          padding: const EdgeInsets.only(bottom: 7),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('•  '),
                              Expanded(
                                child: Text(
                                  item,
                                  style: theme.textTheme.bodyLarge?.copyWith(
                                    height: 1.4,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                _statsCard(context),
                const SizedBox(height: 16),
                _featuresCard(context, features),
                const SizedBox(height: 16),
                _contactCard(context),
                const SizedBox(height: 16),
                _linksCard(context),
                if (_content['social_links'] is Map &&
                    (_content['social_links'] as Map).isNotEmpty) ...[
                  const SizedBox(height: 20),
                  Text(
                    'Connect With Us',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: (_content['social_links'] as Map).entries
                        .map(
                          (entry) => ActionChip(
                            avatar: const Icon(Icons.public_rounded, size: 18),
                            label: Text(entry.key.toString()),
                            onPressed: () => _open(entry.value.toString()),
                          ),
                        )
                        .toList(),
                  ),
                ],
                const SizedBox(height: 28),
                Text(
                  _text(
                    'footer_copyright',
                    '© TrustLens. All rights reserved.',
                  ),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  _text(
                    'footer_note',
                    'Built for people who make apps better.',
                  ),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
                Text(
                  'Version ${_text('version', '1.0.0')}',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ],
            ),
    );
  }

  Widget _hero(BuildContext context, String? imageUrl) {
    final colors = Theme.of(context).colorScheme;
    return ClipRRect(
      borderRadius: const BorderRadius.vertical(
        bottom: Radius.circular(34),
        top: Radius.circular(20),
      ),
      child: SizedBox(
        height: 250,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (imageUrl != null)
              CachedNetworkImage(
                imageUrl: imageUrl,
                fit: BoxFit.cover,
                errorWidget: (_, __, ___) =>
                    Image.asset('assets/splash-bg.png', fit: BoxFit.cover),
              )
            else
              Image.asset('assets/splash-bg.png', fit: BoxFit.cover),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    colors.primary.withValues(alpha: .18),
                    colors.primary.withValues(alpha: .86),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Image.asset(
                        'assets/logo.jpg',
                        width: 58,
                        height: 58,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Icon(
                          Icons.verified_rounded,
                          size: 54,
                          color: colors.onPrimary,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          _text('brand_name', 'TrustLens'),
                          style: Theme.of(context).textTheme.headlineMedium
                              ?.copyWith(
                                color: colors.onPrimary,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                    ],
                  ),
                  Align(
                    alignment: Alignment.center,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 18,
                        vertical: 11,
                      ),
                      decoration: BoxDecoration(
                        color: colors.onPrimary.withValues(alpha: .20),
                        borderRadius: BorderRadius.circular(30),
                      ),
                      child: Text(
                        _text(
                          'hero_tagline',
                          'Discover trusted apps. Review. Earn.',
                        ),
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: colors.onPrimary,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
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

  Widget _infoCard(
    BuildContext context, {
    required IconData icon,
    required String title,
    required Widget child,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Card(
      elevation: 0,
      color: colors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: colors.primaryContainer,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(icon, color: colors.primary),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: colors.primary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            child,
          ],
        ),
      ),
    );
  }

  Widget _statsCard(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final values = <(String, String, IconData, Color)>[
      (
        'members',
        _text('stats_members_label', 'Community Members'),
        Icons.people_alt_rounded,
        Colors.blue,
      ),
      (
        'approved_tasks',
        _text('stats_approved_label', 'Approved Tasks'),
        Icons.photo_library_rounded,
        Colors.orange,
      ),
      (
        'reward_payouts',
        _text('stats_rewards_label', 'Rewards Paid'),
        Icons.workspace_premium_rounded,
        Colors.teal,
      ),
      (
        'testimonials',
        _text('stats_testimonials_label', 'Testimonials'),
        Icons.format_quote_rounded,
        Colors.deepOrange,
      ),
    ];
    return Card(
      elevation: 0,
      color: colors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Platform Stats',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: values.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 1.20,
              ),
              itemBuilder: (context, index) {
                final (key, label, icon, tint) = values[index];
                return Container(
                  decoration: BoxDecoration(
                    color: tint.withValues(
                      alpha: Theme.of(context).brightness == Brightness.dark
                          ? .18
                          : .10,
                    ),
                    borderRadius: BorderRadius.circular(18),
                  ),
                  padding: const EdgeInsets.all(10),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(icon, color: tint),
                      const SizedBox(height: 7),
                      Text(
                        _stat(key).toString(),
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: tint,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        label,
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _featuresCard(BuildContext context, List<String> features) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      color: colors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Features',
              style: Theme.of(context).textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 9,
              runSpacing: 9,
              children: features
                  .map(
                    (label) => Chip(
                      avatar: Icon(
                        Icons.auto_awesome_rounded,
                        size: 17,
                        color: colors.primary,
                      ),
                      label: Text(label),
                      backgroundColor: colors.primaryContainer.withValues(
                        alpha: .55,
                      ),
                      side: BorderSide.none,
                    ),
                  )
                  .toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _contactCard(BuildContext context) {
    final email = _text('contact_email', '');
    final phone = _text('contact_phone', '');
    final address = _text('contact_address', '');
    final rows = <Widget>[];
    if (email.isNotEmpty)
      rows.add(
        _contactRow(
          context,
          Icons.mail_outline_rounded,
          'Email',
          email,
          () => _open('mailto:$email'),
        ),
      );
    if (phone.isNotEmpty)
      rows.add(
        _contactRow(
          context,
          Icons.phone_outlined,
          'Phone',
          phone,
          () => _open('tel:$phone'),
        ),
      );
    if (address.isNotEmpty)
      rows.add(
        _contactRow(
          context,
          Icons.location_on_outlined,
          'Address',
          address,
          () async => Clipboard.setData(ClipboardData(text: address)),
        ),
      );
    if (rows.isEmpty) return const SizedBox.shrink();
    return Card(
      elevation: 0,
      color: Theme.of(context).colorScheme.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const Divider(height: 1),
            rows[i],
          ],
        ],
      ),
    );
  }

  Widget _contactRow(
    BuildContext context,
    IconData icon,
    String title,
    String value,
    VoidCallback onTap,
  ) => ListTile(
    leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
    title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
    subtitle: Text(value),
    trailing: Icon(
      title == 'Address' ? Icons.copy_rounded : Icons.open_in_new_rounded,
    ),
    onTap: onTap,
  );

  Widget _linksCard(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      color: colors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: colors.outlineVariant),
      ),
      child: Column(
        children: [
          _linkRow(
            context,
            Icons.privacy_tip_rounded,
            _text('privacy_label', 'Privacy Policy'),
            () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    LegalDocumentScreen(api: widget.api, slug: 'privacy'),
              ),
            ),
          ),
          Divider(height: 1, color: colors.outlineVariant),
          _linkRow(
            context,
            Icons.description_rounded,
            _text('terms_label', 'Terms of Participation'),
            () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    LegalDocumentScreen(api: widget.api, slug: 'terms'),
              ),
            ),
          ),
          if (_text('store_url', '').isNotEmpty) ...[
            Divider(height: 1, color: colors.outlineVariant),
            _linkRow(
              context,
              Icons.star_rounded,
              _text('rate_app_label', 'Rate us on the store'),
              () => _open(_text('store_url', '')),
            ),
          ],
        ],
      ),
    );
  }

  Widget _linkRow(
    BuildContext context,
    IconData icon,
    String label,
    VoidCallback onTap,
  ) => ListTile(
    leading: Icon(icon),
    title: Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
    trailing: const Icon(Icons.chevron_right_rounded),
    onTap: onTap,
  );
}
