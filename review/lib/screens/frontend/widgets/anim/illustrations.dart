import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'anim_kit.dart';

const _amber = Color(0xFFFFC857);
const _mint = Color(0xFF3DDC97);

// ===========================================================================
// HERO: a phone being "reviewed" while reward chips orbit around it
// ===========================================================================

class HeroArt extends StatelessWidget {
  const HeroArt({super.key, this.height = 260});

  final double height;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: height,
    child: FittedBox(
      fit: BoxFit.contain,
      child: SizedBox(
        width: 300,
        height: 260,
        child: Loop(
          duration: const Duration(seconds: 10),
          builder: (context, t, _) => _frame(context, t),
        ),
      ),
    ),
  );

  Widget _frame(BuildContext context, double t) {
    const tau = 2 * math.pi;
    final colors = Theme.of(context).colorScheme;
    final orbiters = <(IconData, Color)>[
      (Icons.star_rounded, _amber),
      (Icons.savings_rounded, _mint),
      (Icons.campaign_rounded, const Color(0xFFFF7AB6)),
      (Icons.account_balance_wallet_rounded, const Color(0xFF7CC4FF)),
    ];

    final back = <Widget>[];
    final front = <Widget>[];
    for (var i = 0; i < orbiters.length; i++) {
      final a = tau * (t + i / orbiters.length);
      final depth = math.sin(a);
      final x = 150 + math.cos(a) * 118;
      final y = 132 + depth * 44;
      final scale = .84 + .16 * ((depth + 1) / 2);
      final chip = Positioned(
        left: x - 22,
        top: y - 22,
        child: Transform.scale(
          scale: scale,
          child: _chip(orbiters[i].$1, orbiters[i].$2),
        ),
      );
      (depth < 0 ? back : front).add(chip);
    }

    const labels = [r'+$0.45', '+₦250', '+₵5'];
    const spots = [Offset(214, 150), Offset(6, 118), Offset(200, 40)];
    final popups = <Widget>[];
    for (var j = 0; j < labels.length; j++) {
      final p = (t * 2 + j / labels.length) % 1;
      popups.add(
        Positioned(
          left: spots[j].dx,
          top: spots[j].dy - 46 * p,
          child: Opacity(
            opacity: clamp01(math.sin(math.pi * p)),
            child: _popup(labels[j]),
          ),
        ),
      );
    }

    return Stack(
      clipBehavior: Clip.none,
      children: [
        Positioned(
          left: 45,
          top: 28,
          child: Container(
            width: 210,
            height: 210,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(
                colors: [
                  Colors.white.withValues(alpha: .28),
                  Colors.white.withValues(alpha: 0),
                ],
              ),
            ),
          ),
        ),
        const Positioned.fill(child: Sparkles(count: 14, seed: 11)),
        ...back,
        Positioned(
          left: 86,
          top: 22 + math.sin(tau * 2 * t) * 5,
          child: _phone(colors, t),
        ),
        ...front,
        ...popups,
      ],
    );
  }

  Widget _chip(IconData icon, Color color) => Container(
    width: 44,
    height: 44,
    decoration: BoxDecoration(
      color: Colors.white,
      shape: BoxShape.circle,
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: .25),
          blurRadius: 12,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: Icon(icon, color: color, size: 24),
  );

  Widget _popup(String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: _mint,
      borderRadius: BorderRadius.circular(999),
      boxShadow: [
        BoxShadow(
          color: _mint.withValues(alpha: .5),
          blurRadius: 12,
        ),
      ],
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: Color(0xFF0B2A1E),
        fontWeight: FontWeight.w900,
        fontSize: 12,
      ),
    ),
  );

  Widget _bar(double width, double height, double alpha) => Container(
    width: width,
    height: height,
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: alpha),
      borderRadius: BorderRadius.circular(4),
    ),
  );

  Widget _phone(ColorScheme colors, double t) {
    final phase = (t * 2) % 1;
    final progress = phase / .6 * 5;
    final lit = math.min(5, math.max(0, progress.floor()));
    final frac = progress - progress.floor();
    final approved = clamp01((phase - .68) / .12);

    return Container(
      width: 128,
      height: 216,
      padding: const EdgeInsets.all(7),
      decoration: BoxDecoration(
        color: const Color(0xFF16132B),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: Colors.white.withValues(alpha: .4), width: 2.5),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .3),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(21),
        child: Container(
          color: const Color(0xFF221E3F),
          padding: const EdgeInsets.all(8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 20,
                    height: 20,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: colors.primary,
                    ),
                    child: const Icon(
                      Icons.person_rounded,
                      size: 13,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(width: 6),
                  _bar(44, 6, .55),
                ],
              ),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 26,
                          height: 26,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(8),
                            gradient: LinearGradient(
                              colors: [colors.primary, colors.secondary],
                            ),
                          ),
                          child: const Icon(
                            Icons.apps_rounded,
                            size: 15,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _bar(40, 6, .65),
                            const SizedBox(height: 4),
                            _bar(26, 5, .3),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        for (var i = 0; i < 5; i++)
                          Transform.scale(
                            scale: i == lit - 1 && lit < 5
                                ? 1 + .35 * (1 - frac)
                                : 1,
                            child: Icon(
                              Icons.star_rounded,
                              size: 14,
                              color: i < lit
                                  ? _amber
                                  : Colors.white.withValues(alpha: .22),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .05),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 20,
                      height: 20,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: .15),
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                    const SizedBox(width: 6),
                    _bar(36, 6, .3),
                  ],
                ),
              ),
              const Spacer(),
              Opacity(
                opacity: approved,
                child: Transform.translate(
                  offset: Offset(0, 6 * (1 - approved)),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: _mint.withValues(alpha: .22),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.check_circle_rounded, size: 12, color: _mint),
                        SizedBox(width: 4),
                        Text(
                          'Approved',
                          style: TextStyle(
                            color: _mint,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ===========================================================================
// HOW IT WORKS: one small looping scene per step
// ===========================================================================

class StepArt extends StatelessWidget {
  const StepArt({
    super.key,
    required this.index,
    required this.color,
    this.size = 64,
  });

  final int index;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: size,
    height: size,
    child: Loop(
      duration: const Duration(seconds: 3),
      builder: (context, t, _) => CustomPaint(
        painter: _StepPainter(index % 4, color, t),
      ),
    ),
  );
}

class _StepPainter extends CustomPainter {
  _StepPainter(this.kind, this.color, this.t);

  final int kind;
  final Color color;
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    final s = size.width;
    final c = Offset(s / 2, s / 2);
    canvas.drawCircle(c, s * .46, Paint()..color = color.withValues(alpha: .16));
    switch (kind) {
      case 0:
        _register(canvas, s);
      case 1:
        _package(canvas, s, c);
      case 2:
        _task(canvas, s);
      default:
        _wallet(canvas, s);
    }
  }

  void _register(Canvas canvas, double s) {
    final fill = Paint()..color = color;
    canvas.drawCircle(
      Offset(s * .46, s * .5),
      s * (.26 + .18 * t),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2
        ..color = color.withValues(alpha: .5 * (1 - t)),
    );
    canvas.drawCircle(Offset(s * .46, s * .38), s * .12, fill);
    canvas.drawArc(
      Rect.fromCenter(center: Offset(s * .46, s * .76), width: s * .44, height: s * .46),
      math.pi,
      math.pi,
      true,
      fill,
    );
    final pop = 1 + .12 * math.sin(2 * math.pi * t);
    final badge = Offset(s * .76, s * .28);
    canvas.drawCircle(badge, s * .13 * pop, Paint()..color = _mint);
    final plus = Paint()
      ..color = Colors.white
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(badge.translate(-s * .06, 0), badge.translate(s * .06, 0), plus);
    canvas.drawLine(badge.translate(0, -s * .06), badge.translate(0, s * .06), plus);
  }

  void _package(Canvas canvas, double s, Offset c) {
    canvas.save();
    canvas.translate(c.dx, c.dy);
    canvas.rotate(t * math.pi / 2);
    canvas.drawPath(
      starPath(Offset.zero, s * .34, points: 12, innerRatio: .8, rotation: 0),
      Paint()..color = color,
    );
    canvas.restore();
    final pulse = 1 + .1 * math.sin(2 * math.pi * t);
    canvas.drawCircle(c, s * .2, Paint()..color = Colors.white.withValues(alpha: .92));
    canvas.drawPath(
      starPath(c, s * .13 * pulse, points: 5, innerRatio: .45),
      Paint()..color = _amber,
    );
  }

  void _task(Canvas canvas, double s) {
    final fade = t > .9 ? (1 - t) / .1 : (t < .06 ? t / .06 : 1.0);
    final card = RRect.fromRectAndRadius(
      Rect.fromLTWH(s * .2, s * .14, s * .6, s * .62),
      Radius.circular(s * .1),
    );
    canvas.drawRRect(card, Paint()..color = color.withValues(alpha: .2));
    canvas.drawRRect(
      card,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2
        ..color = color.withValues(alpha: .75),
    );
    final line = Paint()
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    for (var i = 0; i < 3; i++) {
      final q = clamp01((t * 1.6 - i * .25) / .3);
      line.color = color.withValues(alpha: .6 * fade);
      final y = s * (.3 + i * .14);
      canvas.drawLine(Offset(s * .3, y), Offset(s * (.3 + .4 * q), y), line);
    }
    final badge = Offset(s * .72, s * .72);
    canvas.drawCircle(badge, s * .17, Paint()..color = _mint.withValues(alpha: fade));
    final check = Path()
      ..moveTo(s * .64, s * .72)
      ..lineTo(s * .70, s * .78)
      ..lineTo(s * .80, s * .66);
    final p = clamp01((t - .5) / .2);
    for (final metric in check.computeMetrics()) {
      canvas.drawPath(
        metric.extractPath(0, metric.length * p),
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2.6
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round
          ..color = Colors.white.withValues(alpha: fade),
      );
    }
  }

  void _wallet(Canvas canvas, double s) {
    for (var k = 0; k < 3; k++) {
      final p = (t + k / 3) % 1;
      final y = s * .02 + (s * .40 - s * .02) * Curves.easeIn.transform(p);
      final alpha = p < .85 ? 1.0 : (1 - p) / .15;
      final coin = Offset(s * (.34 + .16 * k), y);
      canvas.drawCircle(coin, s * .075, Paint()..color = _amber.withValues(alpha: alpha));
      canvas.drawCircle(
        coin,
        s * .045,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.4
          ..color = Colors.white.withValues(alpha: .7 * alpha),
      );
    }
    final body = RRect.fromRectAndRadius(
      Rect.fromLTWH(s * .16, s * .40, s * .68, s * .42),
      Radius.circular(s * .1),
    );
    canvas.drawRRect(body, Paint()..color = color);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(s * .16, s * .40, s * .68, s * .1),
        Radius.circular(s * .06),
      ),
      Paint()..color = Colors.white.withValues(alpha: .22),
    );
    canvas.drawCircle(Offset(s * .72, s * .63), s * .05, Paint()..color = Colors.white);
  }

  @override
  bool shouldRepaint(_StepPainter old) => old.t != t || old.color != color;
}

