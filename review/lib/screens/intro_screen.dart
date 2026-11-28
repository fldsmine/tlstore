import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class IntroScreen extends StatefulWidget {
  const IntroScreen({
    super.key,
    required this.onLogin,
    required this.onRegister,
    required this.onExplore,
  });

  final VoidCallback onLogin;
  final VoidCallback onRegister;

  /// Opens the public landing page without a session, so visitors can look
  /// around before deciding to register or log in.
  final VoidCallback onExplore;

  @override
  State<IntroScreen> createState() => _IntroScreenState();
}

class _IntroScreenState extends State<IntroScreen> {
  final PageController _pages = PageController();
  int _page = 0;

  static const _slides = [
    _IntroSlide(
      asset: 'assets/intro1.svg',
      title: 'Discover apps worth reviewing',
      description:
          'Browse real app listings, share useful feedback, and earn a reward when your review is approved.',
    ),
    _IntroSlide(
      asset: 'assets/intro2.svg',
      title: 'Share campaigns and earn',
      description:
          'Choose sponsored campaigns that fit you. Public shares are checked by our team before rewards are approved.',
    ),
    _IntroSlide(
      asset: 'assets/intro3.svg',
      title: 'Find tasks across regions',
      description:
          'Explore app reviews and sponsored ad tasks from every region. Your active package determines which task types you can complete.',
    ),
  ];

  bool get _isLastPage => _page == _slides.length;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _next() {
    if (_isLastPage) {
      widget.onRegister();
      return;
    }

    _pages.nextPage(
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          ColoredBox(
            color: theme.scaffoldBackgroundColor,
          ),

          if (theme.brightness == Brightness.light)
            Image.asset(
              'assets/intro-bg.png',
              fit: BoxFit.cover,
            ),

          SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: PageView.builder(
                    controller: _pages,
                    itemCount: _slides.length + 1,
                    onPageChanged: (value) {
                      setState(() => _page = value);
                    },
                    itemBuilder: (context, index) {

                      if (index == _slides.length) {
                        return LastSlideScreen(
                          onGetStarted: widget.onRegister,
                          onLogin: widget.onLogin,
                          onExplore: widget.onExplore,
                        );
                      }

                      // First three onboarding pages
                      return _buildSlide(
                        theme,
                        _slides[index],
                      );
                    },
                  ),
                ),

                // Hide old pager controls on the welcome screen.
                if (!_isLastPage)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      24,
                      4,
                      24,
                      22,
                    ),
                    child: _buildPagerControls(colors),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSlide(
    ThemeData theme,
    _IntroSlide slide,
  ) {
    final size = MediaQuery.sizeOf(context);
    final colors = theme.colorScheme;

    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 16, 28, 20),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: size.width * .68,
            height: size.height * .34,
            child: SvgPicture.asset(
              slide.asset,
              fit: BoxFit.contain,
              placeholderBuilder: (_) => Icon(
                Icons.auto_awesome_rounded,
                size: 92,
                color: colors.primary.withValues(alpha: .25),
              ),
            ),
          ),

          SizedBox(height: size.height * .025),

          Text(
            slide.title,
            textAlign: TextAlign.center,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
              height: 1.2,
            ),
          ),

          const SizedBox(height: 12),

          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 360),
            child: Text(
              slide.description,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge?.copyWith(
                color: colors.onSurfaceVariant,
                height: 1.5,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPagerControls(ColorScheme colors) {
    return Row(
      children: [
        TextButton(
          onPressed: widget.onExplore,
          child: const Text('Explore'),
        ),

        const Spacer(),

        Row(
          children: List.generate(
            _slides.length + 1,
            (index) {
              final active = index == _page;

              return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: active ? 19 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: active
                      ? colors.primary
                      : colors.outlineVariant,
                  borderRadius: BorderRadius.circular(8),
                ),
              );
            },
          ),
        ),

        const Spacer(),

        TextButton(
          onPressed: _next,
          child: const Text('Next'),
        ),
      ],
    );
  }
}

// --------------------------------------------------
// LAST PAGE: TL STORE WELCOME SCREEN
// --------------------------------------------------

