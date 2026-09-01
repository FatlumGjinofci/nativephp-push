# NativePHP Mobile v4 support — design

Date: 2026-09-01
Status: approved by default (autonomous session; assumptions listed below)

## Goal

Make `fatlum/nativephp-push` install and build correctly on **NativePHP Mobile v4**
(`nativephp/mobile` ^4.0, currently 4.3.1) while continuing to work on v3.2+.

## What v4 changed that affects this plugin

Verified against the `nativephp/mobile` 4.3.1 source (public on GitHub/Packagist, MIT),
diffed against 3.3.5:

| Area | v3.3.x | v4.3.1 | Impact |
| --- | --- | --- | --- |
| `BridgeFunction` / `BridgeResponse` (Swift + Kotlin) | same | same | none |
| `LaravelBridge.shared.send` (iOS event delivery) | closure, nil until WebView exists | never nil; also feeds Edge element queue | none (works better) |
| `NativeActionCoordinator.dispatchEvent` (Android) | same | same, also feeds Edge element queue | none |
| `PersistentPHPRuntime.shared.artisan` (iOS background) | same | same | none |
| `PHPBridge.nativeEphemeral{Boot,Artisan,Shutdown}` (Android background) | same | same | none |
| `AppDelegate` notification names | same | same | none |
| `PushNotifications` facade / `TokenGenerated` / `RequestPermission` params `{id,event}` | same | same (+ `clearBadge()`) | none |
| `NativePluginHookCommand` helpers | same | same | none |
| **`app/build.gradle.kts` conditional `apply(plugin = "com.google.gms.google-services")`** | present | **removed** | Android push silently broken |
| **Manifest keys `android.project_files`, `ios.project_files`, `android.gradle_plugins`** | ignored | required to own Firebase config + apply the Gradle plugin | must declare |
| `LegacyFirebaseConfig` shim | n/a | copies app-root config for plugins that declare a Firebase pod / gradle plugin, with a deprecation warning | avoid by declaring `project_files` |
| Core PHP requirement | ^8.2 | ^8.4 | plugin stays ^8.2 |
| Default iOS deployment target | 18.0 | 18.2 | none |

Everything in the Swift/Kotlin layer keeps working unchanged. The only real break is Firebase
config delivery on Android.

## Design

### 1. Composer

`"nativephp/mobile": "^3.2|^4.0"`, as the official upgrade guide prescribes for plugin authors.
PHP stays `^8.2` (the plugin itself needs nothing newer; core dictates its own floor).

### 2. Manifest (`nativephp.json`)

Add the v4 declarations. v3 stores unknown keys and never reads them, so they are harmless there.

```json
"android": {
  "project_files": [
    { "sources": ["google-services.json", "nativephp/resources/google-services.json"],
      "destination": "app/google-services.json", "required": true }
  ],
  "gradle_plugins": [
    { "id": "com.google.gms.google-services", "version": "4.4.3", "apply_to": "app" }
  ]
},
"ios": {
  "project_files": [
    { "sources": ["GoogleService-Info.plist", "nativephp/resources/GoogleService-Info.plist"],
      "destination": "NativePHP/GoogleService-Info.plist", "required": true }
  ]
}
```

- Sources are relative to the **app project root**, the location the v3 and v4 docs both
  prescribe. The second source mirrors v3 core's own fallback location.
- `required: true`: without the config Android's Gradle plugin fails the build and iOS crashes
  in `FirebaseApp.configure()`, so a clear compile-time error from core is the honest outcome.
- Destinations match `LegacyFirebaseConfig::DESTINATIONS` exactly, so core's shim sees the
  destination as claimed and stays silent.
- Remove `assets.ios` (plugin-resources plist → `NativePHP/Resources/`). With the app-root plist
  also landing in `NativePHP/`, two same-named plists in the synchronized Xcode group would
  collide.

### 3. `copy_assets` hook (`native-push:copy-assets`)

Keep it, but as a v3 safety net and legacy fallback rather than the primary mechanism:

1. If the destination already exists (v4 `project_files`, or v3 core's own copy), do nothing.
2. Otherwise resolve, in order: app root → `nativephp/resources/` → plugin `resources/`
   (deprecated location from 0.1.x; warn when used).
3. Copy for **both** platforms (Android `app/google-services.json`, iOS
   `NativePHP/GoogleService-Info.plist`).
4. If nothing is found, warn and succeed (v3 behaviour; v4 already failed earlier if required).

### 4. Native code

No changes. Every core symbol used exists with the same signature in 4.3.1.

### 5. Docs

README: Packagist install, v4 registration step (`vendor:publish --tag=nativephp-plugins-provider`),
config files at app root, compatibility table, 0.1.x → 0.2.0 migration note.
CHANGELOG: 0.2.0 entry.

### 6. Tests / CI

- `tests/ManifestTest.php` (PHPUnit): manifest parses; declares the v4 keys above with the exact
  destinations the shim checks; no `assets.ios`; bridge function names unchanged.
- `tests/CopyFirebaseAssetsCommandTest.php` (Testbench): resolution order, skip-if-present, both
  platforms, deprecation warning.
- CI runs `composer install` + PHPUnit on PHP 8.2 and 8.4 (8.4 resolves core v4).
- One-off verification (not committed): run v4's `ProjectFileManager`, `LegacyFirebaseConfig`
  and `buildAppGradlePluginsBlock` against this manifest to prove the shim is bypassed and the
  Gradle plugin is applied.

## Assumptions

- Users move Firebase config files to the app root (the documented location for both versions).
  The plugin-resources location keeps working on v3 only, with a warning.
- `com.google.gms.google-services` 4.4.3 is the pinned Gradle plugin version.
- No new features; this release is compatibility only.
