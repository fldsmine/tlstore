import 'dart:math' as math;
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'dart:ui' show FontFeature;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';

import '../core/currency/money.dart';
import '../services/auth_api.dart';
import '../utils/receipt_delivery.dart';

/// Loads the PDF for one withdrawal. Return null (or throw) to surface an
/// error inside the dialog.
typedef WithdrawalReceiptBytesLoader =
    Future<Uint8List?> Function(WithdrawalReceipt receipt);

/// Where the finished PDF should go: save it, share it or open it.
typedef WithdrawalReceiptDeliverer =
    Future<void> Function(
      WithdrawalReceipt receipt,
      Uint8List bytes,
      String fileName,
    );

/// Opens the reusable withdrawal receipt dialog.
///
/// ```dart
/// // payload is the response of GET /withdrawals
/// final receipts = WithdrawalReceipt.listFromPayload(payload);
///
/// await showWithdrawalReceiptDialog(
///   context,
///   withdrawal: receipts.first,          // or the raw Map, it parses itself
///   appName: 'Magnus',
///   api: api,
///   token: token,
///   receiptPath: (receipt) => 'withdrawals/${receipt.id}/receipt',
///   onReceiptReady: (receipt, bytes, fileName) async {
///     await Share.shareXFiles([XFile.fromData(bytes, name: fileName)]);
///   },
/// );
/// ```
///
/// Everything is painted from [Theme.of], so the dialog follows whatever
/// colour scheme the user picked. The header uses `assets/logo.jpg`; if it is
/// missing, it falls back to a themed badge instead of crashing.
///
/// [withdrawal] accepts either one record straight out of the API payload
/// (`.../withdrawals` → `data.data[i]`) or an already parsed [WithdrawalReceipt].
///
/// The PDF is fetched through [onDownload]. If you would rather let the dialog
/// call your API, pass [api] and [token] and a [receiptPath] builder — the path
/// is never guessed, you own it. [onReceiptReady] receives the bytes so the
/// parent can write or share them.
///
/// Returns true when the receipt was downloaded before the dialog closed.
Future<bool?> showWithdrawalReceiptDialog(
  BuildContext context, {
  required Object withdrawal,
  String logoAsset = 'assets/logo.jpg',
  String? appName,
  String? title,
  String? subtitle,
  WithdrawalReceiptBytesLoader? onDownload,
  WithdrawalReceiptDeliverer? onReceiptReady,
  AuthApi? api,
  String? token,
  String Function(WithdrawalReceipt receipt)? receiptPath,
  bool showDownloadButton = true,
  bool barrierDismissible = true,
  
}) {
  final receipt = withdrawal is WithdrawalReceipt
      ? withdrawal
      : WithdrawalReceipt.fromMap(
          Map<String, dynamic>.from(withdrawal as Map<dynamic, dynamic>),
        );

  WithdrawalReceiptBytesLoader? loader = onDownload;

  if (loader == null && api != null && token != null && receiptPath != null) {
    loader = (WithdrawalReceipt item) async {
      final response = await api.download(receiptPath(item), token);
      return Uint8List.fromList(response.bodyBytes);
    };
  }

  return showDialog<bool>(
    context: context,
    barrierDismissible: barrierDismissible,
    builder: (BuildContext dialogContext) => WithdrawalReceiptDialog(
      receipt: receipt,
      logoAsset: logoAsset,
      appName: appName,
      title: title,
      subtitle: subtitle,
      loader: loader,
      onReceiptReady: onReceiptReady,
      showDownloadButton: showDownloadButton,
    ),
  );
}

/// One withdrawal, parsed from the API record into something a receipt can
/// render without any knowledge of JSON shapes.
class WithdrawalReceipt {
  const WithdrawalReceipt({
    required this.id,
    required this.reference,
    required this.amountMinor,
    required this.feeMinor,
    required this.currency,
    required this.status,
    this.methodLabel = 'Withdrawal',
    this.methodIcon,
    this.methodDescription,
    this.payoutDetails = const <String, String>{},
    this.sourceLabel,
    this.requestedAt,
    this.processedAt,
    this.failureReason,
  });

