import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Small, dependency-free animation toolkit used by every landing section.
///
/// Everything here respects the OS "reduce motion" setting
/// (`MediaQuery.disableAnimations`): loops freeze on a pleasant mid-frame and
/// reveals appear instantly.

bool reduceMotion(BuildContext context) =>
    MediaQuery.maybeOf(context)?.disableAnimations ?? false;

double clamp01(double v) => v < 0 ? 0 : (v > 1 ? 1 : v);

/// Builds a [Path] for a pointed star (4 points by default).
Path starPath(
  Offset c,
  double r, {
  int points = 4,
  double innerRatio = .28,
  double rotation = -math.pi / 2,
}) {
  final path = Path();
  final steps = points * 2;
  for (var i = 0; i < steps; i++) {
    final angle = rotation + i * math.pi / points;
    final radius = i.isEven ? r : r * innerRatio;
    final p = Offset(
      c.dx + math.cos(angle) * radius,
      c.dy + math.sin(angle) * radius,
    );
    if (i == 0) {
      path.moveTo(p.dx, p.dy);
    } else {
      path.lineTo(p.dx, p.dy);
    }
  }
  return path..close();
}

// ---------------------------------------------------------------------------
// Looping animation
// ---------------------------------------------------------------------------

/// Repeats forever and hands the builder a value `t` in `0..1`.
///
/// Use whole-number multiples of `t` inside the builder
/// (`sin(2π · t · n)`) so the loop is seamless.
class Loop extends StatefulWidget {
  const Loop({
    super.key,
    required this.builder,
    this.duration = const Duration(seconds: 4),
    this.child,
  });

  final Widget Function(BuildContext context, double t, Widget? child) builder;
  final Duration duration;
  final Widget? child;

  @override
  State<Loop> createState() => _LoopState();
}

class _LoopState extends State<Loop> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: widget.duration,
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (reduceMotion(context)) {
      _controller
        ..stop()
        ..value = .5;
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => RepaintBoundary(
    child: AnimatedBuilder(
      animation: _controller,
      child: widget.child,
      builder: (context, child) =>
          widget.builder(context, _controller.value, child),
    ),
  );
}

/// Gentle bobbing (and optional tilt) for any child.
class Floating extends StatelessWidget {
  const Floating({
    super.key,
    required this.child,
    this.amplitude = 8,
    this.duration = const Duration(seconds: 4),
    this.phase = 0,
    this.tilt = 0,
  });

  final Widget child;
  final double amplitude;
  final Duration duration;
  final double phase;
  final double tilt;

  @override
  Widget build(BuildContext context) => Loop(
    duration: duration,
    child: child,
    builder: (context, t, child) {
      final a = 2 * math.pi * (t + phase);
      return Transform.translate(
        offset: Offset(0, math.sin(a) * amplitude),
        child: Transform.rotate(angle: math.sin(a + 1) * tilt, child: child),
      );
    },
  );
}

/// Soft breathing glow around a child (used for the main call-to-action).
class PulseGlow extends StatelessWidget {
  const PulseGlow({
    super.key,
    required this.child,
    required this.color,
    this.borderRadius = 25,
  });

  final Widget child;
  final Color color;
  final double borderRadius;

  @override
  Widget build(BuildContext context) => Loop(
    duration: const Duration(milliseconds: 2200),
    child: child,
    builder: (context, t, child) {
      final w = (math.sin(2 * math.pi * t) + 1) / 2;
      return DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(borderRadius),
          boxShadow: [
            BoxShadow(
              color: color.withValues(alpha: .20 + .25 * w),
              blurRadius: 10 + 16 * w,
              spreadRadius: 1 + 2 * w,
            ),
          ],
        ),
        child: child,
      );
    },
  );
}

/// A star that twinkles; give each star in a row a different [index].
class TwinkleStar extends StatelessWidget {
  const TwinkleStar({
    super.key,
    required this.index,
    required this.color,
    this.size = 18,
  });

  final int index;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => Loop(
    duration: const Duration(milliseconds: 2400),
    builder: (context, t, _) {
      final wave = (math.sin(2 * math.pi * (t - index * .12)) + 1) / 2;
      return Transform.scale(
        scale: .86 + .3 * wave,
        child: Icon(
          Icons.star_rounded,
          size: size,
          color: color.withValues(alpha: .7 + .3 * wave),
        ),
      );
    },
  );
}

