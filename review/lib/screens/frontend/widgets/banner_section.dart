import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:review/services/media_url.dart';

import 'anim/anim_kit.dart';
import 'anim/illustrations.dart';

class BannerSection extends StatelessWidget {
  final Map<String, dynamic> banner;
  final Map<String, dynamic> user;

  const BannerSection({super.key, required this.banner, required this.user});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final image = _image(banner['image']);
    const radius = BorderRadius.only(
      bottomLeft: Radius.circular(30),
      bottomRight: Radius.circular(30),
    );

    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [colors.primary, colors.secondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: radius,
      ),
      child: Stack(
        children: [
          // The API image is kept, but only as a faint texture under the art.
          if (image.isNotEmpty)
            Positioned.fill(
              child: Opacity(
                opacity: .14,
                child: CachedNetworkImage(
                  imageUrl: image,
                  fit: BoxFit.cover,
                  errorWidget: (context, url, error) =>
                      const SizedBox.shrink(),
                ),
              ),
            ),
          // Slowly travelling highlight.
          Positioned.fill(
            child: IgnorePointer(
              child: Loop(
                duration: const Duration(seconds: 14),
                builder: (context, t, _) => DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: RadialGradient(
                      center: Alignment(
                        math.cos(2 * math.pi * t) * .9,
                        math.sin(2 * math.pi * t) * .6,
                      ),
                      radius: .9,
                      colors: [
                        Colors.white.withValues(alpha: .16),
                        Colors.white.withValues(alpha: 0),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          const Positioned.fill(child: Sparkles(count: 22, seed: 3)),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 30),
            child: LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 720;
                final copy = _copy(context);
                if (wide) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(flex: 6, child: copy),
                      const SizedBox(width: 24),
                      const Expanded(flex: 5, child: HeroArt(height: 340)),
                    ],
                  );
                }
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    copy,
                    const SizedBox(height: 18),
                    const Center(child: HeroArt(height: 250)),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _copy(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final isLoggin = user['status'] == 'active';
    const step = Duration(milliseconds: 110);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Image.asset(
              'assets/others/logolong.png',
              fit: BoxFit.cover,
              height: 64,
            ),
            const Spacer(),
            IconButton.filledTonal(
              tooltip: 'search user',
              style: IconButton.styleFrom(
                backgroundColor: Colors.black38.withValues(alpha: .3),
              ),
              icon: const Icon(Icons.search_rounded, color: Colors.white),
              onPressed: () {},
            ),
          ],
        ),
        const SizedBox(height: 16),
        Reveal(
          offset: const Offset(0, 24),
          child: Text(
            _text(banner['title'], 'Review. Share. Earn.'),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              fontWeight: FontWeight.bold,
              height: 1.2,
            ),
          ),
        ),
        const SizedBox(height: 12),
        Reveal(
          delay: step,
          child: Text(
            _text(
              banner['subtitle'],
              'A community marketplace for app feedback and sponsored sharing.',
            ),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Reveal(
          delay: step * 2,
          child: Text(
            _text(
              banner['description'],
              'Discover opportunities, complete tasks, and earn rewards after approval.',
            ),
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 14,
              height: 1.5,
            ),
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        const SizedBox(height: 20),
        Reveal(
          delay: step * 3,
          child: Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              PulseGlow(
                color: Colors.white,
                child: ElevatedButton(
                  onPressed: () {},
                  //onPressed: () { isLoggin ? dashboard : register },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: colors.primary,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 24,
                      vertical: 12,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(25),
                    ),
                  ),
                  child: Text(
                    isLoggin
                        ? 'Dashboard'
                        : _text(banner['cta_text'], 'Register'),
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
              OutlinedButton(
                onPressed: () {},
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.white,
                  side: const BorderSide(color: Colors.white),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 12,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(25),
                  ),
                ),
                child: Text(
                  isLoggin ? 'Profile' : 'Sign In',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }

  String _image(Object? value) => MediaUrl.resolve(value?.toString()) ?? '';
}
