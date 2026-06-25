# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
