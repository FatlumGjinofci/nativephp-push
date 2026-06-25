<?php

namespace Lumi\NativePush\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Convenience event for incoming data messages. Core does not ship one, so this
 * gives you a ready-made class to name in your FCM `data.event` key:
 *
 *   "event": "Lumi\\NativePush\\Events\\PushNotificationReceived"
 *
 * The native handler passes the FCM data map (minus the `event` key) as `data`.
 * You can equally send any of your own event classes — whatever FQCN you put in
 * the `event` key is what gets dispatched.
 */
class PushNotificationReceived
{
    use Dispatchable, SerializesModels;

    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data = [],
    ) {}

    /** Decoded `payload` convenience accessor (FCM data values are strings). */
    public function payload(): mixed
    {
        $raw = $this->data['payload'] ?? null;

        return is_string($raw) ? (json_decode($raw, true) ?? $raw) : $raw;
    }
}
