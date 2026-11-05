import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../config/firebase_options.dart';
import 'auth_api.dart';

const _updatesChannel = AndroidNotificationChannel(
  'trustlens_updates',
  'TrustLens updates',
  description: 'Account, task, and wallet updates',
  importance: Importance.high,
);

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
}

class PushNotificationService {
  PushNotificationService._();

  static final instance = PushNotificationService._();
  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();
  final StreamController<void> _opened = StreamController<void>.broadcast();

  FirebaseMessaging? _messaging;
  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<RemoteMessage>? _openedSubscription;
  StreamSubscription<String>? _tokenSubscription;
  AuthApi? _api;
  String? _authToken;
  String? _fcmToken;
  int? _deviceId;
  bool _pendingOpen = false;
  bool _initialized = false;

  Stream<void> get notificationOpened => _opened.stream;

  bool consumePendingOpen() {
    final pending = _pendingOpen;
    _pendingOpen = false;
    return pending;
  }

  Future<void> initialize() async {
    if (_initialized) return;
    if (kIsWeb && !DefaultFirebaseOptions.hasWebConfiguration) return;
    final options = DefaultFirebaseOptions.currentPlatform;

    try {
      await Firebase.initializeApp(options: options);
      if (!kIsWeb) {
        FirebaseMessaging.onBackgroundMessage(
          firebaseMessagingBackgroundHandler,
        );
      }
      _messaging = FirebaseMessaging.instance;
      if (!kIsWeb) {
        await _local.initialize(
          const InitializationSettings(
            android: AndroidInitializationSettings('ic_notification'),
            iOS: DarwinInitializationSettings(
              requestAlertPermission: false,
              requestBadgePermission: false,
              requestSoundPermission: false,
            ),
          ),
          onDidReceiveNotificationResponse: (response) =>
              _handleLocalTap(response.payload),
        );
        await _local
            .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin
            >()
            ?.createNotificationChannel(_updatesChannel);
        await _messaging!.setForegroundNotificationPresentationOptions(
          alert: false,
          badge: true,
          sound: false,
        );
      }
      _foregroundSubscription ??= FirebaseMessaging.onMessage.listen(
        _showForeground,
      );
      _openedSubscription ??= FirebaseMessaging.onMessageOpenedApp.listen(
        (_) => _markOpened(),
      );
      final initialMessage = await _messaging!.getInitialMessage();
      if (initialMessage != null) _markOpened();
      _initialized = true;
      final api = _api;
      final authToken = _authToken;
      if (api != null && authToken != null) {
        unawaited(connect(api, authToken));
      }
    } catch (error) {
      debugPrint('Push notification initialization failed: $error');
    }
  }

  Future<void> connect(
    AuthApi api,
    String authToken, {
    bool requestWebPermission = false,
  }) async {
    _api = api;
    _authToken = authToken;
    final messaging = _messaging;
    if (!_initialized || messaging == null) return;
    if (kIsWeb && DefaultFirebaseOptions.webVapidKey.isEmpty) return;
    if (kIsWeb && requestWebPermission) _fcmToken = null;

    try {
      final permission = kIsWeb && !requestWebPermission
          ? await messaging.getNotificationSettings()
          : await messaging.requestPermission(
              alert: true,
              badge: true,
              sound: true,
            );
      if (permission.authorizationStatus != AuthorizationStatus.authorized &&
          permission.authorizationStatus != AuthorizationStatus.provisional) {
        return;
      }

      if (defaultTargetPlatform == TargetPlatform.iOS) {
        String? apnsToken;
        for (var attempt = 0; attempt < 12 && apnsToken == null; attempt++) {
          apnsToken = await messaging.getAPNSToken();
          if (apnsToken == null) {
            await Future<void>.delayed(const Duration(milliseconds: 500));
          }
        }
        if (apnsToken == null) return;
      }

      // The Laravel FCM sender expects a registration token, not an FID.
      // ignore: deprecated_member_use
      _fcmToken = await messaging.getToken(
        vapidKey: kIsWeb ? DefaultFirebaseOptions.webVapidKey : null,
        serviceWorkerScriptPath: kIsWeb ? '/firebase-messaging-sw.js' : null,
      );
      if (_fcmToken != null) await _registerToken(_fcmToken!);
      // ignore: deprecated_member_use
      _tokenSubscription ??= messaging.onTokenRefresh.listen((token) {
        _fcmToken = token;
        unawaited(_registerTokenSafely(token));
      });
    } catch (error) {
      debugPrint('Push token registration failed: $error');
    }
  }

  Future<bool> enableFromUserGesture(AuthApi api, String authToken) async {
    if (!kIsWeb) {
      await connect(api, authToken);
      return true;
    }
    await connect(api, authToken, requestWebPermission: true);
    return _fcmToken?.isNotEmpty == true;
  }

  Future<void> disconnect(AuthApi api, String authToken) async {
    final deviceId = _deviceId;
    if (deviceId != null) {
      try {
        await api.delete('devices/$deviceId', authToken);
      } on AuthException catch (error) {
        debugPrint('Push device revocation failed: ${error.message}');
      }
    }
    _api = null;
    _authToken = null;
    _deviceId = null;
  }

  Future<void> _registerToken(String token) async {
    final api = _api;
    final authToken = _authToken;
    if (api == null || authToken == null || token.isEmpty) return;
    final response = await api.post('devices', authToken, {
      'platform': kIsWeb
          ? 'web'
          : defaultTargetPlatform == TargetPlatform.iOS
          ? 'ios'
          : 'android',
      'push_token': token,
    });
    final data = response['data'];
    if (data is Map<String, dynamic>) {
      final value = data['id'];
      final nextId = value is num
          ? value.toInt()
          : int.tryParse(value?.toString() ?? '');
      final oldId = _deviceId;
      _deviceId = nextId;
      if (oldId != null && nextId != null && oldId != nextId) {
        await api.delete('devices/$oldId', authToken);
      }
    }
  }

  Future<void> _registerTokenSafely(String token) async {
    try {
      await _registerToken(token);
    } catch (error) {
      debugPrint('Refreshed push token registration failed: $error');
    }
  }

  Future<void> _showForeground(RemoteMessage message) async {
    if (kIsWeb) return;
    final notification = message.notification;
    if (notification == null) return;
    try {
      await _local.show(
        message.messageId?.hashCode ??
            DateTime.now().millisecondsSinceEpoch & 0x7fffffff,
        notification.title ?? 'TrustLens update',
        notification.body ?? '',
        const NotificationDetails(
          android: AndroidNotificationDetails(
            'trustlens_updates',
            'TrustLens updates',
            channelDescription: 'Account, task, and wallet updates',
            importance: Importance.high,
            priority: Priority.high,
            icon: 'ic_launcher',
          ),
          iOS: DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
          ),
        ),
        payload: jsonEncode(message.data),
      );
    } catch (error) {
      debugPrint('Could not display a foreground push notification: $error');
    }
  }

  void _handleLocalTap(String? payload) {
    if (payload != null && payload.isNotEmpty) {
      try {
        jsonDecode(payload);
      } on FormatException {
        // The tap still opens the notification inbox if optional payload data is malformed.
      }
    }
    _markOpened();
  }

  void _markOpened() {
    _pendingOpen = true;
    _opened.add(null);
  }
}
