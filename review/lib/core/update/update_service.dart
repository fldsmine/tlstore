import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/auth_api.dart';

const appVersionName = '1.0.0';
const appBuildNumber = 1;

class UpdateInfo {
  const UpdateInfo({
    required this.available,
    required this.force,
    required this.versionName,
    required this.buildNumber,
    required this.minimumSupportedBuild,
    this.title,
    this.notes,
    this.downloadUrl,
    this.publishedAt,
    this.changes = const [],
  });
  final bool available, force;
  final String versionName;
  final int buildNumber, minimumSupportedBuild;
  final String? title, notes, downloadUrl, publishedAt;
  final List<Map<String, dynamic>> changes;
  bool get hasDownloadUrl => downloadUrl?.trim().isNotEmpty == true;

  Map<String, dynamic> toJson() => {
    'available': available,
    'force': force,
    'versionName': versionName,
    'buildNumber': buildNumber,
    'minimumSupportedBuild': minimumSupportedBuild,
    'title': title,
    'notes': notes,
    'downloadUrl': downloadUrl,
    'publishedAt': publishedAt,
    'changes': changes,
  };

  factory UpdateInfo.fromCache(Map<String, dynamic> json) => UpdateInfo(
    available: json['available'] == true,
    force: json['force'] == true,
    versionName: json['versionName']?.toString() ?? '',
    buildNumber: int.tryParse('${json['buildNumber']}') ?? 0,
    minimumSupportedBuild:
        int.tryParse('${json['minimumSupportedBuild']}') ?? 0,
    title: json['title']?.toString(),
    notes: json['notes']?.toString(),
    downloadUrl: json['downloadUrl']?.toString(),
    publishedAt: json['publishedAt']?.toString(),
    changes: json['changes'] is List
        ? (json['changes'] as List).whereType<Map<String, dynamic>>().toList()
        : const [],
  );

  factory UpdateInfo.fromJson(Map<String, dynamic> input) {
    final data = input['data'] is Map<String, dynamic>
        ? input['data'] as Map<String, dynamic>
        : input;
    final latest = data['latest'] is Map<String, dynamic>
        ? data['latest'] as Map<String, dynamic>
        : <String, dynamic>{};
    final history = data['changes'] is List
        ? (data['changes'] as List).whereType<Map<String, dynamic>>().toList()
        : <Map<String, dynamic>>[];
    return UpdateInfo(
      available: data['update_available'] == true,
      force: data['force_update'] == true || latest['force_update'] == true,
      versionName: latest['version_name']?.toString() ?? '',
      buildNumber: int.tryParse('${latest['build_number']}') ?? 0,
      minimumSupportedBuild:
          int.tryParse('${latest['minimum_supported_build']}') ?? 0,
      title: latest['title']?.toString(),
      notes: latest['release_notes']?.toString(),
      downloadUrl:
          data['download_url']?.toString() ??
          latest['app_store_url']?.toString() ??
          latest['play_store_url']?.toString() ??
          latest['direct_download_url']?.toString(),
      publishedAt: latest['published_at']?.toString(),
      changes: history,
    );
  }
}

class UpdateController extends ChangeNotifier {
  UpdateController(this.api);
  static final instance = UpdateController(AuthApi());
  final AuthApi api;
  static const _storage = FlutterSecureStorage();
  static const _lastCheckKey = 'trustlens.update.last_check';
  static const _skipKey = 'trustlens.update.skipped_build';
  static const _forcedKey = 'trustlens.update.forced_build';
  static const _cachedKey = 'trustlens.update.cached';
  UpdateInfo? info;
  bool checking = false;
  bool dismissed = false;
  bool get mustUpdate => info?.available == true && info?.force == true;
  bool get showPrompt => info?.available == true && !mustUpdate && !dismissed;

  Future<UpdateInfo?> check({bool force = true}) async {
    if (checking) return info;
    if (kIsWeb ||
        !(defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS)) {
      return null;
    }
    final forcedBuild =
        int.tryParse(await _storage.read(key: _forcedKey) ?? '0') ?? 0;
    if (forcedBuild > appBuildNumber) {
      final cached = await _storage.read(key: _cachedKey);
      try {
        if (cached != null)
          info = UpdateInfo.fromCache(
            jsonDecode(cached) as Map<String, dynamic>,
          );
      } catch (_) {
        /* Bad cache will be replaced by the server response below. */
      }
      info ??= UpdateInfo(
        available: true,
        force: true,
        versionName: '',
        buildNumber: forcedBuild,
        minimumSupportedBuild: forcedBuild,
      );
      notifyListeners();
    } else if (forcedBuild != 0) {
      await _storage.delete(key: _forcedKey);
      await _storage.delete(key: _cachedKey);
    }
    if (!force) {
      final last =
          int.tryParse(await _storage.read(key: _lastCheckKey) ?? '0') ?? 0;
      if (DateTime.now().millisecondsSinceEpoch - last <
          const Duration(hours: 24).inMilliseconds)
        return info;
    }
    checking = true;
    notifyListeners();
    try {
      final platform = defaultTargetPlatform == TargetPlatform.iOS
          ? 'ios'
          : 'android';
      final response = await api.getPublic(
        'app/version?platform=$platform&build=$appBuildNumber',
      );
      final next = UpdateInfo.fromJson(response);
      info = next.available ? next : null;
      await _storage.write(
        key: _lastCheckKey,
        value: '${DateTime.now().millisecondsSinceEpoch}',
      );
      if (next.available && next.force) {
        await _storage.write(key: _forcedKey, value: '${next.buildNumber}');
        await _storage.write(key: _cachedKey, value: jsonEncode(next.toJson()));
      } else {
        await _storage.delete(key: _forcedKey);
        await _storage.delete(key: _cachedKey);
      }
      final skipped = await _storage.read(key: _skipKey);
      dismissed = !next.force && skipped == '${next.buildNumber}';
      return next;
    } on AuthException catch (error) {
      debugPrint('App update check failed: ${error.message}');
      return null;
    } finally {
      checking = false;
      notifyListeners();
    }
  }

  void later() {
    dismissed = true;
    notifyListeners();
  }

  Future<void> skipThisVersion() async {
    if (info != null)
      await _storage.write(key: _skipKey, value: '${info!.buildNumber}');
    later();
  }

  Future<bool> openDownload() async {
    final raw = info?.downloadUrl;
    final uri = raw == null ? null : Uri.tryParse(raw);
    if (uri == null || !uri.hasScheme) return false;
    return launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
