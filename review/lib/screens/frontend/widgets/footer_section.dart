import 'package:flutter/material.dart';
import 'package:review/config/api_config.dart';
import 'package:review/services/auth_api.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../about_screen.dart';
import '../../legal_document_screen.dart';

class FooterSection extends StatefulWidget {

  final Map<String, dynamic> footer;
  final AuthApi api;

  const FooterSection({
    super.key, 
    required this.footer, 
    required this.api, 
  });


  @override
  State<FooterSection> createState() => _FooterSectionState();
}

class _FooterSectionState extends State<FooterSection> {
  int _selectedNav = 0;

  Future<void> _pushPage(Widget page, int navIndex) async {
    setState(() => _selectedNav = navIndex);
    await Navigator.of(context)
        .push(MaterialPageRoute<void>(builder: (_) => page));
    if (mounted) {
      setState(() => _selectedNav = 0);
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
    final colors = theme.colorScheme;
    final copyright = _text(
      widget.footer['copyright'],
      '© TLStore. All rights reserved.',
    );
    final note = _text(
      widget.footer['note'],
      'Built for people who make apps better.',
    );
    
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 28, 20, 22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [colors.surfaceContainerHigh, const Color(0xFF5C519B)],
        ),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(30)),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surface,
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.2),
                      blurRadius: 20,
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.asset(
                    'assets/logo.jpg',
                    fit: BoxFit.cover,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                'tlstore',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurface,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            note,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: colors.onSurface.withValues(alpha: .9),
            ),
          ),
          const SizedBox(height: 18),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 10,
            runSpacing: 4,
            children: [
              TextButton(
                onPressed: () {
                  _pushPage(LegalDocumentScreen(api: widget.api, slug: 'privacy'), 0);
                },
                child: Text(
                  'Privacy',
                  style: TextStyle(color: colors.onSurface),
                ),
              ),
              TextButton(
                onPressed: () {
                  _pushPage(LegalDocumentScreen(api: widget.api, slug: 'terms'), 0);
                },
                child: Text('Terms', style: TextStyle(color: colors.onSurface)),
              ),
              TextButton(
                onPressed: () {},
                child: Text(
                  'Support',
                  style: TextStyle(color: colors.onSurface),
                ),
              ),
              TextButton(
                onPressed: () {
                  _pushPage(AboutScreen(api: widget.api), 0);
                },
                child: Text('About', style: TextStyle(color: colors.onSurface)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Divider(color: colors.onSurface.withValues(alpha: .22)),
          const SizedBox(height: 10),
          Text(
            copyright,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors.onSurface.withValues(alpha: .85),
            ),
          ),
          if (widget.footer['version'] != null) ...[
            const SizedBox(height: 4),
            Text(
              'Version ${widget.footer['version']}',
              style: theme.textTheme.labelSmall?.copyWith(
                color: colors.onSurface.withValues(alpha: .7),
              ),
            ),
          ],
        ],
      ),
    );

  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }


}
