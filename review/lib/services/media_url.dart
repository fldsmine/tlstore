import '../config/api_config.dart';

/// Resolves API media values to URLs the image widgets can load.
/// Absolute URLs are preserved; relative storage paths use the API host.
class MediaUrl {
  MediaUrl._();

  static String? resolve(String? value) {
    final raw = value?.trim();
    if (raw == null || raw.isEmpty) return null;

    final parsed = Uri.tryParse(raw);
    if (parsed != null && parsed.hasScheme && parsed.host.isNotEmpty) {
      return raw;
    }

    final api = Uri.parse(ApiConfig.baseUrl);
    final origin = Uri(
      scheme: api.scheme,
      host: api.host,
      port: api.hasPort ? api.port : null,
    );
    var path = raw.replaceAll('\\', '/');
    path = path.replaceFirst(RegExp(r'^(\./)?/?storage/'), '');
    path = path.replaceFirst(RegExp(r'^/?storage/'), '');
    path = path.replaceFirst(RegExp(r'^/+'), '');
    /*if (!path.startsWith('images/') &&
        !path.startsWith('app-icons/') &&
        !path.startsWith('ads/') &&
        !path.startsWith('avatars/') &&
        !path.startsWith('covers/')) {
      path = 'images/$path';
    }*/
    return origin.resolve('storage/$path').toString();
  }
}
