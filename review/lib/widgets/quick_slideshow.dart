import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Default height of a [QuickSlideShow] card, in logical pixels.
///
/// The height belongs to the page, not to the slide: pass
/// `QuickSlideShow(height: ...)` at the call site (or reuse this constant) and the
/// card keeps that height no matter how much copy a slide carries. Slide data
/// never sets or stretches it.
const double kSlideShowHeight = 178;

/// Content for one slide in [QuickSlideShow].
///
/// Each slide uses a semantic icon on a softly lit tile.
class QuickySlide {
  const QuickySlide({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
    required this.ctaLabel,
    required this.icon,
    this.serviceKey,
    this.providerId,
    this.actionKey,
    this.gradient,
    this.accent,
  });

  /// Small label above the headline, e.g. `MTN · Instant airtime`.
  final String eyebrow;

  /// Bold headline. Rendered on a single line and truncated with an ellipsis.
  final String title;

  /// Supporting sentence. Single line, truncated with an ellipsis.
  final String subtitle;

  /// Label of the white call-to-action pill.
  final String ctaLabel;

  /// Icon representing the destination or service.
  final IconData icon;

  /// Optional host payload: the VAS service this slide belongs to.
  final String? serviceKey;

  /// Optional host payload: the provider to preselect on the purchase screen.
  final String? providerId;

  /// Optional application navigation key handled by the host screen.
  final String? actionKey;

  /// Optional per-slide background override.
  final Gradient? gradient;

  /// Optional per-slide lime/accent override.
  final Color? accent;
}

/// Auto-playing, swipeable product carousel.
///
/// Visual language: rounded card with a green gradient, segmented timer at the
/// top, an eyebrow/headline/subtitle block, a white call-to-action pill, and a
/// rounded service icon on the right.
///
/// Slides live in a [PageView]: advancing (or swiping) pushes the next product
/// in from the right while the current one leaves to the left. The segmented
/// timer restarts on every page. The card has a fixed [height] so slide copy can
/// never resize it, and every text line is clamped to a single line with an
/// ellipsis.
///
/// The widget is self-contained and reusable: pass any list of [QuickySlide]s and
/// an optional [onSlideTap] callback.
class QuickSlideShow extends StatefulWidget {
  const QuickSlideShow({
    super.key,
    required this.slides,
    this.onSlideTap,
    this.autoPlay = true,
    this.interval = const Duration(seconds: 5),
    this.height = kSlideShowHeight,
    this.borderRadius = 28,
    this.gradient = const LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [Color(0xFF0F5C42), Color(0xFF15795A), Color(0xFF1C9973)],
    ),
    this.accent = const Color(0xFFD3FF44),
    this.foreground = const Color(0xFF0B3B2C),
    this.padding = const EdgeInsets.fromLTRB(15, 12, 15, 12),
    this.showTimer = true,
  });

  final List<QuickySlide> slides;

  /// Called with the tapped slide index and its data.
  final void Function(int index, QuickySlide slide)? onSlideTap;

  final bool autoPlay;
  final Duration interval;

  /// Fixed card height. The slide content adapts to it; it never grows it.
  final double height;
  final double borderRadius;
  final Gradient gradient;
  final Color accent;

  /// Deep colour used on the white CTA pill.
  final Color foreground;
  final EdgeInsets padding;
  final bool showTimer;

  @override
  State<QuickSlideShow> createState() => _QuickSlideShowState();
}

