/// Compile-time switch for the in-app admin tooling.
///
/// The reviewer-facing app must never ship the admin navigation: admin work
/// belongs to the server-rendered console at `/admin` (or to a dedicated admin
/// build). Reviewer builds therefore compile the ADMIN PAGES section out by
/// default and only an explicit admin build opts back in:
///
/// ```bash
/// flutter build apk --dart-define=ENABLE_ADMIN_UI=true
/// ```
const bool kEnableAdminUi = bool.fromEnvironment(
  'ENABLE_ADMIN_UI',
  defaultValue: false,
);
