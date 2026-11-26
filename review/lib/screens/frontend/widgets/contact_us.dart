import 'package:flutter/material.dart';
import 'package:review/config/api_config.dart';
import 'package:review/services/auth_api.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../widgets/auth_components.dart';
import '../../home_screen.dart';
import '../../support_ticket_screen.dart';

class ContactUs extends StatefulWidget {

  const ContactUs({
    super.key,
    required this.api,
    required this.token,
    required this.contact,
    required this.support,
    required this.user,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> contact;
  final List<String> support;
  final Map<String, dynamic> user;

  @override
  State<ContactUs> createState() => _ContactUsState();
}

class _ContactUsState extends State<ContactUs> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  final _api = AuthApi();
  bool _showForm = false;
  bool _submitting = false;


  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  void _openTickets(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            SupportTicketsScreen(api: widget.api, token: widget.token, user: widget.user),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _submitting = true);

    try {
      final result = await _api.support(
          name: _nameController.text.trim(),
          email: _emailController.text.trim(),
          subject: _subjectController.text.trim(),
          message: _messageController.text.trim(),
      );

      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(
          builder: (_) => HomeScreen(result: result, api: _api),
        ),
        (_) => false,
      );
    } on AuthException catch (error) {
      if (mounted) showAuthMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _openLink(String value) async {
    final raw = value.trim();
    if (raw.isEmpty) return;
    Uri? uri = Uri.tryParse(raw);
    if (uri == null) return;
    if (!uri.hasScheme) {
      final base = Uri.parse(ApiConfig.webAppBaseUrl);
      uri = base.resolve(raw.startsWith('/') ? raw : '/$raw');
    }
    try {
      await launchUrl(uri, mode: LaunchMode.platformDefault);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This link could not be opened.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {

    final theme = Theme.of(context);
    final contact = widget.contact;
    final colors = theme.colorScheme;
    final isUser = false;

    final social = _asMap(contact['social_links']);
    final email = _text(contact['email'], '');
    final phone = _text(contact['phone'], '');
    final address = _text(contact['address'], '');
    final supportLinks = widget.support;
    final socials = social.entries
        .where(
          (entry) =>
              entry.value != null && entry.value.toString().trim().isNotEmpty,
        )
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [colors.surfaceContainerHigh, const Color(0xFF6C60AF)],
              ),
              borderRadius: BorderRadius.circular(24),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [

                Row(
                  children: [
                    Icon(
                      Icons.support_agent_rounded,
                      color: colors.onSurface,
                      size: 26,
                    ),
                    const SizedBox(width: 10),
                    Text(
                      'Get in Touch',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: colors.onSurface,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                if (email.isNotEmpty)
                  _contactRow(
                    context,
                    Icons.email_outlined,
                    'Email',
                    email,
                    () => _openLink('mailto:$email'),
                  ),
                if (phone.isNotEmpty)
                  _contactRow(
                    context,
                    Icons.phone_outlined,
                    'Phone',
                    phone,
                    () => _openLink('tel:$phone'),
                  ),
                if (address.isNotEmpty)
                  _contactRow(
                    context,
                    Icons.location_on_outlined,
                    'Address',
                    address,
                    null,
                  ),
                if (email.isEmpty && phone.isEmpty && address.isEmpty)
                  _contactRow(
                    context,
                    Icons.support_agent_rounded,
                    'Support',
                    'Send us a message',
                    supportLinks.isEmpty
                        ? null
                        : () => _openLink(supportLinks.first),
                  ),
                if (socials.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      for (final entry in socials)
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 5),
                          child: IconButton.filled(
                            tooltip: _label(entry.key),
                            onPressed: () => _openLink(entry.value.toString()),
                            icon: Icon(_socialIcon(entry.key), size: 20),
                          ),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
        if (supportLinks.isNotEmpty) ...[
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Container(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
              decoration: BoxDecoration(
                color: colors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: colors.outlineVariant),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [

                  Row(
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: colors.primary.withValues(alpha: .18),
                          borderRadius: BorderRadius.circular(13),
                        ),
                        alignment: Alignment.center,
                        child: Icon(
                          Icons.support_agent_rounded,
                          color: colors.primary,
                        ),
                      ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                        'Support',
                        style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        Text(
                        'Need help? Message us',
                        style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                      ),
                    ),
                    if (!_showForm)
                      GestureDetector(
                      onTap: () => isUser ? _openTickets(context) : setState(() => _showForm = true),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 6,
                        ),
                        decoration: BoxDecoration(
                        color: colors.primary,
                        borderRadius:
                          BorderRadius.circular(20),
                        ),
                        child: Text(
                        isUser ? '+ Ticket':'+ Message us',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                        ),
                      ),
                      ),
                    ],
                  ),

                // Form
                  if (_showForm) ...[
                    const SizedBox(height: 24),
                    Form(
                      key: _formKey,
                      child: Column(
                        children: [
                          TextFormField(
                            controller: _nameController,
                            decoration: const InputDecoration(
                              hintText: 'Your Name',
                              prefixIcon: Icon(Icons.person_outline),
                            ),
                            validator: (v) =>
                                (v == null || v.trim().isEmpty) ? 'Required' : null,
                          ),
                          const SizedBox(height: 24),
                          TextFormField(
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(
                              hintText: 'Email Address',
                              prefixIcon: Icon(Icons.email_outlined),
                            ),
                            validator: (v) =>
                                (v == null || v.trim().isEmpty) ? 'Required' : null,
                          ),
                          const SizedBox(height: 24),
                          TextFormField(
                            controller: _subjectController,
                            decoration: const InputDecoration(
                              hintText: 'Subject',
                              prefixIcon: Icon(Icons.subject),
                            ),
                            validator: (v) =>
                                (v == null || v.trim().isEmpty) ? 'Required' : null,
                          ),
                          const SizedBox(height: 24),
                          TextFormField(
                            controller: _messageController,
                            maxLines: 4,
                            decoration: const InputDecoration(
                              hintText: 'Your Message',
                              prefixIcon: Icon(Icons.message_outlined),
                            ),
                            validator: (v) =>
                                (v == null || v.trim().isEmpty) ? 'Required' : null,
                          ),
                          const SizedBox(height: 30),
                    Row(
                    children: [
                      Expanded(
                      child: ElevatedButton(
                        onPressed: _submitting ? null : _submit,
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          elevation: 5,
                           side: BorderSide(
                            color: colors.primary, 
                            width: 0.5,
                          ),
                        ),
                        child: _submitting
                          ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                            ),
                          )
                          : const Text('Submit'),
                      ),
                      ),
                      const SizedBox(width: 8),
                      TextButton(
                      onPressed: () => setState(() => _showForm = false),
                      child: const Text('Cancel'),
                      ),
                    ],
                    ),
                        ],
                      ),
                    ),
                  ],

                  const SizedBox(height: 22),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      const spacing = 8.0;
                      const buttonsPerRow = 2;

                      final buttonWidth =
                          (constraints.maxWidth - spacing) / buttonsPerRow;

                      return Wrap(
                        alignment: WrapAlignment.end,
                        spacing: spacing,
                        runSpacing: 8,
                        children: [
                          for (var index = 0; index < supportLinks.length; index++)
                            SizedBox(
                              width: buttonWidth,
                              child: FilledButton(
                                onPressed: () => _openLink(supportLinks[index]),
                                style: FilledButton.styleFrom(
                                  shape: const StadiumBorder(),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 9,
                                  ),
                                ),
                                child: Text(
                                  supportLinks.length == 1
                                      ? '+ Ticket'
                                      : index == 0
                                          ? '+ Community'
                                          : '+ Ticket',
                                  textAlign: TextAlign.center,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ),
                        ],
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ],
        const SizedBox(height: 20),
      ],
    );

  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  String _label(String value) => value
      .split(RegExp(r'[_\-\s]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');

  Widget _contactRow(
    BuildContext context,
    IconData icon,
    String label,
    String value,
    VoidCallback? onTap,
  ) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: colors.onSurface.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(13),
                ),
                alignment: Alignment.center,
                child: Icon(icon, color: colors.onSurface),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    Text(
                      value,
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: colors.onSurface,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }

  Map<String, dynamic> _asMap(Object? value) => value is Map
      ? value.map((key, value) => MapEntry(key.toString(), value))
      : <String, dynamic>{};

  IconData _socialIcon(String value) => switch (value.toLowerCase()) {
    'facebook' => Icons.facebook_rounded,
    'instagram' => Icons.camera_alt_rounded,
    'twitter' || 'x' => Icons.alternate_email_rounded,
    'telegram' => Icons.send_rounded,
    'tiktok' => Icons.music_note_rounded,
    _ => Icons.public_rounded,
  };

}