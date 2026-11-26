import 'dart:async';

import 'package:flutter/material.dart';

/// A small, reusable auto-advancing PageView for landing-page content.
class AutoSlideView extends StatefulWidget {
  const AutoSlideView({
    super.key,
    required this.itemCount,
    required this.itemBuilder,
    required this.height,
    required this.interval,
    this.viewportFraction = .88,
    this.padding = const EdgeInsets.symmetric(horizontal: 18),
  });

  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;
  final double height;
  final Duration interval;
  final double viewportFraction;
  final EdgeInsetsGeometry padding;

  @override
  State<AutoSlideView> createState() => _AutoSlideViewState();
}

class _AutoSlideViewState extends State<AutoSlideView> {
  late final PageController _controller;
  Timer? _timer;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController(viewportFraction: widget.viewportFraction);
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant AutoSlideView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.interval != widget.interval ||
        oldWidget.itemCount != widget.itemCount) {
      _timer?.cancel();
      if (_page >= widget.itemCount) _page = 0;
      _startTimer();
    }
  }

  void _startTimer() {
    if (widget.itemCount < 2) return;
    _timer = Timer.periodic(widget.interval, (_) {
      if (!mounted || !_controller.hasClients) return;
      final next = (_page + 1) % widget.itemCount;
      _controller.animateToPage(
        next,
        duration: const Duration(milliseconds: 450),
        curve: Curves.easeInOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.itemCount == 0) return const SizedBox.shrink();
    final colors = Theme.of(context).colorScheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          height: widget.height,
          child: PageView.builder(
            controller: _controller,
            itemCount: widget.itemCount,
            onPageChanged: (value) => setState(() => _page = value),
            itemBuilder: (context, index) => Padding(
              padding: widget.padding,
              child: widget.itemBuilder(context, index),
            ),
          ),
        ),
        if (widget.itemCount > 1) ...[
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(
              widget.itemCount,
              (index) => AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: index == _page ? 18 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: index == _page
                      ? colors.primary
                      : colors.outlineVariant,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}
