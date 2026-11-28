import 'dart:html' as html;

String? impersonationCodeFromUrl() {
  final code = Uri.base.queryParameters['impersonation']?.trim();
  return code == null || code.isEmpty ? null : code;
}

void clearImpersonationCode() {
  final uri = Uri.base;
  if (!uri.queryParameters.containsKey('impersonation')) return;
  final params = Map<String, String>.from(uri.queryParameters)
    ..remove('impersonation');
  final query = params.isEmpty
      ? ''
      : '?${Uri(queryParameters: params).query}';
  final fragment = uri.fragment.isEmpty ? '' : '#${uri.fragment}';
  html.window.history.replaceState(
    null,
    '',
    '${uri.origin}${uri.path}$query$fragment',
  );
}
