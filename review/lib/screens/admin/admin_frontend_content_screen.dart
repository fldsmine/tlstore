import 'package:flutter/material.dart';

import '../../services/auth_api.dart';
import 'admin_ads_screen.dart';
import 'admin_app_listings_screen.dart';
import 'admin_payment_gateways_screen.dart';

class AdminFrontendContentScreen extends StatefulWidget {
  const AdminFrontendContentScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminFrontendContentScreen> createState() =>
      _AdminFrontendContentScreenState();
}

class _AdminFrontendContentScreenState
    extends State<AdminFrontendContentScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _featuresController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _stepTitleControllers = <TextEditingController>[];
  final _stepDescriptionControllers = <TextEditingController>[];

  static const _defaults = <String, String>{
    'about.hero_tagline': 'Welcome to Trust lens store',
    'about.what_we_do_body': 'Built for people who make apps better',
    'about.mission_body':
        'A community marketplace for app feedback and sponsored sharing.',
    'about.hero_image_url': '',
    'about.hero_cta_text': 'Get Started',
    'about.hero_cta_link': '/register',
    'about.contact_email': 'support@tlstore.org',
    'about.contact_phone': '',
    'about.contact_address': '',
    'about.social_links.facebook': '',
    'about.social_links.twitter': '',
    'about.social_links.instagram': '',
    'about.social_links.tiktok': '',
    'about.social_links.telegram': '',
    'about.supports.link1': 'https://t.me/tlstore_general',
    'about.supports.link2': 'https://t.me/tlstore_help',
    'about.footer_copyright': '© TLStore. All rights reserved.',
    'about.footer_note': 'Review • Share • Earn',
  };

  static const _defaultSteps = <Map<String, String>>[
    {
      'title': 'Create your account',
      'description':
          'Register with your country and set up your TLStore profile.',
      'icon': 'person_add_alt_1_rounded',
      'color': 'primary',
    },
    {
      'title': 'Choose a package',
      'description': 'Join the package that gives you access to app reviews or sponsored sharing.',
      'icon': 'workspace_premium_rounded',
      'color': 'secondary',
    },
    {
      'title': 'Complete a task',
      'description': 'Review an app or share a sponsored post, then submit proof when required.',
      'icon': 'task_alt_rounded',
      'color': 'tertiary',
    },
    {
      'title': 'Get approved and earn',
      'description': 'Approved work is credited to your wallet. Track your earnings and request a withdrawal.',
      'icon': 'account_balance_wallet_rounded',
      'color': 'primary',
    },
  ];

  String? _token;
  String? _error;
  bool _loading = false;
  bool _saving = false;
  bool _obscurePassword = true;

  @override
  void initState() {
    super.initState();
    for (final entry in _defaults.entries) {
      _controllers[entry.key] = TextEditingController(text: entry.value);
    }
    _setSteps(_defaultSteps);
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _featuresController.dispose();
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    for (final controller in _stepTitleControllers) {
      controller.dispose();
    }
    for (final controller in _stepDescriptionControllers) {
      controller.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  List<Map<String, dynamic>> _rows(Object? value) => value is List
      ? value.whereType<Map>().map(_map).toList()
      : const <Map<String, dynamic>>[];

  String _string(Object? value, [String fallback = '']) {
    if (value == null) return fallback;
    if (value is String) return value;
    return value.toString();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.postPublic('admin/auth/login', {
        'email': _email.text.trim(),
        'password': _password.text,
      });
      final data = _map(response['data']);
      final token = response['token'] ?? data['token'] ?? data['access_token'];
      if (token == null || token.toString().isEmpty) {
        throw const AuthException(
          'Admin sign-in did not return an access token.',
        );
      }
      if (!mounted) return;
      setState(() => _token = token.toString());
      await _loadSettings();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Sign-in failed. Please try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadSettings() async {
    final token = _token;
    if (token == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.get('admin/settings', token);
      final rows = _rows(response['data']);
      final settings = <String, dynamic>{
        for (final row in rows)
          if (row['setting_key'] != null)
            row['setting_key'].toString(): row['value'],
      };

      for (final entry in _defaults.entries) {
        final value = settings[entry.key];
        _controllers[entry.key]!.text = _string(value, entry.value);
      }

      final featureValue = settings['about.features'];
      final features = featureValue is List
          ? featureValue.map((value) => value.toString().trim())
          : const [
              'App reviews',
              'Sponsored sharing',
              'Earn rewards',
              'Referral rewards',
            ];
      _featuresController.text = features.join('\n');

      final configuredSteps = settings['about.how_it_works'];
      final steps = configuredSteps is List
          ? configuredSteps.whereType<Map>().map(_map).toList()
          : _defaultSteps;
      _setSteps(steps);
      if (mounted) setState(() {});
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Frontend settings could not load.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _setSteps(List<Map<String, dynamic>> steps) {
    for (final controller in _stepTitleControllers) {
      controller.dispose();
    }
    for (final controller in _stepDescriptionControllers) {
      controller.dispose();
    }
    _stepTitleControllers
      ..clear()
      ..addAll(
        steps.map(
          (step) => TextEditingController(text: _string(step['title'])),
        ),
      );
    _stepDescriptionControllers
      ..clear()
      ..addAll(
        steps.map(
          (step) => TextEditingController(text: _string(step['description'])),
        ),
      );
  }

  Future<void> _save() async {
    final token = _token;
    if (token == null || _saving) return;
    setState(() {
      _saving = true;
      _error = null;
    });

    final values = <String, dynamic>{
      for (final entry in _controllers.entries)
        entry.key: entry.value.text.trim(),
      'about.features': _featuresController.text
          .split(RegExp(r'[\r\n]+'))
          .map((line) => line.trim())
          .where((line) => line.isNotEmpty)
          .toList(),
      'about.how_it_works': [
        for (var index = 0; index < _stepTitleControllers.length; index++)
          {
            'number': '${index + 1}',
            'title': _stepTitleControllers[index].text.trim(),
            'description': _stepDescriptionControllers[index].text.trim(),
            'icon': _defaultSteps[index % _defaultSteps.length]['icon'],
            'color': _defaultSteps[index % _defaultSteps.length]['color'],
            'hasline': index < _stepTitleControllers.length - 1,
          },
      ],
    };

    try {
      for (final entry in values.entries) {
        await widget.api.put(
          'admin/settings/${Uri.encodeComponent(entry.key)}',
          token,
          {'value': entry.value, 'is_public': true},
        );
      }
      await widget.api.clearFrontendCache();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(content: Text('Landing page content saved.')),
        );
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted)
        setState(() => _error = 'Could not save landing page content.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Landing page content'),
        actions: [
          if (_token != null)
            IconButton(
              tooltip: 'Reload',
              onPressed: _loading || _saving ? null : _loadSettings,
              icon: const Icon(Icons.refresh_rounded),
            ),
        ],
      ),
      body: _token == null
          ? _loginView(colors)
          : _loading && _controllers.values.every((c) => c.text.isEmpty)
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                _introCard(colors),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  _errorCard(colors),
                ],
                const SizedBox(height: 16),
                _section(
                  colors,
                  title: 'Hero banner',
                  subtitle: 'Main landing page message and call to action.',
                  icon: Icons.wallpaper_rounded,
                  children: [
                    _field('about.hero_tagline', 'Headline'),
                    _field('about.what_we_do_body', 'Subtitle'),
                    _field('about.mission_body', 'Description', maxLines: 3),
                    _field('about.hero_image_url', 'Hero image URL'),
                    _field('about.hero_cta_text', 'Button label'),
                    _field('about.hero_cta_link', 'Button link'),
                  ],
                ),
                const SizedBox(height: 14),
                _section(
                  colors,
                  title: 'How it works',
                  subtitle: 'Edit the steps displayed on the landing page.',
                  icon: Icons.route_rounded,
                  children: [
                    for (
                      var index = 0;
                      index < _stepTitleControllers.length;
                      index++
                    ) ...[
                      if (index > 0) const Divider(height: 24),
                      Text(
                        'STEP ${index + 1}',
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: colors.primary,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _stepTitleControllers[index],
                        decoration: const InputDecoration(labelText: 'Title'),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _stepDescriptionControllers[index],
                        minLines: 2,
                        maxLines: 3,
                        decoration: const InputDecoration(
                          labelText: 'Description',
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 14),
                _section(
                  colors,
                  title: 'Features',
                  subtitle: 'One feature per line.',
                  icon: Icons.auto_awesome_rounded,
                  children: [
                    TextFormField(
                      controller: _featuresController,
                      minLines: 3,
                      maxLines: 7,
                      decoration: const InputDecoration(
                        labelText: 'Landing page features',
                        alignLabelWithHint: true,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _section(
                  colors,
                  title: 'Contact and social links',
                  subtitle: 'Public contact details shown near the bottom.',
                  icon: Icons.contact_support_outlined,
                  children: [
                    _field('about.contact_email', 'Contact email'),
                    _field('about.contact_phone', 'Contact phone'),
                    _field('about.contact_address', 'Address', maxLines: 2),
                    for (final network in const [
                      'facebook',
                      'twitter',
                      'instagram',
                      'tiktok',
                      'telegram',
                    ])
                      _field(
                        'about.social_links.$network',
                        '${network[0].toUpperCase()}${network.substring(1)} URL',
                      ),
                  ],
                ),
                const SizedBox(height: 14),
                _section(
                  colors,
                  title: 'Support and footer',
                  subtitle: 'Support destinations and footer copy.',
                  icon: Icons.support_agent_rounded,
                  children: [
                    _field('about.supports.link1', 'Community link'),
                    _field('about.supports.link2', 'Support link'),
                    _field('about.footer_copyright', 'Copyright line'),
                    _field('about.footer_note', 'Footer note'),
                  ],
                ),
                const SizedBox(height: 14),
                _managedContentCard(colors),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: _saving || _loading ? null : _save,
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_rounded),
                  label: Text(
                    _saving ? 'Saving content…' : 'Save landing content',
                  ),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _loginView(ColorScheme colors) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 460),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(22),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(
                    Icons.admin_panel_settings_rounded,
                    size: 42,
                    color: colors.primary,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Admin sign-in',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 18),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'Admin email',
                      prefixIcon: Icon(Icons.email_outlined),
                    ),
                    validator: (value) => value == null || value.trim().isEmpty
                        ? 'Enter your admin email.'
                        : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _password,
                    obscureText: _obscurePassword,
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      suffixIcon: IconButton(
                        onPressed: () => setState(
                          () => _obscurePassword = !_obscurePassword,
                        ),
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                      ),
                    ),
                    validator: (value) => value == null || value.isEmpty
                        ? 'Enter your password.'
                        : null,
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(_error!, style: TextStyle(color: colors.error)),
                  ],
                  const SizedBox(height: 18),
                  FilledButton(
                    onPressed: _loading ? null : _login,
                    child: Text(_loading ? 'Signing in…' : 'Sign in'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );

  Widget _introCard(ColorScheme colors) => Card(
    color: colors.primaryContainer,
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          Icon(
            Icons.public_rounded,
            color: colors.onPrimaryContainer,
            size: 30,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              'Edit the public copy served by /api/v1/frontend. Changes are saved as public system settings.',
              style: TextStyle(color: colors.onPrimaryContainer, height: 1.4),
            ),
          ),
        ],
      ),
    ),
  );

  Widget _errorCard(ColorScheme colors) => Card(
    color: colors.errorContainer,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Text(_error!, style: TextStyle(color: colors.onErrorContainer)),
    ),
  );

  Widget _section(
    ColorScheme colors, {
    required String title,
    required String subtitle,
    required IconData icon,
    required List<Widget> children,
  }) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: colors.primary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(subtitle, style: TextStyle(color: colors.onSurfaceVariant)),
          const SizedBox(height: 14),
          for (var index = 0; index < children.length; index++) ...[
            children[index],
            if (index < children.length - 1) const SizedBox(height: 10),
          ],
        ],
      ),
    ),
  );

  Widget _field(String key, String label, {int maxLines = 1}) => TextField(
    controller: _controllers[key],
    minLines: maxLines == 1 ? 1 : maxLines,
    maxLines: maxLines,
    decoration: InputDecoration(
      labelText: label,
      alignLabelWithHint: maxLines > 1,
      border: const OutlineInputBorder(),
    ),
  );

  Widget _managedContentCard(ColorScheme colors) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Database-managed sections',
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Text(
            'Featured listings, payment methods, FAQs, testimonials, platform stats and leaderboard entries come from their records and are not edited as copy here.',
            style: TextStyle(color: colors.onSurfaceVariant, height: 1.4),
          ),
          const SizedBox(height: 12),
          _moduleLink(
            icon: Icons.apps_rounded,
            title: 'Manage app listings',
            onTap: () => _push(AdminAppListingsScreen(api: widget.api)),
          ),
          _moduleLink(
            icon: Icons.campaign_rounded,
            title: 'Manage sponsored ads',
            onTap: () => _push(AdminAdsScreen(api: widget.api)),
          ),
          _moduleLink(
            icon: Icons.payments_rounded,
            title: 'Payment gateway settings',
            onTap: () => _push(AdminPaymentGatewaysScreen(api: widget.api)),
          ),
        ],
      ),
    ),
  );

  Widget _moduleLink({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: Icon(icon),
    title: Text(title),
    trailing: const Icon(Icons.chevron_right_rounded),
    onTap: onTap,
  );

  void _push(Widget page) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));
  }
}
