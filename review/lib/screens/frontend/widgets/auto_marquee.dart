import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

class AutoMarquee extends StatefulWidget {
  const AutoMarquee({
    super.key,
    required this.itemCount,
    required this.itemBuilder,
    this.height = 80,
    this.speed = 35,
    this.itemSpacing = 12,
    this.padding = EdgeInsets.zero,
    this.pauseOnTouch = true,
  });

  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;

  /// Height of the marquee.
  final double height;

  /// Scroll speed in pixels per second.
  final double speed;

  /// Space between items.
  final double itemSpacing;

  final EdgeInsetsGeometry padding;

  /// Pause automatic scrolling while touching or dragging.
  final bool pauseOnTouch;

  @override
  State<AutoMarquee> createState() => _AutoMarqueeState();
}

class _AutoMarqueeState extends State<AutoMarquee> {
  final ScrollController _controller = ScrollController();

  late final Ticker _ticker;

  Duration? _lastElapsed;

  double _loopDistance = 0;

  bool _isPaused = false;

  @override
  void initState() {
    super.initState();

    _ticker = Ticker(_onTick);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _startScrolling();
    });
  }

  @override
  void didUpdateWidget(covariant AutoMarquee oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.speed != widget.speed ||
        oldWidget.itemCount != widget.itemCount) {
      _lastElapsed = null;

      WidgetsBinding.instance.addPostFrameCallback((_) {
        _startScrolling();
      });
    }
  }

  void _startScrolling() {
    if (!mounted ||
        _isPaused ||
        widget.itemCount == 0 ||
        widget.speed <= 0 ||
        !_controller.hasClients) {
      return;
    }

    final position = _controller.position;

    // Two identical sets of items are displayed.
    // Calculate the width of one complete set.
    _loopDistance =
        (position.maxScrollExtent + position.viewportDimension) / 2;

    if (_loopDistance <= 0) return;

    if (!_ticker.isActive) {
      _lastElapsed = null;
      _ticker.start();
    }
  }

  void _onTick(Duration elapsed) {
    if (!mounted || !_controller.hasClients || _isPaused) return;

    final previous = _lastElapsed;
    _lastElapsed = elapsed;

    if (previous == null) return;

    final delta = (elapsed - previous).inMicroseconds /
        Duration.microsecondsPerSecond;

    final position = _controller.position;

    double nextOffset =
        position.pixels + (widget.speed * delta);

    // Seamless loop: jump back by exactly one content width.
    if (_loopDistance > 0 && nextOffset >= _loopDistance) {
      nextOffset -= _loopDistance;
    }

    nextOffset = nextOffset.clamp(
      0.0,
      position.maxScrollExtent,
    );

    _controller.jumpTo(nextOffset);
  }

  void _pause() {
    if (!widget.pauseOnTouch) return;

    _isPaused = true;
    _ticker.stop();
    _lastElapsed = null;
  }

  void _resume() {
    if (!widget.pauseOnTouch || !_isPaused) return;

    _isPaused = false;
    _startScrolling();
  }

  @override
  void dispose() {
    _ticker.dispose();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.itemCount == 0) {
      return const SizedBox.shrink();
    }

    return SizedBox(
      height: widget.height,
      child: Listener(
        onPointerDown: (_) => _pause(),
        onPointerUp: (_) => _resume(),
        onPointerCancel: (_) => _resume(),
        child: ListView.builder(
          controller: _controller,
          scrollDirection: Axis.horizontal,
          physics: const BouncingScrollPhysics(),
          padding: widget.padding,
          itemCount: widget.itemCount * 2,
          itemBuilder: (context, index) {
            final actualIndex = index % widget.itemCount;

            return Padding(
              padding: EdgeInsets.only(
                right: widget.itemSpacing,
              ),
              child: widget.itemBuilder(
                context,
                actualIndex,
              ),
            );
          },
        ),
      ),
    );
  }
}
