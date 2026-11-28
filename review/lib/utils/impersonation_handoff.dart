// Admin impersonation hand-off.
//
// The admin console opens the Flutter web app with `?impersonation=<code>`.
// Only the web build can read and clean the address bar, so the platform
// specific implementations live behind a conditional import.

import 'impersonation_handoff_stub.dart'
    if (dart.library.html) 'impersonation_handoff_web.dart'
    as handoff;

/// The one-time impersonation code the app was opened with, if any.
String? impersonationCodeFromUrl() => handoff.impersonationCodeFromUrl();

/// Remove the code from the address bar after it has been redeemed.
void clearImpersonationCode() => handoff.clearImpersonationCode();
