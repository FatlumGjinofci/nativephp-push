# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Native bridge functions for `PushNotification.CheckPermission`, `.RequestPermission`, `.GetToken`, `.ClearBadge` (iOS + Android), wired into NativePHP Mobile core.
- Token delivery via core's `TokenGenerated` event.
- Background data-message processing through core's ephemeral PHP runtime (`native:push:dispatch` artisan command).
- Server-side FCM v1 sender (`FcmSender` / `FcmMessage`).
- Optional `PushNotificationReceived` event.
- `push.allowed_events` allow-list for the background dispatch path.

> Status: pre-release. Not yet verified on a physical device build — see README "Verify on a real device".
