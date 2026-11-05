import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AppThemeController extends ChangeNotifier {
  AppThemeController._();
  static final instance = AppThemeController._();
  static const _key = 'trustlens.theme_mode';
  static const _storage = FlutterSecureStorage();
  ThemeMode _mode = ThemeMode.light;
  ThemeMode get mode => _mode;

  Future<void> initialize() async {
    try {
      _mode = (await _storage.read(key: _key)) == 'dark'
          ? ThemeMode.dark
          : ThemeMode.light;
      notifyListeners();
    } catch (_) {
      // Keep the default light theme when secure storage is unavailable.
    }
  }

  Future<void> setDark(bool value) async {
    _mode = value ? ThemeMode.dark : ThemeMode.light;
    notifyListeners();
    await _storage.write(key: _key, value: value ? 'dark' : 'light');
  }
}
