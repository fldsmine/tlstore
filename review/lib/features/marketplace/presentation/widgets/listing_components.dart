import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../services/auth_api.dart';
import '../../../../services/media_url.dart';
import '../../../../widgets/auth_components.dart';
import '../listing_kind.dart';

class ListingIntro extends StatelessWidget {
  const ListingIntro({super.key, required this.kind, required this.count});
  final ListingKind kind;
  final int count;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF145A3D), Color(0xFF28704F)],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                kind == ListingKind.appReview
                    ? 'Share your opinion'
                    : 'Share with your community',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                '$count available ${kind == ListingKind.appReview ? 'review tasks' : 'campaigns'}',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: .78),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
        Icon(
          kind == ListingKind.appReview
              ? Icons.rate_review_outlined
              : Icons.campaign_outlined,
          color: const Color(0xFFF2D48C),
          size: 34,
        ),
      ],
    ),
  );
}

class ListingCard extends StatelessWidget {
  const ListingCard({
    super.key,
    this.api,
    this.preferredCurrency = 'USD',
    required this.kind,
    required this.item,
    this.onTap,
    this.expanded = false,
  });
  final AuthApi? api;
  final String preferredCurrency;
  final ListingKind kind;
  final Map<String, dynamic> item;
  final VoidCallback? onTap;
  final bool expanded;
  @override
  Widget build(BuildContext context) {
    final app = item['app'] is Map<String, dynamic>
        ? item['app'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final review = kind == ListingKind.appReview;
    final title = review
        ? app['name']?.toString() ?? 'App review'
        : item['title']?.toString() ?? 'Sponsored campaign';
    final subtitle = review
        ? app['store']?.toString() ??
              app['developer']?.toString() ??
              'App store'
        : item['description']?.toString() ?? '';
    final currency = item['currency']?.toString() ?? 'USD';
    final reward = moneyConverted(
      api,
      review ? item['review_reward_minor'] : item['share_reward'],
      currency,
      item['currency_minor_units'],
      preferredCurrency,
      isMinor: review,
    );
    final image = MediaUrl.resolve(
      review ? app['app_icon']?.toString() : item['creative_path']?.toString(),
    );
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE7EBE5)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: expanded ? 64 : 54,
                    height: expanded ? 64 : 54,
                    clipBehavior: Clip.antiAlias,
                    decoration: BoxDecoration(
                      color: softGreen,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: image != null && image.startsWith('http')
                        ? Image.network(
                            image,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => Icon(
                              review
                                  ? Icons.apps_rounded
                                  : Icons.campaign_outlined,
                              color: brandGreen,
                              size: 27,
                            ),
                          )
                        : Icon(
                            review
                                ? Icons.apps_rounded
                                : Icons.campaign_outlined,
                            color: brandGreen,
                            size: 27,
                          ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: ink,
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          subtitle,
                          maxLines: expanded ? 3 : 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: mutedInk,
                            fontSize: 11,
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (!expanded)
                    const Icon(Icons.chevron_right_rounded, color: mutedInk),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  ListingTag(icon: Icons.payments_outlined, text: reward),
                  const SizedBox(width: 7),
                  ListingTag(
                    icon: Icons.location_on_outlined,
                    text: item['location']?.toString() ?? 'Global',
                  ),
                  const Spacer(),
                  if (review && app['rating'] != null)
                    Row(
                      children: [
                        const Icon(
                          Icons.star_rounded,
                          color: Color(0xFFE5A927),
                          size: 16,
                        ),
                        Text(
                          '${app['rating']}',
                          style: const TextStyle(
                            color: mutedInk,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                ],
              ),
              if (item['task_access_status'] != null) ...[
                const SizedBox(height: 10),
                AccessBadge(status: item['task_access_status'].toString()),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class AccessBadge extends StatelessWidget {
  const AccessBadge({super.key, required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final (label, icon, color) = switch (status) {
      'marketplace_plan_required' => (
        'Marketplace plan needed to start',
        Icons.lock_outline_rounded,
        const Color(0xFF9A6811),
      ),
      'location_coverage_required' => (
        'Location add-on needed to start',
        Icons.public_rounded,
        const Color(0xFF9A6811),
      ),
      _ => (
        'Ready to start with your plan',
        Icons.check_circle_outline_rounded,
        brandGreen,
      ),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: status == 'eligible'
            ? const Color(0xFFE8F1EA)
            : const Color(0xFFFFF5DF),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: color),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ListingTag extends StatelessWidget {
  const ListingTag({super.key, required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
    decoration: BoxDecoration(
      color: softGreen,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: brandGreen, size: 13),
        const SizedBox(width: 4),
        Text(
          text,
          style: const TextStyle(
            color: brandGreen,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class DetailCard extends StatelessWidget {
  const DetailCard({super.key, required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: const Color(0xFFE6EAE5)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: children,
    ),
  );
}

class DetailLine extends StatelessWidget {
  const DetailLine({super.key, required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(color: mutedInk, fontSize: 12),
          ),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              color: ink,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class ListingErrorState extends StatelessWidget {
  const ListingErrorState({
    super.key,
    required this.message,
    required this.retry,
  });
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, color: mutedInk, size: 38),
          const SizedBox(height: 9),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: mutedInk),
          ),
          TextButton.icon(
            onPressed: retry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try again'),
          ),
        ],
      ),
    ),
  );
}

class ListingEmptyState extends StatelessWidget {
  const ListingEmptyState({
    super.key,
    required this.title,
    required this.message,
  });
  final String title;
  final String message;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.inbox_outlined, color: brandGreen, size: 40),
          const SizedBox(height: 12),
          Text(
            title,
            style: const TextStyle(
              color: ink,
              fontWeight: FontWeight.w800,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: mutedInk, height: 1.45, fontSize: 12),
          ),
        ],
      ),
    ),
  );
}

String moneyConverted(
  AuthApi? api,
  dynamic amount,
  String sourceCurrency,
  dynamic precision,
  String preferredCurrency, {
  required bool isMinor,
}) {
  final source = sourceCurrency.toUpperCase();
  final digits = (precision as num?)?.toInt() ?? _precisionFor(source);
  final raw = double.tryParse(amount?.toString() ?? '') ?? 0;
  final major = isMinor ? raw / math.pow(10, digits) : raw;
  final converted = api?.convertMajor(
    major,
    source,
    preferredCurrency.toUpperCase(),
  );
  final currency = converted == null ? source : preferredCurrency.toUpperCase();
  final value = converted ?? major;
  return _formatMajorMoney(value, currency);
}

int _precisionFor(String code) {
  if (const {
    'BIF',
    'CLP',
    'DJF',
    'GNF',
    'ISK',
    'JPY',
    'KMF',
    'KRW',
    'PYG',
    'RWF',
    'UGX',
    'VND',
    'VUV',
    'XAF',
    'XOF',
    'XPF',
  }.contains(code)) {
    return 0;
  }
  if (const {'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'}.contains(code)) {
    return 3;
  }
  return 2;
}

String _formatMajorMoney(double amount, String currency) {
  final symbols = {
    'USD': r'$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
    'GHS': '₵',
    'ZAR': 'R',
    'KES': 'KSh',
    'INR': '₹',
  };
  return '${symbols[currency] ?? '$currency '}${amount.toStringAsFixed(_precisionFor(currency))}';
}

String formatExpiry(String value) {
  final date = DateTime.tryParse(value)?.toLocal();
  if (date == null) return value;
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '${date.day}/${date.month}/${date.year} at $hour:$minute';
}
