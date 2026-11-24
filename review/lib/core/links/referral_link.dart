import 'package:share_plus/share_plus.dart';

import '../../config/api_config.dart';

class ReferralLink {
  ReferralLink._();

  static const scheme = 'tlstore';
  static const registrationHost = 'register';

  static Uri forCode(String code) => Uri(
    scheme: scheme,
    host: registrationHost,
    queryParameters: {'ref': code.trim().toUpperCase()},
  );

  static bool isRegistrationLink(Uri uri) =>
      (uri.scheme.toLowerCase() == scheme &&
          uri.host.toLowerCase() == registrationHost) ||
      (ApiConfig.isAppWebsiteLink(uri) &&
          (uri.pathSegments.any(
                (segment) => segment.toLowerCase() == 'register',
              ) ||
              (uri.pathSegments.length >= 2 &&
                  uri.pathSegments.first.toLowerCase() == 'ref')));

  static bool isHandledLink(Uri uri) =>
      isRegistrationLink(uri) ||
      (uri.scheme.toLowerCase() == scheme &&
          uri.host.toLowerCase() == 'open') ||
      ApiConfig.isAppWebsiteLink(uri);

  static String? codeFrom(Uri uri) {
    final queryCode =
        (uri.queryParameters['ref'] ??
                uri.queryParameters['referral_code'] ??
                uri.queryParameters['referral'])
            ?.trim();
    final pathCode =
        uri.pathSegments.length >= 2 &&
            uri.pathSegments.first.toLowerCase() == 'ref'
        ? uri.pathSegments[1]
        : null;
    final code = queryCode ?? pathCode;
    return code == null || code.isEmpty ? null : code.toUpperCase();
  }

  /// Share referral link
  Future<void> shareReferralLink({required String referralCode}) async {
    await SharePlus.instance.share(
      ShareParams(
        text: shareMessage(referralCode),
        subject: 'Join Trust Lens Store',
      ),
    );
  }

  static String referralWebLink(String referralCode) {
    final base = Uri.parse(ApiConfig.websiteBaseUrl);
    final segments = <String>[
      ...base.pathSegments.where((segment) => segment.isNotEmpty),
      'ref',
      referralCode.trim().toUpperCase(),
    ];
    return base.replace(pathSegments: segments).toString();
  }

  /// Get shareable message
  static String shareMessage(String referralCode) {
    final code = referralCode.trim().toUpperCase();
    final link = referralWebLink(code);
    final appLink = forCode(code);

    return 'Join me on Trust Lens Store!\n\n'
        '📝 Review apps, 🚀 share sponsored tasks, and 💵 earn rewards.\n\n'
        '👥 Sign up with my referral code: $code\n'
        '🔗 Use my link to get started: $link\n'
        '📱 In-App Link: $appLink';
  }
}
