import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class IntroSeenStore {
  IntroSeenStore._();

  static const _storage = FlutterSecureStorage();
  static const _key = 'trustlens.intro_seen';

  static Future<bool> hasSeen() async => await _storage.read(key: _key) == '1';

  static Future<void> markSeen() => _storage.write(key: _key, value: '1');
}