// ---------------------------------------------------------------------------
// Scroll-triggered reveal
// ---------------------------------------------------------------------------

/// Tells its builder when the widget has first scrolled into view.
class InView extends StatefulWidget {
  const InView({
    super.key,
    required this.builder,
    this.delay = Duration.zero,
    this.threshold = .92,
  });

  final Widget Function(BuildContext context, bool visible) builder;
  final Duration delay;

  /// Fraction of the viewport height the widget's top edge must pass.
  final double threshold;

  @override
  State<InView> createState() => _InViewState();
}

class _InViewState extends State<InView> {
  ScrollPosition? _position;
  bool _visible = false;
  bool _armed = false;
  Timer? _timer;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _position?.removeListener(_check);
    _position = Scrollable.maybeOf(context)?.position;
    _position?.addListener(_check);
    WidgetsBinding.instance.addPostFrameCallback((_) => _check());
  }

  void _check() {
    if (_armed || !mounted) return;
    final box = context.findRenderObject();
    if (box is! RenderBox || !box.attached || !box.hasSize) return;
    final top = box.localToGlobal(Offset.zero).dy;
    final height = MediaQuery.sizeOf(context).height;
    if (top > height * widget.threshold) return;

    _armed = true;
    _position?.removeListener(_check);
    if (reduceMotion(context) || widget.delay == Duration.zero) {
      setState(() => _visible = true);
      return;
    }
    _timer = Timer(widget.delay, () {
      if (mounted) setState(() => _visible = true);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _position?.removeListener(_check);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.builder(context, _visible);
}

/// Fade + slide + settle-in when the child scrolls into view.
class Reveal extends StatelessWidget {
  const Reveal({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.offset = const Offset(0, 32),
    this.duration = const Duration(milliseconds: 700),
    this.scale = .96,
    this.curve = Curves.easeOutCubic,
  });

  final Widget child;
  final Duration delay;
  final Offset offset;
  final Duration duration;
  final double scale;
  final Curve curve;

  @override
  Widget build(BuildContext context) {
    final still = reduceMotion(context);
    return InView(
      delay: delay,
      builder: (context, visible) => TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: visible ? 1 : 0),
        duration: still ? Duration.zero : duration,
        curve: curve,
        child: child,
        builder: (context, t, child) => Opacity(
          opacity: clamp01(t),
          child: Transform.translate(
            offset: offset * (1 - t),
            child: Transform.scale(scale: scale + (1 - scale) * t, child: child),
          ),
        ),
      ),
    );
  }
}

/// Counts a value like `$38`, `1,200` or `12k` up from zero when in view.
class CountUpText extends StatelessWidget {
  const CountUpText({
    super.key,
    required this.value,
    this.style,
    this.duration = const Duration(milliseconds: 1600),
  });

  final String value;
  final TextStyle? style;
  final Duration duration;

  static final _pattern = RegExp(r'^([^0-9]*)([0-9][0-9,]*)(\.[0-9]+)?(.*)$');

