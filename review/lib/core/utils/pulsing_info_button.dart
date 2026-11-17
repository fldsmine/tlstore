import 'package:flutter/material.dart';

class PulsingInfoButton extends StatefulWidget {
  final VoidCallback onPressed;
  final Color color;

  const PulsingInfoButton({
    super.key,
    required this.onPressed,
    required this.color,
  });

  @override
  State<PulsingInfoButton> createState() => _PulsingInfoButtonState();
}

class _PulsingInfoButtonState extends State<PulsingInfoButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _animation;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat(reverse: true);

    _animation = Tween<double>(
      begin: 1.0,
      end: 1.2,
    ).animate(
      CurvedAnimation(
        parent: _controller,
        curve: Curves.easeInOut,
      ),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, child) {
        return Transform.scale(
          scale: _animation.value,
          child: child,
        );
      },
      child: IconButton(
        onPressed: widget.onPressed,
        icon: Icon(
          Icons.info_outline_rounded,
          size: 20,
          color: widget.color,
        ),
        padding: EdgeInsets.zero,
        constraints: const BoxConstraints(),
      ),
    );
  }
}