  final int id;
  final String reference;
  final int amountMinor;
  final int feeMinor;
  final String currency;
  final String status;
  final String methodLabel;
  final String? methodIcon;
  final String? methodDescription;
  final Map<String, String> payoutDetails;
  final String? sourceLabel;
  final DateTime? requestedAt;
  final DateTime? processedAt;
  final String? failureReason;

  /// What actually lands in the account.
  int get netMinor => amountMinor - feeMinor;

  bool get isPending => status == 'pending' || status == 'processing';
  bool get isFailed =>
      status == 'failed' || status == 'rejected' || status == 'cancelled';

  factory WithdrawalReceipt.fromMap(Map<String, dynamic> map) {
    final Map<String, dynamic> option = map['option'] is Map
        ? Map<String, dynamic>.from(map['option'] as Map)
        : <String, dynamic>{};

    final Map<String, dynamic> rawDetails = map['payout_details'] is Map
        ? Map<String, dynamic>.from(map['payout_details'] as Map)
        : <String, dynamic>{};

    final List<String> fieldOrder = option['fields'] is List
        ? (option['fields'] as List).map((dynamic e) => e.toString()).toList()
        : <String>[];

    final Map<String, String> details = <String, String>{};

    for (final String field in fieldOrder) {
      final String? value = _readable(rawDetails[field]);
      if (value != null) details[field] = value;
    }

    for (final MapEntry<String, dynamic> entry in rawDetails.entries) {
      if (details.containsKey(entry.key)) continue;
      final String? value = _readable(entry.value);
      if (value != null) details[entry.key] = value;
    }

    final int id = _asInt(map['id']);
    final String? providerReference = _readable(map['provider_reference']);
    final String? idempotencyKey = _readable(map['idempotency_key']);

    return WithdrawalReceipt(
      id: id,
      reference: providerReference ??
          _referenceFromKey(idempotencyKey) ??
          'WD-$id',
      amountMinor: _asInt(map['amount_minor']),
      feeMinor: _asInt(map['fee_minor']),
      currency: (_readable(map['currency']) ?? '').toUpperCase(),
      status: (_readable(map['status']) ?? 'pending').toLowerCase(),
      methodLabel: _readable(option['name']) ?? 'Withdrawal',
      methodIcon: _readable(option['icon']),
      methodDescription: _readable(option['description']),
      payoutDetails: details,
      sourceLabel: _sourceLabel(map),
      requestedAt: _parseDate(map['requested_at']),
      processedAt: _parseDate(map['processed_at']),
      failureReason: _readable(map['failure_reason']),
    );
  }

  /// Reads the paginated payload the withdrawals endpoint returns and pulls
  /// out every record, so a list screen can hand one straight to the dialog.
  static List<WithdrawalReceipt> listFromPayload(Map<String, dynamic> payload) {
    dynamic rows = payload['data'];

    if (rows is Map && rows['data'] is List) {
      rows = rows['data'];
    }

    if (rows is! List) return const <WithdrawalReceipt>[];

    return rows
        .whereType<Map<dynamic, dynamic>>()
        .map((Map<dynamic, dynamic> row) =>
            WithdrawalReceipt.fromMap(Map<String, dynamic>.from(row)))
        .toList(growable: false);
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.round();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String? _readable(dynamic value) {
    if (value == null) return null;
    final String text = value.toString().trim();
    return text.isEmpty ? null : text;
  }

  static DateTime? _parseDate(dynamic value) =>
      DateTime.tryParse(_readable(value) ?? '')?.toLocal();

  static String? _referenceFromKey(String? key) {
    if (key == null) return null;
    final String tail = key.split('-').last.trim();
    if (tail.isEmpty) return null;
    return 'WD-${tail.toUpperCase()}';
  }

  static String? _sourceLabel(Map<String, dynamic> map) {
    final Map<String, dynamic> wallet = map['wallet'] is Map
        ? Map<String, dynamic>.from(map['wallet'] as Map)
        : <String, dynamic>{};

    final String? name = _readable(wallet['name']) ?? _readable(map['wallet_name']);
    if (name != null) return name;

    final int walletId = _asInt(map['wallet_id']);
    return walletId > 0 ? 'Wallet #$walletId' : null;
  }
}

/// The dialog itself, public so a page can mount it directly if it prefers.
class WithdrawalReceiptDialog extends StatefulWidget {
  const WithdrawalReceiptDialog({
    super.key,
    required this.receipt,
    this.logoAsset = 'assets/logo.jpg',
    this.appName,
    this.title,
    this.subtitle,
    this.loader,
    this.onReceiptReady,
    this.showDownloadButton = true,
  });

