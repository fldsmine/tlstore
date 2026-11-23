# Push notifications

The app uses Firebase Cloud Messaging (FCM) for remote delivery. The Laravel API records each notification in the inbox, then sends it to the user's active device tokens. Delivery attempts are recorded in `notification_deliveries`.

## Firebase project setup

1. Create or select a Firebase project and register Android app `pynith.tlstore.review` and iOS app `pynith.tlstore.review`.
2. Enable Firebase Cloud Messaging API (V1) for the project.
3. For iOS delivery, upload an Apple Push Notifications authentication key to Firebase, enable Push Notifications and Background Modes in the iOS Runner target, and use a provisioning profile with the Push Notifications entitlement.
4. Generate a Firebase service-account key for the server and store it outside the web root. Grant its service account permission to send FCM messages. Never commit this private key.

## Laravel server configuration

Set these values in the server `.env`:

```dotenv
FCM_ENABLED=true
FCM_PROJECT_ID=your-firebase-project-id
FCM_SERVICE_ACCOUNT_PATH=C:\\secure\\firebase-service-account.json
```

The project ID may be omitted when it is present in the service-account file. After changing environment values on a deployed server, refresh Laravel's config cache.

## Flutter client configuration

Pass the Firebase app options as Dart defines when running or building the Flutter app. These identify the client apps; they are not the service-account credentials.

```powershell
flutter run -d RF8NB1PBB7W `
  --dart-define=API_BASE_URL=https://gptminpro.online/api/v1/ `
  --dart-define=FIREBASE_PROJECT_ID=your-firebase-project-id `
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=your-sender-id `
  --dart-define=FIREBASE_ANDROID_API_KEY=your-android-api-key `
  --dart-define=FIREBASE_ANDROID_APP_ID=your-android-app-id `
  --dart-define=FIREBASE_IOS_API_KEY=your-ios-api-key `
  --dart-define=FIREBASE_IOS_APP_ID=your-ios-app-id
```

Add `FIREBASE_STORAGE_BUCKET` if it is present in the Firebase app options. The app asks for notification permission after sign-in, registers and refreshes FCM tokens through `/api/v1/devices`, displays foreground messages locally, and opens the in-app notification inbox when a notification is tapped. Logout revokes the registered device token.

## Events that send notifications

Admin decisions for app reviews, ad shares, manual deposits, and withdrawals create inbox notifications. The notification observer sends each new notification after its database transaction commits. Failed and successful delivery attempts are recorded per device.