class LastSlideScreen extends StatelessWidget {
  const LastSlideScreen({
    super.key,
    required this.onGetStarted,
    required this.onLogin,
    required this.onExplore,
  });

  final VoidCallback onGetStarted;
  final VoidCallback onLogin;
  final VoidCallback onExplore;

  static const Color primaryGreen = Color(0xFF0C573A);
  static const Color darkGreen = Color(0xFF145A3D);
  static const Color orange = Color(0xFFF44735);

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return Stack(
          fit: StackFit.expand,
          children: [
            // Background
            Image.asset(
              'assets/splash-bg.png',
              fit: BoxFit.cover,
            ),

            // Gradient overlay
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    orange.withValues(alpha: 0.65),
                    const Color(0xFFB64B30)
                        .withValues(alpha: 0.65),
                    darkGreen.withValues(alpha: 0.75),
                    primaryGreen.withValues(alpha: 0.90),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
            ),

            // Main content
            SafeArea(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight,
                  ),
                  child: IntrinsicHeight(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 24,
                        vertical: 20,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Logo and app name
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(
                                    alpha: 0.15,
                                  ),
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: const Icon(
                                  Icons.eco_rounded,
                                  color: Colors.white,
                                  size: 30,
                                ),
                              ),

                              const SizedBox(width: 12),

                              const Text(
                                'TL Store',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 22,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),

                          const Spacer(flex: 2),

                          // Illustration
                          Center(
                            child: Container(
                              width: 190,
                              height: 190,
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(
                                  alpha: 0.12,
                                ),
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: Colors.white.withValues(
                                    alpha: 0.2,
                                  ),
                                  width: 2,
                                ),
                              ),
                              child: const Icon(
                                Icons.nature_people_rounded,
                                color: Colors.white,
                                size: 100,
                              ),
                            ),
                          ),

                          const Spacer(flex: 2),

                          // Welcome heading
                          const Text(
                            'Welcome to\nA Better Lifestyle.',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 38,
                              height: 1.2,
                              fontWeight: FontWeight.bold,
                            ),
                          ),

                          const SizedBox(height: 18),

                          // Description
                          Text(
                            'Discover a smarter and simpler way '
                            'to earn, connect and grow. '
                            'Your journey starts here.',
                            style: TextStyle(
                              color: Colors.white.withValues(
                                alpha: 0.85,
                              ),
                              fontSize: 16,
                              height: 1.6,
                            ),
                          ),

                          const SizedBox(height: 35),

                          // Get Started
                          SizedBox(
                            width: double.infinity,
                            height: 58,
                            child: ElevatedButton(
                              onPressed: onGetStarted,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.white,
                                foregroundColor: primaryGreen,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                              ),
                              child: const Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.center,
                                children: [
                                  Text(
                                    'Get Started',
                                    style: TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  SizedBox(width: 10),
                                  Icon(Icons.arrow_forward_rounded),
                                ],
                              ),
                            ),
                          ),

                          const SizedBox(height: 16),

                          // Login
                          SizedBox(
                            width: double.infinity,
                            height: 58,
                            child: OutlinedButton(
                              onPressed: onLogin,
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.white,
                                side: const BorderSide(
                                  color: Colors.white70,
                                  width: 1.2,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                              ),
                              child: const Text(
                                'I already have an account',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 10),

                          // Explore: browse the public landing page without
                          // creating an account or logging in.
                          SizedBox(
                            width: double.infinity,
                            height: 52,
                            child: TextButton.icon(
                              onPressed: onExplore,
                              style: TextButton.styleFrom(
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                ),
                              ),
                              icon: const Icon(
                                Icons.travel_explore_rounded,
                                size: 20,
                              ),
                              label: const Text(
                                'Explore without an account',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 20),

                          // Footer
                          Center(
                            child: Text(
                              'Simple living. Better experiences.',
                              style: TextStyle(
                                color: Colors.white.withValues(
                                  alpha: 0.65,
                                ),
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

// -------------------
// INTRO SLIDE MODEL
// -------------------

class _IntroSlide {
  const _IntroSlide({
    required this.asset,
    required this.title,
    required this.description,
  });

  final String asset;
  final String title;
  final String description;
}
