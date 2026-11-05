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
          uri.pathSegments.any(
            (segment) => segment.toLowerCase() == 'register',
          ));

  static bool isHandledLink(Uri uri) =>
      isRegistrationLink(uri) ||
      (uri.scheme.toLowerCase() == scheme &&
          uri.host.toLowerCase() == 'open') ||
      ApiConfig.isAppWebsiteLink(uri);

  static String? codeFrom(Uri uri) {
    final code =
        (uri.queryParameters['ref'] ??
                uri.queryParameters['referral_code'] ??
                uri.queryParameters['referral'])
            ?.trim();
    return code == null || code.isEmpty ? null : code.toUpperCase();
  }
}
