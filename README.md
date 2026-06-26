# lumi/nativephp-push

A **free**, MIT-licensed plugin.

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

## License


MIT.