// ===========================================================================
// VALUE ADDED SERVICES: airtime, data, tv, electricity, education
// ===========================================================================

class ServiceArt extends StatelessWidget {
  const ServiceArt({
    super.key,
    required this.kind,
    required this.color,
    this.size = 72,
  });

  /// One of: airtime, data, tv, electricity, education (anything else falls
  /// back to a pulsing ring).
  final String kind;
  final Color color;
  final double size;

  static String kindFor(Map<String, dynamic> service) {
    final icon = service['icon']?.toString() ?? '';
    final key = service['key']?.toString().toLowerCase() ?? '';
    return switch (icon) {
      'phone_android' => 'airtime',
      'wifi' => 'data',
      'tv' => 'tv',
      'bolt' => 'electricity',
      'school' => 'education',
      _ => key,
    };
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    width: size,
    height: size,
    child: Loop(
      duration: const Duration(seconds: 3),
      builder: (context, t, _) => CustomPaint(
        painter: _ServicePainter(kind, color, t),
      ),
    ),
  );
}

class _ServicePainter extends CustomPainter {
  _ServicePainter(this.kind, this.color, this.t);

  final String kind;
  final Color color;
  final double t;

  static const _tau = 2 * math.pi;

  Paint get _stroke => Paint()
    ..style = PaintingStyle.stroke
    ..strokeCap = StrokeCap.round
    ..strokeJoin = StrokeJoin.round;

