import 'dart:async';
import 'dart:math' as math;
import 'dart:ui' show FontFeature;

import 'package:flutter/material.dart';

/// Full-screen, data-driven connection preview for a user's active add-on.
///
/// This page presents the connection details made available by the add-on API.
/// It is a UI preview only; it does not establish a VPN/proxy tunnel.
class ProxyConnectionDialog extends StatefulWidget {
  const ProxyConnectionDialog({
    super.key,
    required this.addonState,
    required this.user,
  });

  final Map<String, dynamic> addonState;
  final Map<String, dynamic> user;

  @override
  State<ProxyConnectionDialog> createState() => _ProxyConnectionDialogState();
}

class _ProxyConnectionDialogState extends State<ProxyConnectionDialog> {
  Timer? _ticker;
  int _elapsedSeconds = 0;
  int _selectedServer = 0;
  bool _connected = true;

  Map<String, dynamic> _map(Object? value) => value is Map
      ? value.map<String, dynamic>(
          (key, value) => MapEntry(key.toString(), value),
        )
      : <String, dynamic>{};

  Map<String, dynamic> get _addon => _map(widget.addonState['addon']);
  Map<String, dynamic> get _plan =>
      _map(_addon['plan'] ?? widget.addonState['plan']);
  Map<String, dynamic> get _entitlement {
    final nested = _map(widget.addonState['active_entitlement']);
    if (nested.isNotEmpty) return nested;
    final status = widget.addonState['status']?.toString().toLowerCase();
    return status == 'active' || status == 'trialing'
        ? widget.addonState
        : const <String, dynamic>{};
  }

  Map<String, dynamic> get _features {
    final entitlementFeatures = _map(_entitlement['features']);
    if (entitlementFeatures.isNotEmpty) return entitlementFeatures;
    return _map(_addon['features']);
  }

  List<_ConnectionServer> get _servers {
    final features = _features;
    final locations = features['location'] ?? features['locations'];
    final codes = features['country_code'];
    final ips = _map(features['ip_addresses']);
    final ipEntries = ips.entries.toList(growable: false);
    if (locations is List && locations.isNotEmpty) {
      return [
        for (var index = 0; index < locations.length; index++)
          _ConnectionServer.fromValues(
            locations[index]?.toString() ?? 'Secure location ${index + 1}',
            codes is List && index < codes.length
                ? codes[index]?.toString()
                : null,
            _ipForLocation(ips, ipEntries, codes, index),
            index,
          ),
      ];
    }
    if (ips.isNotEmpty) {
      return [
        for (var index = 0; index < ipEntries.length; index++)
          _ConnectionServer.fromValues(
            _countryName(ipEntries[index].key),
            ipEntries[index].key,
            ipEntries[index].value?.toString(),
            index,
          ),
      ];
    }
    return const [
      _ConnectionServer(
        name: 'Secure location',
        country: 'Location not provided',
        code: 'UN',
      ),
    ];
  }

  String? _ipForLocation(
    Map<String, dynamic> ips,
    List<MapEntry<String, dynamic>> entries,
    Object? codes,
    int index,
  ) {
    if (codes is List && index < codes.length) {
      final code = codes[index]?.toString();
      if (code != null && ips[code] != null) return ips[code].toString();
    }
    if (index < entries.length) return entries[index].value?.toString();
    return null;
  }

  String _countryName(String code) {
    switch (code.toUpperCase()) {
      case 'US':
      case 'USA':
        return 'United States';
      case 'NG':
        return 'Nigeria';
      case 'GB':
      case 'UK':
        return 'United Kingdom';
      case 'DE':
        return 'Germany';
      default:
        return code;
    }
  }

  @override
  void initState() {
    super.initState();
    final status = _entitlement['status']?.toString().toLowerCase();
    _connected =
        _entitlement.isNotEmpty &&
        (status == null || status == 'active' || status == 'trialing');
    final expires = DateTime.tryParse(
      _entitlement['ends_at']?.toString() ?? '',
    );
    if (expires != null && expires.isBefore(DateTime.now())) _connected = false;
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted || !_connected) return;
      setState(() => _elapsedSeconds++);
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  String get _duration {
    final hours = (_elapsedSeconds ~/ 3600).toString().padLeft(2, '0');
    final minutes = ((_elapsedSeconds ~/ 60) % 60).toString().padLeft(2, '0');
    final seconds = (_elapsedSeconds % 60).toString().padLeft(2, '0');
    return '$hours:$minutes:$seconds';
  }

