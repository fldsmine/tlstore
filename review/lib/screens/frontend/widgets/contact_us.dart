import 'package:flutter/material.dart';
import 'package:review/config/api_config.dart';
import 'package:url_launcher/url_launcher.dart';

class ContactUs extends StatefulWidget {
  final Map<String, dynamic> contact;
  final List<String> support;

  const ContactUs({
    super.key, 
    required this.contact, 
    required this.support, 
  });

  @override
  State<ContactUs> createState() => _ContactUsState();
}

class _ContactUsState extends State<ContactUs> {

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
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Support',
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              'Need help? Message us',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: colors.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.centerRight,
                    child: Wrap(
                      alignment: WrapAlignment.end,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (
                          var index = 0;
                          index < supportLinks.length;
                          index++
                        )
                          FilledButton(
                            onPressed: () => _openLink(supportLinks[index]),
                            style: FilledButton.styleFrom(
                              shape: const StadiumBorder(),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 14,
                                vertical: 9,
                              ),
                            ),
                            child: Text(
                              supportLinks.length == 1
                                  ? '+ Ticket'
                                  : index == 0
                                  ? '+ Community'
                                  : '+ Ticket',
                            ),
                          ),
                      ],
                    ),
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