  final WithdrawalReceipt receipt;
  final String logoAsset;
  final String? appName;
  final String? title;
  final String? subtitle;
  final WithdrawalReceiptBytesLoader? loader;
  final WithdrawalReceiptDeliverer? onReceiptReady;
  final bool showDownloadButton;

  @override
  State<WithdrawalReceiptDialog> createState() =>
      _WithdrawalReceiptDialogState();
}

class _WithdrawalReceiptDialogState extends State<WithdrawalReceiptDialog> {
  bool _downloading = false;
  bool _downloaded = false;
  bool _downloadingImage = false;
  bool _downloadedImage = false;
  String? _error;
  final GlobalKey _receiptImageKey = GlobalKey();

  WithdrawalReceipt get _receipt => widget.receipt;

  Future<void> _downloadPdf() async {
    final WithdrawalReceiptBytesLoader? loader = widget.loader;
    if (loader == null || _downloading || _downloadingImage) return;

    FocusScope.of(context).unfocus();
    setState(() {
      _downloading = true;
      _error = null;
    });

    try {
      final Uint8List? bytes = await loader(_receipt);

      if (!mounted) return;

      if (bytes == null || bytes.isEmpty) {
        setState(() {
          _downloading = false;
          _error = 'The receipt came back empty. Please try again.';
        });
        return;
      }

      if (!_isPdf(bytes)) {
        setState(() {
          _downloading = false;
          _error = 'The receipt service returned a file that is not a PDF.';
        });
        return;
      }

      final String fileName = 'withdrawal-${_receipt.reference}.pdf';

      final deliver = widget.onReceiptReady ??
          (WithdrawalReceipt _, Uint8List data, String name) =>
              deliverPdfReceipt(data, name);
      await deliver(_receipt, bytes, fileName);

      if (!mounted) return;

      HapticFeedback.mediumImpact();
      setState(() {
        _downloading = false;
        _downloaded = true;
      });
    } on AuthException catch (error) {
      if (!mounted) return;
      setState(() {
        _downloading = false;
        _error = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _downloading = false;
        _error = 'Could not download the receipt. Please try again.';
      });
    }
  }

  Future<void> _downloadImage() async {
    if (_downloadingImage || _downloading) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _downloadingImage = true;
      _error = null;
    });