  @override
  void paint(Canvas canvas, Size size) {
    final s = size.width;
    switch (kind) {
      case 'airtime':
        _airtime(canvas, s);
      case 'data':
        _data(canvas, s);
      case 'tv':
        _tv(canvas, s);
      case 'electricity':
        _electricity(canvas, s);
      case 'education':
        _education(canvas, s);
      default:
        canvas.drawCircle(
          Offset(s / 2, s / 2),
          s * (.2 + .25 * t),
          _stroke
            ..strokeWidth = 2.5
            ..color = color.withValues(alpha: 1 - t),
        );
        canvas.drawCircle(Offset(s / 2, s / 2), s * .14, Paint()..color = color);
    }
  }

  void _airtime(Canvas canvas, double s) {
    final centre = Offset(s * .39, s * .48);
    canvas.drawCircle(
      centre,
      s * (.1 + .38 * t),
      _stroke
        ..strokeWidth = 2
        ..color = color.withValues(alpha: .5 * (1 - t)),
    );
    final phone = RRect.fromRectAndRadius(
      Rect.fromLTWH(s * .22, s * .12, s * .34, s * .72),
      Radius.circular(s * .08),
    );
    canvas.drawRRect(phone, Paint()..color = color.withValues(alpha: .16));
    canvas.drawRRect(
      phone,
      _stroke
        ..strokeWidth = 2.6
        ..color = color,
    );
    canvas.drawCircle(Offset(s * .39, s * .76), s * .025, Paint()..color = color);
    for (var i = 0; i < 4; i++) {
      final level = .6 + .4 * math.sin(_tau * (t - i * .15));
      final h = s * (.12 + .11 * i) * level;
      final x = s * (.64 + i * .085);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, s * .78 - h, s * .05, h),
          Radius.circular(s * .02),
        ),
        Paint()..color = color.withValues(alpha: .45 + .55 * level.clamp(0.0, 1.0)),
      );
    }
  }

  void _data(Canvas canvas, double s) {
    final origin = Offset(s * .5, s * .74);
    canvas.drawCircle(origin, s * .055, Paint()..color = color);
    for (var i = 1; i <= 3; i++) {
      final wave = (math.sin(_tau * (t - (i - 1) * .18)) + 1) / 2;
      canvas.drawArc(
        Rect.fromCircle(center: origin, radius: s * .17 * i),
        -3 * math.pi / 4,
        math.pi / 2,
        false,
        _stroke
          ..strokeWidth = 3.6
          ..color = color.withValues(alpha: .22 + .78 * wave),
      );
    }
  }

  void _tv(Canvas canvas, double s) {
    final wiggle = math.sin(_tau * t) * s * .03;
    final antenna = _stroke
      ..strokeWidth = 2.2
      ..color = color;
    canvas.drawLine(Offset(s * .5, s * .28), Offset(s * .32 + wiggle, s * .08), antenna);
    canvas.drawLine(Offset(s * .5, s * .28), Offset(s * .68 - wiggle, s * .08), antenna);

    final screen = RRect.fromRectAndRadius(
      Rect.fromLTWH(s * .12, s * .28, s * .76, s * .5),
      Radius.circular(s * .09),
    );
    canvas.save();
    canvas.clipRRect(screen);
    canvas.drawRect(screen.outerRect, Paint()..color = color.withValues(alpha: .12));
    const bars = 6;
    final w = screen.width / bars;
    for (var i = 0; i < bars; i++) {
      final wave = (math.sin(_tau * (t - i / bars)) + 1) / 2;
      canvas.drawRect(
        Rect.fromLTWH(screen.left + w * i, screen.top, w, screen.height),
        Paint()..color = color.withValues(alpha: .1 + .32 * wave),
      );
    }
    canvas.restore();
    canvas.drawRRect(
      screen,
      _stroke
        ..strokeWidth = 2.6
        ..color = color,
    );
    final pulse = 1 + .12 * math.sin(_tau * t * 2);
    final cx = s * .5;
    final cy = s * .53;
    final r = s * .09 * pulse;
    canvas.drawPath(
      Path()
        ..moveTo(cx - r * .7, cy - r)
        ..lineTo(cx + r * 1.1, cy)
        ..lineTo(cx - r * .7, cy + r)
        ..close(),
      Paint()..color = Colors.white.withValues(alpha: .92),
    );
    canvas.drawLine(
      Offset(s * .34, s * .88),
      Offset(s * .66, s * .88),
      _stroke
        ..strokeWidth = 2.6
        ..color = color,
    );
  }

  Path _bolt(double s) {
    const pts = [
      Offset(.60, .04),
      Offset(.26, .56),
      Offset(.47, .56),
      Offset(.38, .96),
      Offset(.76, .40),
      Offset(.54, .40),
      Offset(.68, .04),
    ];
    final path = Path()..moveTo(pts.first.dx * s, pts.first.dy * s);
    for (final p in pts.skip(1)) {
      path.lineTo(p.dx * s, p.dy * s);
    }
    return path..close();
  }

  void _electricity(Canvas canvas, double s) {
    final glow = (math.sin(_tau * t) + 1) / 2;
    canvas.drawPath(
      _bolt(s),
      Paint()
        ..color = color.withValues(alpha: .35 + .4 * glow)
        ..maskFilter = MaskFilter.blur(BlurStyle.normal, 5 + 5 * glow),
    );
    canvas.drawPath(_bolt(s), Paint()..color = color);
    canvas.drawPath(
      _bolt(s),
      _stroke
        ..strokeWidth = 1.4
        ..color = Colors.white.withValues(alpha: .6),
    );
    final spark = _stroke
      ..strokeWidth = 2.2
      ..color = _amber;
    const spots = [Offset(.14, .24), Offset(.86, .62), Offset(.18, .8), Offset(.84, .14)];
    for (var k = 0; k < spots.length; k++) {
      if (math.sin(_tau * (t * 3 + k * .27)) > .25) {
        final p = Offset(spots[k].dx * s, spots[k].dy * s);
        canvas.drawLine(p.translate(-s * .04, 0), p.translate(s * .04, 0), spark);
        canvas.drawLine(p.translate(0, -s * .04), p.translate(0, s * .04), spark);
      }
    }
  }

  void _education(Canvas canvas, double s) {
    final bob = math.sin(_tau * t) * s * .03;
    final fill = Paint()..color = color;
    canvas.drawPath(
      Path()
        ..moveTo(s * .27, s * .46 + bob)
        ..lineTo(s * .27, s * .62 + bob)
        ..quadraticBezierTo(s * .5, s * .78 + bob, s * .73, s * .62 + bob)
        ..lineTo(s * .73, s * .46 + bob)
        ..lineTo(s * .5, s * .56 + bob)
        ..close(),
      Paint()..color = color.withValues(alpha: .7),
    );
    canvas.drawPath(
      Path()
        ..moveTo(s * .5, s * .2 + bob)
        ..lineTo(s * .92, s * .38 + bob)
        ..lineTo(s * .5, s * .56 + bob)
        ..lineTo(s * .08, s * .38 + bob)
        ..close(),
      fill,
    );
    final swing = math.sin(_tau * t * 2) * s * .025;
    final end = Offset(s * .86 + swing, s * .62 + bob);
    canvas.drawLine(
      Offset(s * .86, s * .40 + bob),
      end,
      _stroke
        ..strokeWidth = 2
        ..color = _amber,
    );
    canvas.drawCircle(end, s * .035, Paint()..color = _amber);
    for (var k = 0; k < 3; k++) {
      final a = _tau * (t + k / 3);
      final p = Offset(s * .5 + math.cos(a) * s * .42, s * .5 + math.sin(a) * s * .4);
      canvas.drawPath(starPath(p, s * .06), Paint()..color = _amber);
    }
  }

  @override
  bool shouldRepaint(_ServicePainter old) =>
      old.t != t || old.kind != kind || old.color != color;
}

