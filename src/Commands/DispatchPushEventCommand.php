<?php

namespace Lumi\NativePush\Commands;

use Illuminate\Console\Command;

/**
 * Fires a Laravel event from the on-device ephemeral PHP runtime when a data
 * message arrives while the app is backgrounded or killed.
 *
 * The native PushMessagingService (Android) / push observer (iOS) invokes this
 * via the ephemeral runtime, e.g.:
 *
 *   nativeEphemeralArtisan("native:push:dispatch 'App\\Events\\Sync' <base64> --base64")
 *
 * It mirrors core's DispatchEventFromAppController: the decoded payload is spread
 * into the event constructor, so push `data` keys map to named constructor args.
 */
class DispatchPushEventCommand extends Command
{
    protected $signature = 'native:push:dispatch {event : Fully-qualified event class} {payload? : JSON payload} {--base64 : Payload is base64-encoded JSON}';

    protected $description = 'Dispatch a Laravel event from a background push (used by the on-device runtime).';

    public function handle(): int
    {
        $event = $this->argument('event');
        $raw = $this->argument('payload') ?? '[]';

        if ($this->option('base64')) {
            $raw = base64_decode($raw) ?: '[]';
        }

        if (! class_exists($event)) {
            $this->error("Event class does not exist: {$event}");

            return self::FAILURE;
        }

        $allowed = config('push.allowed_events', []);
        if (! empty($allowed) && ! in_array($event, $allowed, true)) {
            $this->error("Event not in push.allowed_events allow-list: {$event}");

            return self::FAILURE;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = [];
        }

        // Spread named args (assoc keys) exactly like core's HTTP dispatch route.
        event(new $event(...$payload));

        return self::SUCCESS;
    }
}