    try {
      await WidgetsBinding.instance.endOfFrame;
      final renderObject = _receiptImageKey.currentContext?.findRenderObject();
      if (renderObject is! RenderRepaintBoundary) {
        throw StateError('Receipt image is not ready.');
      }
      final ui.Image image = await renderObject.toImage(pixelRatio: 2.5);
      final ByteData? data;
      try {
        data = await image.toByteData(format: ui.ImageByteFormat.png);
      } finally {
        image.dispose();
      }
      final Uint8List? bytes = data?.buffer.asUint8List();
      if (bytes == null || bytes.isEmpty) {
        throw StateError('The receipt image could not be created.');
      }

      await deliverImageReceipt(
        bytes,
        'withdrawal-${_receipt.reference}.png',
      );
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      setState(() {
        _downloadingImage = false;
        _downloadedImage = true;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _downloadingImage = false;
        _error = 'Could not create or save the receipt image. Please try again.';
      });
    }
  }

  bool _isPdf(Uint8List bytes) =>
      bytes.length >= 5 &&
      bytes[0] == 0x25 && // %
      bytes[1] == 0x50 && // P
      bytes[2] == 0x44 && // D
      bytes[3] == 0x46 && // F
      bytes[4] == 0x2D; // -

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;
    final MediaQueryData media = MediaQuery.of(context);

    const EdgeInsets insetPadding =
        EdgeInsets.symmetric(horizontal: 18, vertical: 24);
    final double availableHeight =
        media.size.height - media.viewInsets.bottom - insetPadding.vertical - 8;

    return Dialog(
      backgroundColor: Colors.transparent,
      elevation: 0,
      insetPadding: insetPadding,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 430,
          maxHeight: availableHeight > 320 ? availableHeight : 320,
        ),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: colors.surface,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(
              color: colors.outlineVariant.withValues(alpha: 0.7),
            ),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: colors.shadow.withValues(alpha: 0.24),
                blurRadius: 44,
                offset: const Offset(0, 22),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Flexible(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
                    child: RepaintBoundary(
                      key: _receiptImageKey,
                      child: ColoredBox(
                        color: colors.surface,
                        child: Padding(
                          padding: const EdgeInsets.all(1),
                          child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        _Header(logoAsset: widget.logoAsset, label: widget.appName),
                        const SizedBox(height: 6),
                        _RibbonWaveCheck(receipt: _receipt, colors: colors),
                        const SizedBox(height: 4),
                        Text(
                          widget.title ?? _defaultTitle(_receipt),
                          textAlign: TextAlign.center,
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.4,
                            color: colors.onSurface,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          widget.subtitle ?? _defaultSubtitle(_receipt),
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                            height: 1.45,
                          ),
                        ),
                        const SizedBox(height: 18),
                        _AmountCard(receipt: _receipt, theme: theme),
                        const SizedBox(height: 12),
                        _ReferenceChip(reference: _receipt.reference, theme: theme),
                        const SizedBox(height: 14),
                        ..._detailRows(theme, colors),
                        const SizedBox(height: 6),
                        _NoteBox(receipt: _receipt, theme: theme),
                        if (_error != null) ...<Widget>[
                          const SizedBox(height: 12),
                          _ErrorNote(message: _error!, theme: theme),
                        ],
                      ],
                    ),
                        ),
                      ),
                    ),
                  ),
                ),
                _Actions(
                  downloadingImage: _downloadingImage,
                  downloadedImage: _downloadedImage,
                  downloadingPdf: _downloading,
                  downloadedPdf: _downloaded,
                  canDownloadPdf: widget.loader != null,
                  showDownloadButton: widget.showDownloadButton,
                  onCancel: () => Navigator.of(context).pop(
                    _downloaded || _downloadedImage,
                  ),
                  onDownloadImage: _downloadImage,
                  onDownloadPdf: _downloadPdf,
                  theme: theme,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  List<Widget> _detailRows(ThemeData theme, ColorScheme colors) {
    final List<Widget> rows = <Widget>[];

    void add(String label, String value, {IconData? icon}) {
      rows.add(
        _DetailRow(
          label: label,
          value: value,
          icon: icon,
          theme: theme,
          colors: colors,
          last: false,
        ),
      );
    }

    final String? methodIcon = _receipt.methodIcon;
    add(
      'Method',
      methodIcon == null || methodIcon.isEmpty
          ? _receipt.methodLabel
          : '$methodIcon ${_receipt.methodLabel}',
      icon: Icons.account_balance_wallet_outlined,
    );

    if (_receipt.sourceLabel != null) {
      add('From', _receipt.sourceLabel!, icon: Icons.savings_outlined);
    }

    _receipt.payoutDetails.forEach((String key, String value) {
      add(_fieldLabel(key), value);
    });

    add('Fee', _money(_receipt.feeMinor, _receipt.currency));

    if (_receipt.requestedAt != null) {
      add(
        'Requested',
        _formatDateTime(_receipt.requestedAt!),
        icon: Icons.schedule_rounded,
      );
    }

    rows.add(
      _DetailRow(
        label: 'Status',
        value: _statusLabel(_receipt.status),
        theme: theme,
        colors: colors,
        last: _receipt.processedAt == null,
        trailing: _StatusPill(receipt: _receipt, colors: colors),
      ),
    );

    if (_receipt.processedAt != null) {
      rows.add(
        _DetailRow(
          label: 'Processed',
          value: _formatDateTime(_receipt.processedAt!),
          theme: theme,
          colors: colors,
          last: true,
        ),
      );
    }

    return rows;
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.logoAsset, this.label});

  final String logoAsset;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        Image.asset(
          logoAsset,
          height: 45,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.medium,
          errorBuilder: (BuildContext context, Object error, StackTrace? stack) {
            return Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[colors.primary, colors.secondary],
                ),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                Icons.receipt_long_rounded,
                size: 28,
                color: colors.onPrimary,
              ),
            );
          },
        ),
        if (label != null && label!.isNotEmpty) ...<Widget>[
          const SizedBox(width: 10),
          Flexible(
            child: Text(
              label!.toUpperCase(),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w900,
                letterSpacing: 1.4,
                color: colors.primary,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// The tick, wrapped in ribbons of wave that keep rolling outward.
class _RibbonWaveCheck extends StatefulWidget {
  const _RibbonWaveCheck({required this.receipt, required this.colors});

  final WithdrawalReceipt receipt;
  final ColorScheme colors;

  @override
  State<_RibbonWaveCheck> createState() => _RibbonWaveCheckState();
}

class _RibbonWaveCheckState extends State<_RibbonWaveCheck>
    with TickerProviderStateMixin {
  late final AnimationController _waves = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2800),
  );
  late final AnimationController _enter = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 620),
  );

  @override
  void initState() {
    super.initState();
    _waves.repeat();
    _enter.forward();
  }

  @override
  void dispose() {
    _waves.dispose();
    _enter.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bool still = MediaQuery.of(context).disableAnimations;
    final WithdrawalReceipt receipt = widget.receipt;
    final ColorScheme colors = widget.colors;

    final Color first = receipt.isFailed ? colors.error : colors.primary;
    final Color second = receipt.isFailed
        ? colors.error.withValues(alpha: 0.7)
        : receipt.isPending
            ? Color.lerp(colors.primary, colors.tertiary, 0.65) ?? colors.secondary
            : colors.secondary;

    final IconData glyph =
        receipt.isFailed ? Icons.close_rounded : Icons.check_rounded;

    return SizedBox(
      height: 156,
      child: Center(
        child: AnimatedBuilder(
          animation: Listenable.merge(<Listenable>[_waves, _enter]),
          builder: (BuildContext context, Widget? child) {
            final double enter = Curves.easeOutBack.transform(_enter.value);

            return SizedBox.square(
              dimension: 156,
              child: Stack(
                alignment: Alignment.center,
                children: <Widget>[
                  CustomPaint(
                    size: const Size.square(156),
                    painter: _RibbonWavePainter(
                      progress: still ? 0.32 : _waves.value,
                      first: first,
                      second: second,
                    ),
                  ),
                  Transform.scale(
                    scale: (0.7 + 0.3 * enter).toDouble(),
                    child: Opacity(
                      opacity: _enter.value,
                      child: Container(
                        width: 88,
                        height: 88,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: <Color>[first, second],
                          ),
                          boxShadow: <BoxShadow>[
                            BoxShadow(
                              color: first.withValues(alpha: 0.35),
                              blurRadius: 26,
                              offset: const Offset(0, 12),
                            ),
                          ],
                        ),
                        child: Icon(glyph, size: 44, color: colors.onPrimary),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _RibbonWavePainter extends CustomPainter {
  _RibbonWavePainter({
    required this.progress,
    required this.first,
    required this.second,
  });

  final double progress;
  final Color first;
  final Color second;

  @override
  void paint(Canvas canvas, Size size) {
    final Offset center = size.center(Offset.zero);
    final double base = size.width * 0.31;
    final double spread = size.width * 0.18;

    final Paint glow = Paint()
      ..shader = RadialGradient(
        colors: <Color>[first.withValues(alpha: 0.16), first.withValues(alpha: 0)],
      ).createShader(Rect.fromCircle(center: center, radius: size.width * 0.5));
    canvas.drawCircle(center, size.width * 0.5, glow);

    for (int ribbon = 0; ribbon < 3; ribbon++) {
      final double t = (progress + ribbon / 3) % 1;
      final double radius = base + t * spread;
      final double amplitude = 2.2 + 5.4 * (1 - t);
      final Color color = Color.lerp(first, second, ribbon / 3) ?? first;

      final Paint paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeCap = StrokeCap.round
        ..strokeWidth = (1 - t) * 2.6 + 0.7
        ..color = color.withValues(alpha: (1 - t) * 0.6);

      final Path path = Path();
      const int steps = 96;

      for (int step = 0; step <= steps; step++) {
        final double angle = (step / steps) * math.pi * 2;
        final double wave = amplitude *
            math.sin(6 * angle + t * math.pi * 2 + ribbon * 1.1);
        final Offset point = center +
            Offset(math.cos(angle) * (radius + wave), math.sin(angle) * (radius + wave));

        if (step == 0) {
          path.moveTo(point.dx, point.dy);
        } else {
          path.lineTo(point.dx, point.dy);
        }
      }

      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(_RibbonWavePainter oldDelegate) =>
      oldDelegate.progress != progress ||
      oldDelegate.first != first ||
      oldDelegate.second != second;
}

class _AmountCard extends StatelessWidget {
  const _AmountCard({required this.receipt, required this.theme});

  final WithdrawalReceipt receipt;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            colors.primary.withValues(alpha: 0.14),
            colors.secondary.withValues(alpha: 0.09),
          ],
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: colors.primary.withValues(alpha: 0.18)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(
            'YOU RECEIVE',
            style: theme.textTheme.labelSmall?.copyWith(
              color: colors.onSurfaceVariant,
              letterSpacing: 2.2,
              fontWeight: FontWeight.w800,
              fontSize: 9.5,
            ),
          ),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              _money(receipt.netMinor, receipt.currency),
              style: theme.textTheme.headlineMedium?.copyWith(
                color: colors.primary,
                fontWeight: FontWeight.w900,
                letterSpacing: -0.6,
              ),
            ),
          ),
          /*if (receipt.feeMinor > 0) ...<Widget>[
            const SizedBox(height: 8),
            Text(
              'Fee ${_money(receipt.feeMinor, receipt.currency)}  ·  '
              'You receive ${_money(receipt.netMinor, receipt.currency)}',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: colors.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],*/
        ],
      ),
    );
  }
}

class _ReferenceChip extends StatelessWidget {
  const _ReferenceChip({required this.reference, required this.theme});

  final String reference;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = theme.colorScheme;

    return Center(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
        decoration: BoxDecoration(
          color: colors.surfaceContainerHighest.withValues(alpha: 0.7),
          borderRadius: BorderRadius.circular(30),
          border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.8)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'REF:',
              style: theme.textTheme.labelSmall?.copyWith(
                color: colors.onSurfaceVariant,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                reference,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: colors.onSurface,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.4,
                  fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    required this.theme,
    required this.colors,
    required this.last,
    this.icon,
    this.trailing,
  });

  final String label;
  final String value;
  final ThemeData theme;
  final ColorScheme colors;
  final bool last;
  final IconData? icon;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 9),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              if (icon != null) ...<Widget>[
                Icon(icon, size: 15, color: colors.onSurfaceVariant),
                const SizedBox(width: 7),
              ],
              Text(
                label,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: trailing == null
                    ? Text(
                        value,
                        textAlign: TextAlign.right,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: colors.onSurface,
                          fontWeight: FontWeight.w800,
                        ),
                      )
                    : Align(alignment: Alignment.centerRight, child: trailing),
              ),
            ],
          ),
        ),
        if (!last)
          Divider(
            height: 1,
            thickness: 1,
            color: colors.outlineVariant.withValues(alpha: 0.45),
          ),
      ],
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.receipt, required this.colors});

  final WithdrawalReceipt receipt;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    final Color tone = receipt.isFailed
        ? colors.error
        : receipt.isPending
            ? colors.tertiary
            : colors.primary;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: tone.withValues(alpha: 0.28)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: tone, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            _statusLabel(receipt.status),
            style: TextStyle(
              color: tone,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}

