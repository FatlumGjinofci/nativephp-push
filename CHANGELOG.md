# Changelog

All notable changes to this project will be documented in this file.

## [0.2.0] - 2026-09-01

### Added
- **NativePHP Mobile v4 support.** `nativephp/mobile` constraint widened to `^3.2|^4.0`.
  All native bridge, event-delivery and ephemeral-runtime APIs this plugin uses are
  unchanged in v4; the only real break was Firebase config delivery on Android, fixed by
  the manifest changes below.
- `nativephp.json` now declares `android.project_files`, `ios.project_files` and
  `android.gradle_plugins` (v4 keys, ignored by v3). Core v4 copies
  `google-services.json` / `GoogleService-Info.plist` from your **app root** into the
  native projects and applies the `com.google.gms.google-services` Gradle plugin to the
  app module itself. This also keeps core's `LegacyFirebaseConfig` deprecation warning
  from firing.
- PHPUnit test suite (`composer test`) covering the manifest declarations and the
  `copy_assets` hook; CI runs it on PHP 8.2 (core v3) and PHP 8.4 (core v4).

### Changed
- **Firebase config files now live in your app root** (`google-services.json` and
  `GoogleService-Info.plist` next to `composer.json`), which is the location the official
  docs prescribe for both v3 and v4. The `copy_assets` hook (`native-push:copy-assets`) now
  handles both platforms and resolves, in order: app root → `nativephp/resources/` →
  the plugin's own `resources/` directory.
- Removed the `assets.ios` manifest mapping that copied the plist from the plugin's
  `resources/` into `NativePHP/Resources/`; with the app-root plist landing in `NativePHP/`
  it would have produced two same-named plists in the Xcode target.

### Deprecated
- Keeping the Firebase config inside the plugin's `resources/` directory (the 0.1.x
  instruction). Still works on v3 with a build-time warning; on v4 the build stops with a
  message telling you where to put the file.

## [0.1.1] - 2026-07-15

### Fixed
- Android `google-services.json` is now placed at the app module root
  (`app/google-services.json`) via a `copy_assets` lifecycle hook
  (`native-push:copy-assets`), where the `com.google.gms.google-services` Gradle
  plugin expects it. The previous `assets` manifest mapping put it under
  `app/src/main/assets/` (the wrong location) and failed `native:plugin:validate`.
  iOS is unaffected.

## [0.1.0] - 2026-06-25

### Added
- Native bridge functions for `PushNotification.CheckPermission`, `.RequestPermission`, `.GetToken`, `.ClearBadge` (iOS + Android), wired into NativePHP Mobile core.
- Token delivery via core's `TokenGenerated` event.
- Background data-message processing through core's ephemeral PHP runtime (`native:push:dispatch` artisan command).
- Server-side FCM v1 sender (`FcmSender` / `FcmMessage`).
- Optional `PushNotificationReceived` event.
- `push.allowed_events` allow-list for the background dispatch path.

> Status: pre-release. Not yet verified on a physical device build — see README "Verify on a real device".