// ===========================================================================
// FAQ: floating "?" and "!" bubbles
// ===========================================================================

class FaqArt extends StatelessWidget {
  const FaqArt({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    Widget bubble(String text, double size, Color color) => Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .2),
        shape: BoxShape.circle,
        border: Border.all(color: color.withValues(alpha: .5)),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w900,
          fontSize: size * .5,
        ),
      ),
    );

    return SizedBox(
      width: 74,
      height: 58,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            top: 12,
            child: Floating(
              amplitude: 5,
              tilt: .1,
              child: bubble('?', 38, colors.primary),
            ),
          ),
          Positioned(
            left: 38,
            top: 0,
            child: Floating(
              amplitude: 4,
              phase: .5,
              duration: const Duration(seconds: 3),
              child: bubble('!', 28, colors.tertiary),
            ),
          ),
        ],
      ),
    );
  }
}

// ===========================================================================
// Confetti that falls behind the leaderboard podium
// ===========================================================================

class ConfettiRain extends StatelessWidget {
  const ConfettiRain({super.key, required this.colors, this.count = 18});

  final List<Color> colors;
  final int count;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: Loop(
      duration: const Duration(seconds: 6),
      builder: (context, t, _) => CustomPaint(
        size: Size.infinite,
        painter: _ConfettiPainter(t, colors, count),
      ),
    ),
  );
}

class _ConfettiPainter extends CustomPainter {
  _ConfettiPainter(this.t, this.colors, this.count);

  final double t;
  final List<Color> colors;
  final int count;

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty || colors.isEmpty) return;
    final random = math.Random(21);
    for (var i = 0; i < count; i++) {
      final x = random.nextDouble() * size.width;
      final offset = random.nextDouble();
      final spin = (random.nextBool() ? 1 : -1) * (2 + random.nextInt(3));
      final fall = (t + offset) % 1;
      final y = fall * (size.height + 20) - 10;
      final alpha = (1 - fall * fall * fall) * .85;
      canvas.save();
      canvas.translate(x + math.sin(fall * math.pi * 2) * 6, y);
      canvas.rotate(fall * math.pi * spin);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          const Rect.fromLTWH(-2.5, -4, 5, 8),
          const Radius.circular(1.5),
        ),
        Paint()..color = colors[i % colors.length].withValues(alpha: alpha),
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_ConfettiPainter old) => old.t != t;
}