class _QuickSlideShowState extends State<QuickSlideShow>
    with TickerProviderStateMixin, WidgetsBindingObserver {
  static const Duration _pageTransition = Duration(milliseconds: 420);

  late final AnimationController _timer;
  late final AnimationController _ambient;
  late final PageController _pages;

  /// Absolute page number. Starts on a large multiple of the slide count so the
  /// carousel can be swiped backwards as well as forwards while looping.
  int _page = 0;

  int get _length => widget.slides.length;

  int get _index => _length == 0 ? 0 : ((_page % _length) + _length) % _length;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    final start = 1000 * (_length == 0 ? 1 : _length);
    _page = start;
    _pages = PageController(initialPage: start);
    _timer = AnimationController(vsync: this, duration: widget.interval)
      ..addStatusListener(_onTimerStatus);
    _ambient = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 26),
    )..repeat();
    _restartTimer();
  }

  @override
  void didUpdateWidget(covariant QuickSlideShow oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.interval != widget.interval) {
      _timer.duration = widget.interval;
    }
    if (oldWidget.interval != widget.interval ||
        oldWidget.autoPlay != widget.autoPlay ||
        oldWidget.slides.length != widget.slides.length) {
      _restartTimer();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _ambient.repeat();
      _restartTimer();
    } else {
      _ambient.stop();
      _timer.stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer.dispose();
    _ambient.dispose();
    _pages.dispose();
    super.dispose();
  }

  void _onTimerStatus(AnimationStatus status) {
    if (status == AnimationStatus.completed && mounted) _advance();
  }

  void _restartTimer() {
    _timer.stop();
    if (!widget.autoPlay || widget.slides.length < 2) {
      _timer.value = 0;
      return;
    }
    _timer.forward(from: 0);
  }

  /// Pushes the next product in from the right.
  void _advance() {
    if (widget.slides.length < 2) return;
    if (!_pages.hasClients) {
      _restartTimer();
      return;
    }
    _pages.nextPage(duration: _pageTransition, curve: Curves.easeOutCubic);
    // Normally [onPageChanged] restarts the timer. This covers the case where
    // the page cannot actually move (e.g. the card is offstage).
    Future<void>.delayed(
      _pageTransition + const Duration(milliseconds: 80),
      () {
        if (mounted && !_timer.isAnimating) _restartTimer();
      },
    );
  }

  void _onPageChanged(int page) {
    setState(() => _page = page);
    _restartTimer();
  }

  void _handleTap(int index) {
    if (index < 0 || index >= widget.slides.length) return;
    widget.onSlideTap?.call(index, widget.slides[index]);
    _restartTimer();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.slides.isEmpty) return const SizedBox.shrink();
    // A banner, not body copy: clamp text scaling so the composition keeps its
    // shape at large accessibility text sizes.
    return MediaQuery.withClampedTextScaling(
      maxScaleFactor: 1.05,
      child: _card(),
    );
  }

  Widget _card() {
    final slides = widget.slides;
    final slide = slides[_index];
    final accent = slide.accent ?? widget.accent;

    return SizedBox(
      height: widget.height,
      child: Container(
        decoration: BoxDecoration(
          gradient: slide.gradient ?? widget.gradient,
          borderRadius: BorderRadius.circular(widget.borderRadius),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0B3B2C).withValues(alpha: .28),
              blurRadius: 26,
              offset: const Offset(0, 14),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            Positioned(
              right: -70,
              top: -80,
              child: _AmbientRing(
                animation: _ambient,
                size: 280,
                color: accent.withValues(alpha: .34),
                clockwise: true,
              ),
            ),
            Positioned(
              right: 40,
              bottom: -110,
              child: _AmbientRing(
                animation: _ambient,
                size: 200,
                color: Colors.white.withValues(alpha: .16),
                clockwise: false,
              ),
            ),
            Positioned(
              right: -50,
              bottom: -70,
              child: IgnorePointer(
                child: Container(
                  width: 190,
                  height: 190,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        accent.withValues(alpha: .2),
                        accent.withValues(alpha: 0),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            Padding(
              padding: widget.padding,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (widget.showTimer) ...[
                    _SlideTimer(
                      count: slides.length,
                      index: _index,
                      progress: _timer,
                      accent: accent,
                    ),
                    const SizedBox(height: 14),
                  ],
                  Expanded(
                    child: PageView.builder(
                      controller: _pages,
                      onPageChanged: _onPageChanged,
                      itemBuilder: (context, page) {
                        final slot =
                            ((page % slides.length) + slides.length) %
                            slides.length;
                        final item = slides[slot];
                        return _SlideBody(
                          key: ValueKey<int>(page),
                          slide: item,
                          accent: item.accent ?? widget.accent,
                          foreground: widget.foreground,
                          ambient: _ambient,
                          onTap: () => _handleTap(slot),
                        );
                      },
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
}

class _SlideBody extends StatelessWidget {
  const _SlideBody({
    super.key,
    required this.slide,
    required this.accent,
    required this.foreground,
    required this.ambient,
    required this.onTap,
  });

  final QuickySlide slide;
  final Color accent;
  final Color foreground;
  final Animation<double> ambient;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact =
            constraints.maxWidth < 340 || constraints.maxHeight < 130;
        final tile = (constraints.maxWidth * .23).clamp(58.0, 86.0).toDouble();
        return Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    slide.eyebrow,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .76),
                      fontSize: compact ? 10.5 : 11,
                      fontWeight: FontWeight.w600,
                      letterSpacing: .1,
                    ),
                  ),
                  SizedBox(height: compact ? 3 : 4),
                  Text(
                    slide.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: compact ? 21 : 25,
                      fontWeight: FontWeight.w900,
                      height: 1.12,
                      letterSpacing: -.5,
                    ),
                  ),
                  SizedBox(height: compact ? 4 : 6),
                  Text(
                    slide.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .84),
                      fontSize: compact ? 11 : 12,
                      height: 1.35,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  SizedBox(height: compact ? 9 : 12),
                  _SlideCta(
                    label: slide.ctaLabel,
                    foreground: foreground,
                    onTap: onTap,
                    compact: compact,
                  ),
                ],
              ),
            ),
            SizedBox(width: compact ? 10 : 14),
            _SlideArt(
              slide: slide,
              accent: accent,
              foreground: foreground,
              ambient: ambient,
              size: tile,
              onTap: onTap,
            ),
          ],
        );
      },
    );
  }
}

