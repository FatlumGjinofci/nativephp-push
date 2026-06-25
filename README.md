# lumi/nativephp-push

A **free**, MIT-licensed replacement for the paid `nativephp/mobile-firebase` plugin.

It implements only the part that actually costs money — the **native Swift/Kotlin layer** — and
wires it into the push API that already ships in NativePHP Mobile's open-source core. Firebase
Cloud Messaging and APNs are free; this is the glue.

## How it fits together

The PHP API, the `TokenGenerated` event, and the on-device event-dispatch route all live in core
(`nativephp/mobile`, MIT) already. This plugin supplies the native implementations core calls:

| Layer | Where it lives |
| --- | --- |
| `PushNotifications::enroll() / checkPermission() / getToken()` | **core** (`Native\Mobile\Facades\PushNotifications`) |
| `TokenGenerated` event, `POST /_native/api/events` route | **core** |
| Ephemeral PHP runtime for background execution | **core** (v3.2+) |
| Native bridge functions `PushNotification.*`, Firebase SDK, FCM service | **this plugin** |
| Server-side FCM v1 sender | **this plugin** |

> **Do not install this alongside `nativephp/mobile-firebase`.** Both register the same
> `PushNotification.*` bridge functions — pick one.

## What's implemented

- Permission flow + token delivery (`TokenGenerated` fires with `token` + enrollment `id`)
- **Background data-message processing** — when the app is backgrounded or killed, the FCM service
  boots core's ephemeral PHP runtime and dispatches your event via the `native:push:dispatch`
  artisan command. Foreground messages go through the live web view so mounted Livewire components
  react.
- Deep-link / data handling, badge clearing
- Free server-side sending via the FCM v1 API

---

## Install

```json
// app composer.json
{ "repositories": [ { "type": "path", "url": "../packages/nativephp-push" } ] }
```

```bash
composer require lumi/nativephp-push
php artisan native:plugin:register lumi/nativephp-push
php artisan vendor:publish --tag=native-push-config   # optional
```

Requires `nativephp/mobile` **^3.2** (for the ephemeral runtime).

## Firebase setup

1. Create a Firebase project (free).
2. **iOS:** add an iOS app, download `GoogleService-Info.plist`, place it at
   `resources/GoogleService-Info.plist` in this plugin. Upload your APNs key under
   Firebase Console → Cloud Messaging.
3. **Android:** add an Android app, download `google-services.json`, place it at
   `resources/google-services.json` in this plugin.
4. **Server:** Project Settings → Service Accounts → *Generate new private key*.

```dotenv
APS_ENVIRONMENT=production          # 'development' for local device builds
FCM_PROJECT_ID=your-project-id      # server sending
FIREBASE_CREDENTIALS=/abs/path/service-account.json
```

---

## Usage (PHP / Livewire) — core's API

```php
use Native\Mobile\Facades\PushNotifications;
use Native\Mobile\Events\PushNotification\TokenGenerated;

// Enroll (prompts if needed). Token arrives via TokenGenerated.
PushNotifications::enroll();

$status = PushNotifications::checkPermission(); // granted|denied|not_determined|provisional|ephemeral

#[\Native\Mobile\Attributes\OnNative(TokenGenerated::class)]
public function handleToken(string $token)
{
    auth()->user()->update(['push_token' => $token]);
}
```

### Background processing

Send a data message naming any event class. It runs even when the app is backgrounded/killed:

```php
// A normal Laravel listener (service provider boot) — runs in the ephemeral runtime.
Event::listen(function (\Lumi\NativePush\Events\PushNotificationReceived $event) {
    // $event->data — persist, queue work, update local SQLite, etc.
});
```

> **Event constructor convention:** the native handler passes the FCM `data` map (minus the
> `event` key) as a single `array $data` argument. Design your push event classes as
> `__construct(array $data)` (the bundled `PushNotificationReceived` already does).

## Sending from your server (free)

```bash
composer require google/auth   # sending machine only
```

```php
use Lumi\NativePush\Server\{FcmSender, FcmMessage};

$sender = new FcmSender();

// Tray notification (no PHP on device):
$sender->notify($token, 'Order shipped', 'On its way!', ['url' => '/orders/123']);

// Background event:
$sender->send(
    FcmMessage::make()->to($token)
        ->event(\Lumi\NativePush\Events\PushNotificationReceived::class, ['sync_id' => 42])
);
```

---

## Verify on a real device

Built from the core source (`nativephp/mobile-air`), but compile and confirm:

```bash
php artisan native:plugin:validate
php artisan native:install --force
php artisan native:plugin:install-agent   # free Swift/Kotlin agents for any fixes
php artisan native:run
```

Most-likely-to-need-a-tweak spots:

1. **Manifest service schema** — the `android.services[].intent-filters` entry for
   `com.google.firebase.MESSAGING_EVENT`; confirm the validator accepts it.
2. **Android Firebase init** — relies on the build applying the google-services plugin so
   `FirebaseApp.initializeApp(context)` finds config. If not, build `FirebaseOptions` manually.
3. **Ephemeral boot** — `PushDispatch.dispatchInBackground` calls core's
   `nativeEphemeralBoot/Artisan/Shutdown`. Confirm the bootstrap path and that a fresh `PHPBridge`
   in the FCM service can boot without the main runtime (it's a separate TSRM context by design).
4. **iOS APNs entitlement** — `aps-environment` must match the build (`development` vs `production`).
5. **iOS background runtime** — `PushObserver` uses `PersistentPHPRuntime.shared.artisan(...)` when
   not active; a silent push (`content-available: 1`, set automatically by `FcmMessage::event()`)
   must wake the app for this to run.

## Publishing to GitHub

Before you push, rename the `lumi` / `Lumi\NativePush` / `com.lumi.plugins.push` identifiers to
your own vendor, fill in the `LICENSE` copyright holder and the `authors`/`support` URLs in
`composer.json`, then:

```bash
git init && git add . && git commit -m "Initial commit"
git branch -M main
git remote add origin git@github.com:your-vendor/nativephp-push.git
git push -u origin main
```

To let others `composer require` it: either submit it to [Packagist](https://packagist.org), or
have them add a VCS repository to their app's `composer.json`:

```json
{ "repositories": [ { "type": "vcs", "url": "https://github.com/your-vendor/nativephp-push" } ] }
```

Tag releases (`git tag v0.1.0 && git push --tags`) so Composer can resolve versions.

### Distribution caveat: Firebase config files

The manifest copies `resources/GoogleService-Info.plist` and `resources/google-services.json` into
the build, but **those files are each developer's own Firebase config and are git-ignored** — they
are never committed. That's fine when you consume the plugin as a local `path` repo (you drop your
files into `resources/`). But when it's installed via Composer into `vendor/`, editing files inside
`vendor/` is wrong.

For a truly shared package, provide the Firebase config from the **app** instead of the package —
e.g. publish the files into the app and point the manifest `assets` source at an app path, or
initialise Firebase programmatically from `${ENV}` secrets (Android `FirebaseOptions`, iOS
`FirebaseApp.configure(options:)`) so no per-developer files live in the package at all. Pick one
before you promote this beyond a path/fork install.

## License


MIT.