  @override
  Widget build(BuildContext context) {
    final match = _pattern.firstMatch(value.trim());
    if (match == null) return Text(value, style: style);

    final prefix = match.group(1)!;
    final whole = match.group(2)!;
    final fraction = match.group(3);
    final suffix = match.group(4)!;
    final grouped = whole.contains(',');
    final places = fraction == null ? 0 : fraction.length - 1;
    final target = double.tryParse(
      '${whole.replaceAll(',', '')}${fraction ?? ''}',
    );
    if (target == null) return Text(value, style: style);

    String format(double v) {
      var text = v.toStringAsFixed(places);
      if (grouped) {
        final parts = text.split('.');
        parts[0] = parts[0].replaceAllMapped(
          RegExp(r'\B(?=(\d{3})+(?!\d))'),
          (_) => ',',
        );
        text = parts.join('.');
      }
      return '$prefix$text$suffix';
    }

    final still = reduceMotion(context);
    return InView(
      builder: (context, visible) => TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: visible ? target : 0),
        duration: still ? Duration.zero : duration,
        curve: Curves.easeOutCubic,
        builder: (context, v, _) =>
            Text(format(visible ? v : 0), style: style, maxLines: 1),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Section heading shared by every section
// ---------------------------------------------------------------------------

class SectionHeading extends StatelessWidget {
  const SectionHeading({
    super.key,
    required this.eyebrow,
    required this.title,
    this.description,
    this.trailing,
    this.padding = const EdgeInsets.fromLTRB(20, 12, 20, 14),
  });

  final String eyebrow;
  final String title;
  final String? description;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: padding,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (eyebrow.isNotEmpty)
                  Reveal(
                    offset: const Offset(-12, 0),
                    child: Text(
                      eyebrow.toUpperCase(),
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: colors.primary,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.2,
                      ),
                    ),
                  ),
                const SizedBox(height: 4),
                Reveal(
                  delay: const Duration(milliseconds: 80),
                  child: Text(
                    title,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                      letterSpacing: -.5,
                    ),
                  ),
                ),
                const SizedBox(height: 7),
                InView(
                  delay: const Duration(milliseconds: 200),
                  builder: (context, visible) => AnimatedContainer(
                    duration: reduceMotion(context)
                        ? Duration.zero
                        : const Duration(milliseconds: 700),
                    curve: Curves.easeOutCubic,
                    width: visible ? 44 : 0,
                    height: 4,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(4),
                      gradient: LinearGradient(
                        colors: [colors.primary, colors.tertiary],
                      ),
                    ),
                  ),
                ),
                if (description != null) ...[
                  const SizedBox(height: 8),
                  Reveal(
                    delay: const Duration(milliseconds: 140),
                    child: Text(
                      description!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Decorative layers
// ---------------------------------------------------------------------------

/// Twinkling four-point stars painted over its parent.
class Sparkles extends StatelessWidget {
  const Sparkles({
    super.key,
    this.count = 16,
    this.color = Colors.white,
    this.seed = 7,
    this.maxSize = 5,
  });

  final int count;
  final Color color;
  final int seed;
  final double maxSize;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: Loop(
      duration: const Duration(seconds: 6),
      builder: (context, t, _) => CustomPaint(
        size: Size.infinite,
        painter: _SparklePainter(t, count, color, seed, maxSize),
      ),
    ),
  );
}

class _SparklePainter extends CustomPainter {
  _SparklePainter(this.t, this.count, this.color, this.seed, this.maxSize);

  final double t;
  final int count;
  final Color color;
  final int seed;
  final double maxSize;

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty) return;
    final random = math.Random(seed);
    final paint = Paint();
    for (var i = 0; i < count; i++) {
      final x = random.nextDouble() * size.width;
      final y = random.nextDouble() * size.height;
      final phase = random.nextDouble();
      final base = (.4 + random.nextDouble() * .6) * maxSize;
      final wave = (math.sin(2 * math.pi * (t * (1 + i % 2) + phase)) + 1) / 2;
      paint.color = color.withValues(alpha: .12 + .7 * wave);
      canvas.drawPath(starPath(Offset(x, y), base * (.45 + .55 * wave)), paint);
    }
  }

  @override
  bool shouldRepaint(_SparklePainter old) => old.t != t;
}

/// Slowly drifting colour glows that sit behind the whole page.
class AmbientBackdrop extends StatelessWidget {
  const AmbientBackdrop({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return IgnorePointer(
      child: Loop(
        duration: const Duration(seconds: 40),
        builder: (context, t, _) {
          final a = 2 * math.pi * t;
          return Stack(
            children: [
              _blob(
                Alignment(math.cos(a) * .9, -.75 + math.sin(a) * .15),
                420,
                colors.primary,
              ),
              _blob(
                Alignment(-math.sin(a) * .9, .1 + math.cos(a) * .25),
                340,
                colors.secondary,
              ),
              _blob(
                Alignment(math.sin(a + 2) * .8, .85 - math.cos(a) * .12),
                380,
                colors.tertiary,
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _blob(Alignment alignment, double size, Color color) => Align(
    alignment: alignment,
    child: Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(
          colors: [color.withValues(alpha: .16), color.withValues(alpha: 0)],
        ),
      ),
    ),
  );
}