class _NoteBox extends StatelessWidget {
  const _NoteBox({required this.receipt, required this.theme});

  final WithdrawalReceipt receipt;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = theme.colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(
            Icons.info_outline_rounded,
            size: 16,
            color: colors.onSurfaceVariant,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              _noteFor(receipt),
              style: theme.textTheme.bodySmall?.copyWith(
                color: colors.onSurfaceVariant,
                height: 1.45,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorNote extends StatelessWidget {
  const _ErrorNote({required this.message, required this.theme});

  final String message;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = theme.colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      decoration: BoxDecoration(
        color: colors.errorContainer.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.error.withValues(alpha: 0.3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(Icons.error_outline_rounded, size: 16, color: colors.error),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
                color: colors.onErrorContainer,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Actions extends StatelessWidget {
  const _Actions({
    required this.downloadingImage,
    required this.downloadedImage,
    required this.downloadingPdf,
    required this.downloadedPdf,
    required this.canDownloadPdf,
    required this.showDownloadButton,
    required this.onCancel,
    required this.onDownloadImage,
    required this.onDownloadPdf,
    required this.theme,
  });

  final bool downloadingImage;
  final bool downloadedImage;
  final bool downloadingPdf;
  final bool downloadedPdf;
  final bool canDownloadPdf;
  final bool showDownloadButton;
  final VoidCallback onCancel;
  final VoidCallback onDownloadImage;
  final VoidCallback onDownloadPdf;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = theme.colorScheme;
    final bool busy = downloadingImage || downloadingPdf;

    return Container(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 18),
      decoration: BoxDecoration(
        border: Border(
          top: BorderSide(color: colors.outlineVariant.withValues(alpha: 0.5)),
        ),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: SizedBox(
              height: 48,
              child: OutlinedButton(
                onPressed: busy ? null : onCancel,
                style: OutlinedButton.styleFrom(
                  foregroundColor: colors.onSurface,
                  side: BorderSide(color: colors.outlineVariant),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(15),
                  ),
                  padding: EdgeInsets.zero,
                ),
                child: const Text(
                  'Close',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ),
          if (showDownloadButton) ...<Widget>[
            const SizedBox(width: 8),
            Expanded(
              child: SizedBox(
                height: 48,
                child: OutlinedButton.icon(
                  onPressed: busy ? null : onDownloadImage,
                  icon: downloadingImage
                      ? SizedBox.square(
                          dimension: 15,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: colors.primary,
                          ),
                        )
                      : Icon(
                          downloadedImage
                              ? Icons.check_circle_rounded
                              : Icons.image_outlined,
                          size: 17,
                        ),
                  label: Text(
                    downloadingImage
                        ? 'Saving…'
                        : downloadedImage
                            ? 'Saved'
                            : 'Image',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: colors.primary,
                    side: BorderSide(color: colors.primary.withValues(alpha: .5)),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(15),
                    ),
                    padding: const EdgeInsets.symmetric(horizontal: 7),
                    textStyle: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ),
            if (canDownloadPdf) ...<Widget>[
              const SizedBox(width: 8),
              Expanded(
                child: SizedBox(
                  height: 48,
                  child: FilledButton.icon(
                    onPressed: busy ? null : onDownloadPdf,
                    icon: downloadingPdf
                        ? SizedBox.square(
                            dimension: 15,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: colors.onPrimary,
                            ),
                          )
                        : Icon(
                            downloadedPdf
                                ? Icons.check_circle_rounded
                                : Icons.picture_as_pdf_rounded,
                            size: 17,
                          ),
                    label: Text(
                      downloadingPdf
                          ? 'Saving…'
                          : downloadedPdf
                              ? 'Saved'
                              : 'PDF',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    style: FilledButton.styleFrom(
                      backgroundColor: colors.primary,
                      foregroundColor: colors.onPrimary,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(15),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 7),
                      textStyle: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

String _defaultTitle(WithdrawalReceipt receipt) {
  if (receipt.isFailed) return 'Withdrawal Failed';
  if (receipt.status == 'completed' || receipt.status == 'paid') {
    return 'Withdrawal Completed';
  }
  return 'Withdrawal Initiated';
}

String _defaultSubtitle(WithdrawalReceipt receipt) {
  if (receipt.isFailed) {
    return receipt.failureReason ?? 'This request could not be completed.';
  }
  if (receipt.status == 'completed' || receipt.status == 'paid') {
    return 'The money is on its way to you.';
  }
  return 'Request submitted successfully';
}

String _noteFor(WithdrawalReceipt receipt) {
  if (receipt.isFailed) {
    return 'Nothing was deducted for a failed request. You can start a new '
        'withdrawal whenever you are ready.';
  }
  if (receipt.status == 'completed' || receipt.status == 'paid') {
    final DateTime? when = receipt.processedAt;
    return when == null
        ? 'This withdrawal has been paid out.'
        : 'Paid out on ${_formatDateTime(when)}.';
  }
  return 'Processing time: 1–3 business days\n'
      "You'll be notified once completed";
}

String _statusLabel(String status) {
  switch (status) {
    case 'pending':
      return 'Pending';
    case 'processing':
      return 'Processing';
    case 'completed':
    case 'paid':
      return 'Completed';
    case 'failed':
      return 'Failed';
    case 'rejected':
      return 'Rejected';
    case 'cancelled':
      return 'Cancelled';
    default:
      final String cleaned = status.replaceAll('_', ' ').trim();
      return cleaned.isEmpty
          ? 'Unknown'
          : cleaned[0].toUpperCase() + cleaned.substring(1);
  }
}

String _fieldLabel(String key) {
  const Map<String, String> known = <String, String>{
    'phone_number': 'Phone number',
    'provider': 'Network',
    'account_number': 'Account number',
    'account_name': 'Account name',
    'bank_name': 'Bank',
    'bank_code': 'Bank code',
    'sort_code': 'Sort code',
    'iban': 'IBAN',
    'swift': 'SWIFT',
    'wallet_address': 'Wallet address',
    'crypto_address': 'Wallet address',
    'network': 'Network',
    'email': 'Email',
    'address': 'Address',
    'country': 'Country',
    'name': 'Name',
  };

  final String lowered = key.toLowerCase();
  final String? mapped = known[lowered];
  if (mapped != null) return mapped;

  final String spaced = key
      .replaceAll('_', ' ')
      .replaceAllMapped(
        RegExp('[A-Z]'),
        (Match match) => ' ${match.group(0)!.toLowerCase()}',
      )
      .trim();

  if (spaced.isEmpty) return key;

  return spaced[0].toUpperCase() + spaced.substring(1);
}

String _money(int minor, String currency) {
  final String code = currency.isEmpty ? 'USD' : currency.toUpperCase();
  final int digits = currencyPrecision(code);
  final double major = minor / math.pow(10, digits);
  final String label = formatMajorMoney(major, code);
  final int dot = label.lastIndexOf('.');

  final String head = dot < 0 ? label : label.substring(0, dot);
  final String tail = dot < 0 ? '' : label.substring(dot);
  final String grouped = head.replaceAllMapped(
    RegExp(r'(\d)(?=(\d{3})+$)'),
    (Match match) => '${match.group(1)},',
  );

  return '$grouped$tail';
}

String _formatDateTime(DateTime value) {
  const List<String> months = <String>[
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];

  final int hour24 = value.hour;
  final int hour = hour24 % 12 == 0 ? 12 : hour24 % 12;
  final String minute = value.minute.toString().padLeft(2, '0');
  final String period = hour24 >= 12 ? 'PM' : 'AM';

  return '${value.day} ${months[value.month - 1]} ${value.year} · '
      '$hour:$minute $period';
}
