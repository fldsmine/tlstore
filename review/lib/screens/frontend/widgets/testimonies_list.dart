import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:review/services/media_url.dart';

class TestimoniesList extends StatefulWidget {
  final List<Map<String, dynamic>> testimonies;

  const TestimoniesList({
    super.key, 
    required this.testimonies, 
  });

  @override
  State<TestimoniesList> createState() => _TestimoniesListState();
}

class _TestimoniesListState extends State<TestimoniesList> {
    int _testimonialPage = 0;
    final _testimonialController = PageController(viewportFraction: .91);

  @override
  void dispose() {
    _testimonialController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {

    if (widget.testimonies.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final colors = theme.colorScheme;


    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionHeading(
          context,
          'Member stories',
          'What members say',
          trailing: Row(
            children: [
              IconButton.filledTonal(
                tooltip: 'Previous story',
                onPressed: _testimonialPage == 0
                    ? null
                    : () => _testimonialController.previousPage(
                        duration: const Duration(milliseconds: 250),
                        curve: Curves.easeOut,
                      ),
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              const SizedBox(width: 4),
              IconButton.filledTonal(
                tooltip: 'Next story',
                onPressed: _testimonialPage >= widget.testimonies.length - 1
                    ? null
                    : () => _testimonialController.nextPage(
                        duration: const Duration(milliseconds: 250),
                        curve: Curves.easeOut,
                      ),
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
        ),
        SizedBox(
          height: 230,
          child: PageView.builder(
            controller: _testimonialController,
            itemCount: widget.testimonies.length,
            onPageChanged: (index) => setState(() => _testimonialPage = index),
            itemBuilder: (context, index) {
              final review = widget.testimonies[index];
              final avatar = _image(review['avatar']);
              final rating = (review['rating'] as num?)?.toInt() ?? 5;
              return Padding(
                padding: const EdgeInsets.fromLTRB(18, 2, 8, 8),
                child: Card(
                  elevation: 0,
                  color: colors.surfaceContainerLow,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(24),
                    side: BorderSide(color: colors.outlineVariant),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: List.generate(
                            rating.clamp(0, 5).toInt(),
                            (_) => Icon(
                              Icons.star_rounded,
                              size: 18,
                              color: colors.tertiary,
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Expanded(
                          child: Text(
                            '“${_text(review['content'], 'A great experience with TLStore.')}”',
                            maxLines: 5,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodyLarge?.copyWith(
                              height: 1.45,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 17,
                              backgroundColor: colors.primaryContainer,
                              foregroundImage: avatar.isEmpty
                                  ? null
                                  : CachedNetworkImageProvider(avatar),
                              child: avatar.isEmpty
                                  ? Icon(
                                      Icons.person_rounded,
                                      color: colors.onPrimaryContainer,
                                    )
                                  : null,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _text(review['name'], 'TLStore member'),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        const SizedBox(height: 18),
      ],
    );

  }

  String _text(Object? value, String fallback) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty || text == 'null' ? fallback : text;
  }
  String _image(Object? value) => MediaUrl.resolve(value?.toString()) ?? '';

  Widget _sectionHeading(
    BuildContext context,
    String eyebrow,
    String title, {
    String? description,
    Widget? trailing,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  eyebrow.toUpperCase(),
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: colors.primary,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  title,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                    letterSpacing: -.5,
                  ),
                ),
                if (description != null) ...[
                  const SizedBox(height: 5),
                  Text(
                    description,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                      height: 1.4,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) trailing,
        ],
      ),
    );
  }

}