class _SlideTimer extends StatelessWidget {
  const _SlideTimer({
    required this.count,
    required this.index,
    required this.progress,
    required this.accent,
  });

  final int count;
  final int index;
  final Animation<double> progress;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 4,
      child: Row(
        children: [
          for (var slot = 0; slot < count; slot++) ...[
            if (slot > 0) const SizedBox(width: 8),
            Expanded(
              child: AnimatedBuilder(
                animation: progress,
                builder: (context, _) {
                  final value = slot < index
                      ? 1.0
                      : slot > index
                      ? 0.0
                      : progress.value;
                  return _TimerSegment(
                    value: value.clamp(0.0, 1.0).toDouble(),
                    accent: accent,
                    active: slot <= index,
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _TimerSegment extends StatelessWidget {
  const _TimerSegment({
    required this.value,
    required this.accent,
    required this.active,
  });

  final double value;
  final Color accent;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final radius = BorderRadius.circular(3);
        return Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: active ? .2 : .26),
                borderRadius: radius,
              ),
            ),
            Positioned(
              left: 0,
              top: 0,
              bottom: 0,
              width: constraints.maxWidth * value,
              child: DecoratedBox(
                decoration: BoxDecoration(color: accent, borderRadius: radius),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _SlideCta extends StatelessWidget {
  const _SlideCta({
    required this.label,
    required this.foreground,
    required this.onTap,
    required this.compact,
  });

  final String label;
  final Color foreground;
  final VoidCallback onTap;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: .12),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(30),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: EdgeInsets.symmetric(
              horizontal: compact ? 13 : 16,
              vertical: compact ? 7 : 10,
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.bolt_rounded,
                  size: compact ? 14 : 16,
                  color: foreground,
                ),
                const SizedBox(width: 5),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: foreground,
                      fontSize: compact ? 11.5 : 12.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: .1,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SlideArt extends StatelessWidget {
  const _SlideArt({
    required this.slide,
    required this.accent,
    required this.foreground,
    required this.ambient,
    required this.size,
    required this.onTap,
  });

  final QuickySlide slide;
  final Color accent;
  final Color foreground;
  final Animation<double> ambient;
  final double size;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(size * .28);
    return AnimatedBuilder(
      animation: ambient,
      builder: (context, _) {
        final pulse = .5 + .5 * math.sin(ambient.value * math.pi * 2);
        return GestureDetector(
          onTap: onTap,
          child: Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              color: accent,
              borderRadius: radius,
              boxShadow: [
                BoxShadow(
                  color: accent.withValues(alpha: .26 + .2 * pulse),
                  blurRadius: 16 + 10 * pulse,
                  spreadRadius: 1 + 2 * pulse,
                ),
              ],
            ),
            child: Icon(
              slide.icon,
              color: foreground,
              size: size * .46,
            ),
          ),
        );
      },
    );
  }
}

class _AmbientRing extends StatelessWidget {
  const _AmbientRing({
    required this.animation,
    required this.size,
    required this.color,
    required this.clockwise,
  });

  final Animation<double> animation;
  final double size;
  final Color color;
  final bool clockwise;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: animation,
        builder: (context, child) {
          final angle = (clockwise ? 1 : -1) * animation.value * math.pi * 2;
          return Transform.rotate(
            angle: angle,
            child: child ?? const SizedBox.shrink(),
          );
        },
        child: SizedBox(
          width: size,
          height: size,
          child: CustomPaint(painter: _DashedRingPainter(color: color)),
        ),
      ),
    );
  }
}

class _DashedRingPainter extends CustomPainter {
  const _DashedRingPainter({
    required this.color,
    this.strokeWidth = 1.3,
    this.dash = 5,
    this.gap = 7,
  });

  final Color color;
  final double strokeWidth;
  final double dash;
  final double gap;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth;
    final path = Path()..addOval((Offset.zero & size).deflate(strokeWidth / 2));
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final next = math.min(distance + dash, metric.length);
        canvas.drawPath(metric.extractPath(distance, next), paint);
        distance = next + gap;
      }
    }
  }

  @override
  bool shouldRepaint(_DashedRingPainter oldDelegate) =>
      oldDelegate.color != color ||
      oldDelegate.strokeWidth != strokeWidth ||
      oldDelegate.dash != dash ||
      oldDelegate.gap != gap;
}