  String get _addonName => _addon['name']?.toString() ?? 'Secure add-on';
  String get _planName => _plan['name']?.toString() ?? 'Your active package';
  String get _endsAt => _entitlement['ends_at']?.toString() ?? '';

  String _formatDate(String value) {
    final date = DateTime.tryParse(value)?.toLocal();
    if (date == null) return 'Not provided';
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  Future<void> _chooseServer() async {
    final servers = _servers;
    final picked = await showModalBottomSheet<int>(
      context: context,
      backgroundColor: Theme.of(context).colorScheme.surface,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) {
        final colors = Theme.of(context).colorScheme;
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 2, 18, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Available locations',
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 5),
                Text(
                  'Locations supplied with your add-on.',
                  style: Theme.of(context).textTheme.bodySmall
                      ?.copyWith(color: colors.onSurfaceVariant),
                ),
                const SizedBox(height: 12),
                for (var index = 0; index < servers.length; index++)
                  ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                    leading: _FlagBadge(code: servers[index].code, size: 38),
                    title: Text(
                      servers[index].name,
                      style: Theme.of(context).textTheme.titleSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      servers[index].country,
                      style: Theme.of(context).textTheme.bodySmall
                          ?.copyWith(color: colors.onSurfaceVariant),
                    ),
                    trailing: index == _selectedServer
                        ? Icon(
                            Icons.check_circle_rounded,
                            color: colors.primary,
                          )
                        : null,
                    onTap: () => Navigator.pop(context, index),
                  ),
                const SizedBox(height: 7),
                Text(
                  'Connection controls are a visual preview; no network tunnel is started.',
                  style: Theme.of(context).textTheme.labelSmall
                      ?.copyWith(color: colors.onSurfaceVariant, height: 1.35),
                ),
              ],
            ),
          ),
        );
      },
    );
    if (picked != null && mounted) setState(() => _selectedServer = picked);
  }

  void _showAddonInfo() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Theme.of(context).colorScheme.surface,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) {
        final colors = Theme.of(context).colorScheme;
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 4, 22, 26),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _addonName,
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 5),
                Text(
                  _planName,
                  style: Theme.of(context).textTheme.bodyMedium
                      ?.copyWith(color: colors.onSurfaceVariant),
                ),
                const SizedBox(height: 14),
                _infoLine(
                  Icons.verified_user_outlined,
                  'Add-on status',
                  _entitlement['status']?.toString() ?? 'Active',
                ),
                _infoLine(
                  Icons.event_available_outlined,
                  'Valid until',
                  _endsAt.isEmpty ? 'Not provided' : _formatDate(_endsAt),
                ),
                _infoLine(
                  Icons.public_rounded,
                  'Locations',
                  '${_servers.length} available',
                ),
                const SizedBox(height: 10),
                Text(
                  'This page previews the connection details associated with your proxy.',
                  style: Theme.of(context).textTheme.bodySmall
                      ?.copyWith(color: colors.onSurfaceVariant, height: 1.45),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _infoLine(IconData icon, String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      children: [
        Icon(icon, color: Theme.of(context).colorScheme.primary, size: 19),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium
              ?.copyWith(fontWeight: FontWeight.w700),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final background = colors.surface;
    final primaryText = colors.onSurface;
    final secondaryText = colors.onSurfaceVariant;
    final panel = colors.surfaceContainerLow;
    final server =
        _servers[_selectedServer.clamp(0, _servers.length - 1).toInt()];
    final features = _features;
    final dedicatedIps =
        features['dedicated_ips'] == true || features['dedicated_ips'] == 1;
    final tunnelEncryption =
        features['encrypted_tunnel'] == true ||
        features['encrypted_tunnel'] == 1;
    final userName = widget.user['full_name']?.toString().trim();

    return Scaffold(
      backgroundColor: background,
      body: Stack(
        children: [
          Positioned.fill(
            child: CustomPaint(
              painter: _ConnectionGridPainter(
                dark: dark,
                foreground: colors.onSurface,
                accent: colors.primary,
              ),
            ),
          ),
          SafeArea(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 540),
                child: Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(18, 9, 18, 8),
                      child: Row(
                        children: [
                          _RoundIconButton(
                            icon: Icons.menu_rounded,
                            onTap: _showAddonInfo,
                            tooltip: 'Add-on information',
                          ),
                          const Spacer(),
                          const _BrandLockup(),
                          const Spacer(),
                          const SizedBox(width: 48),
                        ],
                      ),
                    ),
                    Expanded(
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          return SingleChildScrollView(
                            physics: const BouncingScrollPhysics(),
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                            child: Column(
                              children: [
                                _LocationCard(
                                  server: server,
                                  panel: panel,
                                  primaryText: primaryText,
                                  secondaryText: secondaryText,
                                  onTap: _chooseServer,
                                ),
                                const SizedBox(height: 35),
                                Text(
                                  _duration,
                                  style: TextStyle(
                                    color: primaryText,
                                    fontSize: 43,
                                    letterSpacing: 3.3,
                                    fontWeight: FontWeight.w700,
                                    fontFeatures: const [
                                      FontFeature.tabularFigures(),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 10),
                                _StatusChip(connected: _connected),
                                const SizedBox(height: 22),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(
                                      Icons.language_rounded,
                                      color: colors.primary,
                                      size: 18,
                                    ),
                                    const SizedBox(width: 9),
                                    Flexible(
                                      child: Text(
                                        server.ip ?? 'IP not provided',
                                        style: TextStyle(
                                          color: primaryText,
                                          fontFamily: 'monospace',
                                          fontSize: 16,
                                          letterSpacing: 1.5,
                                        ),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    const SizedBox(width: 9),
                                    Flexible(
                                      child: Text(
                                        '${server.name}, ${server.country}',
                                        style: TextStyle(
                                          color: secondaryText,
                                          fontSize: 12,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ],
                                ),
                                if (userName != null &&
                                    userName.isNotEmpty) ...[
                                  const SizedBox(height: 8),
                                  Text(
                                    'SECURE PROFILE  ·  $userName',
                                    style: TextStyle(
                                      color: secondaryText.withValues(
                                        alpha: .75,
                                      ),
                                      fontSize: 9,
                                      letterSpacing: 1.4,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 16),
                                ConstrainedBox(
                                  constraints: BoxConstraints(
                                    maxHeight: math.max(
                                      290.0,
                                      math.min(
                                        390.0,
                                        constraints.maxHeight * .49,
                                      ),
                                    ),
                                  ),
                                  child: Center(
                                    child: AspectRatio(
                                      aspectRatio: 1,
                                      child: _ConnectionPower(
                                        connected: _connected,
                                        onTap: () => setState(
                                          () => _connected = !_connected,
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: _MetricCard(
                                        icon: Icons.south_rounded,
                                        title: 'DOWNLOAD',
                                        value: _speedValue(
                                          features['download_speed'],
                                          '12.4 MB/s',
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _MetricCard(
                                        icon: Icons.north_rounded,
                                        title: 'UPLOAD',
                                        value: _speedValue(
                                          features['upload_speed'],
                                          '3.1 MB/s',
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _MetricCard(
                                        icon: Icons.memory_rounded,
                                        title: 'CPU',
                                        value:
                                            features['cpu_usage']?.toString() ??
                                            '14%',
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 13,
                                    vertical: 11,
                                  ),
                                  decoration: BoxDecoration(
                                    color: panel.withValues(alpha: .85),
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(
                                      color: colors.outlineVariant,
                                    ),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        tunnelEncryption
                                            ? Icons.lock_rounded
                                            : Icons.shield_outlined,
                                        color: colors.primary,
                                        size: 17,
                                      ),
                                      const SizedBox(width: 9),
                                      Expanded(
                                        child: Text(
                                          tunnelEncryption
                                              ? 'Encrypted tunnel included'
                                              : 'Secure add-on preview',
                                          style: TextStyle(
                                            color: primaryText,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ),
                                      if (dedicatedIps)
                                        Text(
                                          'DEDICATED IP',
                                          style: theme.textTheme.labelSmall
                                              ?.copyWith(
                                                color: colors.primary,
                                                fontSize: 9,
                                                letterSpacing: 1,
                                                fontWeight: FontWeight.w900,
                                              ),
                                        ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 10),
                                Text(
                                  'CONNECTION PREVIEW  ·  No network tunnel is started',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: secondaryText.withValues(alpha: .8),
                                    fontSize: 9,
                                    letterSpacing: 1.1,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                if (_endsAt.isNotEmpty) ...[
                                  const SizedBox(height: 5),
                                  Text(
                                    'Add-on active until ${_formatDate(_endsAt)}',
                                    style: TextStyle(
                                      color: secondaryText,
                                      fontSize: 10,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _speedValue(Object? value, String fallback) {
    if (value == null) return fallback;
    return value is num ? '${value.toStringAsFixed(1)} MB/s' : value.toString();
  }
}

class _ConnectionServer {
  const _ConnectionServer({
    required this.name,
    required this.country,
    required this.code,
    this.ip,
  });

  factory _ConnectionServer.fromValues(
    String location,
    String? code,
    String? ip,
    int index,
  ) {
    final normalized = location.trim().isEmpty
        ? 'Secure location'
        : location.trim();
    var countryName = normalized.replaceAll('_', ' ');
    if (countryName.toLowerCase() == 'united state')
      countryName = 'United States';
    final inferredCode = _guessCode(countryName);
    final chosenCode = inferredCode != 'UN'
        ? inferredCode
        : code?.trim().isNotEmpty == true
        ? code!.trim().toUpperCase()
        : 'UN';
    final name =
        '${countryName.split(RegExp(r'[,·]')).first.trim()} ${index + 1}';
    return _ConnectionServer(
      name: name,
      country: countryName,
      code: chosenCode,
      ip: ip,
    );
  }

  static String _guessCode(String name) {
    final lower = name.toLowerCase();
    if (lower.contains('nigeria')) return 'NG';
    if (lower.contains('united kingdom') || lower == 'uk') return 'GB';
    if (lower.contains('united state') || lower == 'usa') return 'US';
    if (lower.contains('germany')) return 'DE';
    return 'UN';
  }

  final String name;
  final String country;
  final String code;
  final String? ip;
}

class _BrandLockup extends StatelessWidget {
  const _BrandLockup();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const _ShieldMark(size: 43),
        const SizedBox(width: 10),
        Text(
          'TLSTORE PROXY',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: colors.onSurface,
            fontSize: 18,
            fontWeight: FontWeight.w800,
            letterSpacing: -.5,
          ),
        ),
      ],
    );
  }
}

class _ShieldMark extends StatelessWidget {
  const _ShieldMark({required this.size});
  final double size;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: colors.primary, shape: BoxShape.circle),
      child: Icon(
        Icons.shield_outlined,
        size: size * .68,
        color: colors.onPrimary,
      ),
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  const _RoundIconButton({
    required this.icon,
    required this.onTap,
    required this.tooltip,
  });
  final IconData icon;
  final VoidCallback onTap;
  final String tooltip;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Tooltip(
      message: tooltip,
      child: Material(
        color: colors.surfaceContainerLow,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: colors.outlineVariant),
            ),
            child: Icon(icon, color: colors.onSurface, size: 24),
          ),
        ),
      ),
    );
  }
}

class _LocationCard extends StatelessWidget {
  const _LocationCard({
    required this.server,
    required this.panel,
    required this.primaryText,
    required this.secondaryText,
    required this.onTap,
  });
  final _ConnectionServer server;
  final Color panel;
  final Color primaryText;
  final Color secondaryText;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: panel,
    borderRadius: BorderRadius.circular(25),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(25),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(25),
          border: Border.all(
            color: Theme.of(context).colorScheme.outlineVariant,
          ),
          boxShadow: [
            BoxShadow(
              color: Theme.of(context).colorScheme.shadow
                  .withValues(alpha: .10),
              blurRadius: 22,
              offset: const Offset(0, 9),
            ),
          ],
        ),
        child: Row(
          children: [
            _FlagBadge(code: server.code, size: 54),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    server.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: primaryText,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    server.country,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: secondaryText, fontSize: 14),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Container(
              width: 45,
              height: 45,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: Theme.of(context).colorScheme.outlineVariant,
                ),
              ),
              child: Icon(
                Icons.arrow_forward_rounded,
                color: primaryText,
                size: 23,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _FlagBadge extends StatelessWidget {
  const _FlagBadge({required this.code, this.size = 38});
  final String code;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(size * .34),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
    ),
    child: ClipRRect(
      borderRadius: BorderRadius.circular(99),
      child: CustomPaint(
        size: Size(size * .68, size * .46),
        painter: _FlagPainter(code.toUpperCase()),
      ),
    ),
  );
}

class _FlagPainter extends CustomPainter {
  const _FlagPainter(this.code);
  final String code;

  @override
  void paint(Canvas canvas, Size size) {
    final bounds = Offset.zero & size;
    canvas.save();
    canvas.clipRRect(
      RRect.fromRectAndRadius(bounds, const Radius.circular(99)),
    );
    final colors = switch (code) {
      'DE' => const [Color(0xFF111111), Color(0xFFD51B2B), Color(0xFFF4C51D)],
      'NG' => const [Color(0xFF168447), Color(0xFFF4F5F2), Color(0xFF168447)],
      'US' => const [Color(0xFFB52332), Color(0xFFF4F5F2), Color(0xFFB52332)],
      'GB' ||
      'UK' => const [Color(0xFF27407F), Color(0xFFF4F5F2), Color(0xFFB52332)],
      _ => const [Color(0xFF2E6842), Color(0xFFE9EEE8), Color(0xFF2E6842)],
    };
    final paint = Paint();
    for (var index = 0; index < 3; index++) {
      paint.color = colors[index];
      canvas.drawRect(
        Rect.fromLTWH(
          0,
          size.height * index / 3,
          size.width,
          size.height / 3 + 1,
        ),
        paint,
      );
    }
    if (code == 'US') {
      paint.color = const Color(0xFF244078);
      canvas.drawRect(
        Rect.fromLTWH(0, 0, size.width * .42, size.height * .54),
        paint,
      );
    }
    if (code == 'GB' || code == 'UK') {
      paint.color = const Color(0xFFEEEFEA);
      paint.strokeWidth = size.height * .2;
      canvas.drawLine(Offset(0, 0), Offset(size.width, size.height), paint);
      canvas.drawLine(Offset(size.width, 0), Offset(0, size.height), paint);
      paint.color = const Color(0xFFB52332);
      paint.strokeWidth = size.height * .09;
      canvas.drawLine(Offset(0, 0), Offset(size.width, size.height), paint);
      canvas.drawLine(Offset(size.width, 0), Offset(0, size.height), paint);
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _FlagPainter oldDelegate) =>
      oldDelegate.code != code;
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.connected});
  final bool connected;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final accent = connected ? colors.primary : colors.error;
    final pill = connected ? colors.primaryContainer : colors.errorContainer;
    final onPill = connected
        ? colors.onPrimaryContainer
        : colors.onErrorContainer;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: pill,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: accent, shape: BoxShape.circle),
          ),
          const SizedBox(width: 8),
          Text(
            connected ? 'CONNECTED' : 'DISCONNECTED',
            style: TextStyle(
              color: onPill,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.1,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _ConnectionPower extends StatelessWidget {
  const _ConnectionPower({required this.connected, required this.onTap});
  final bool connected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final colors = Theme.of(context).colorScheme;
      final size = math.min(constraints.maxWidth, constraints.maxHeight);
      return Stack(
        alignment: Alignment.center,
        children: [
          for (var index = 0; index < 5; index++)
            Container(
              width: size * (.55 + index * .105),
              height: size * (.55 + index * .105),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: colors.primary.withValues(
                    alpha: connected ? .12 + (4 - index) * .035 : .07,
                  ),
                  width: index == 1 ? 2 : 1,
                ),
                boxShadow: index == 2 && connected
                    ? [
                        BoxShadow(
                          color: colors.primary.withValues(alpha: .12),
                          blurRadius: 28,
                          spreadRadius: 4,
                        ),
                      ]
                    : null,
              ),
            ),
          if (connected) ...[
            _Orb(size: size * .067, left: size * .08, top: size * .22),
            _Orb(
              size: size * .055,
              right: size * .1,
              top: size * .05,
              pale: true,
            ),
            _Orb(size: size * .075, right: size * .055, top: size * .55),
            _Orb(size: size * .052, left: size * .19, bottom: size * .08),
          ],
          GestureDetector(
            onTap: onTap,
            child: Container(
              width: size * .51,
              height: size * .51,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: colors.primary,
                border: Border.all(
                  color: colors.onPrimary.withValues(alpha: .18),
                  width: 2,
                ),
                boxShadow: [
                  BoxShadow(
                    color: colors.primary.withValues(
                      alpha: connected ? .34 : .12,
                    ),
                    blurRadius: 38,
                    spreadRadius: 9,
                  ),
                  BoxShadow(
                    color: colors.shadow.withValues(alpha: .3),
                    blurRadius: 8,
                    spreadRadius: -5,
                  ),
                ],
              ),
              child: Icon(
                Icons.power_settings_new_rounded,
                color: colors.onPrimary,
                size: size * .22,
              ),
            ),
          ),
        ],
      );
    },
  );
}

class _Orb extends StatelessWidget {
  const _Orb({
    required this.size,
    this.left,
    this.top,
    this.right,
    this.bottom,
    this.pale = false,
  });
  final double size;
  final double? left;
  final double? top;
  final double? right;
  final double? bottom;
  final bool pale;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final accent = pale ? colors.primaryContainer : colors.primary;
    return Positioned(
      left: left,
      top: top,
      right: right,
      bottom: bottom,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: accent,
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: colors.primary.withValues(alpha: .35),
              blurRadius: 15,
              spreadRadius: 2,
            ),
          ],
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.icon,
    required this.title,
    required this.value,
  });
  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final foreground = colors.onSurface;
    final muted = colors.onSurfaceVariant;
    return Container(
      height: 87,
      padding: const EdgeInsets.fromLTRB(11, 10, 8, 8),
      decoration: BoxDecoration(
        color: colors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.outlineVariant),
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withValues(alpha: .08),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Icon(icon, color: colors.primary, size: 16),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: muted,
                    fontSize: 9,
                    letterSpacing: 1.1,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: TextStyle(
                color: foreground,
                fontFamily: 'monospace',
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ConnectionGridPainter extends CustomPainter {
  const _ConnectionGridPainter({
    required this.dark,
    required this.foreground,
    required this.accent,
  });
  final bool dark;
  final Color foreground;
  final Color accent;

  @override
  void paint(Canvas canvas, Size size) {
    final grid = Paint()
      ..color = foreground.withValues(alpha: .055)
      ..strokeWidth = 1;
    const spacing = 104.0;
    for (double x = 0; x < size.width; x += spacing)
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), grid);
    for (double y = 0; y < size.height; y += spacing)
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    final glow = Paint()
      ..shader =
          RadialGradient(
            colors: [
              accent.withValues(alpha: dark ? .12 : .08),
              Colors.transparent,
            ],
          ).createShader(
            Rect.fromCircle(
              center: Offset(size.width * .5, size.height * .69),
              radius: size.width * .6,
            ),
          );
    canvas.drawRect(Offset.zero & size, glow);
  }

  @override
  bool shouldRepaint(covariant _ConnectionGridPainter oldDelegate) =>
      oldDelegate.dark != dark ||
      oldDelegate.foreground != foreground ||
      oldDelegate.accent != accent;
}
