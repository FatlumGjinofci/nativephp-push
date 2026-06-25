<?php

namespace Lumi\NativePush\Server;

/**
 * Fluent builder for an FCM HTTP v1 `message` object.
 *
 * notification() = OS draws it in the tray, no PHP runs on device.
 * event()        = data message that fires a Laravel event on device
 *                  (foreground always; background/killed via the ephemeral runtime).
 */
class FcmMessage
{
    protected ?string $token = null;
    protected ?string $title = null;
    protected ?string $body = null;
    protected array $data = [];
    protected ?int $badge = null;
    protected bool $contentAvailable = false;

    public static function make(): self
    {
        return new self();
    }

    public function to(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function notification(string $title, string $body): self
    {
        $this->title = $title;
        $this->body = $body;

        return $this;
    }

    /** @param array<string, scalar> $data */
    public function data(array $data): self
    {
        foreach ($data as $k => $v) {
            $this->data[$k] = is_string($v) ? $v : (string) json_encode($v);
        }

        return $this;
    }

    public function url(string $url): self
    {
        $this->data['url'] = $url;

        return $this;
    }

    /**
     * Trigger a Laravel event on the device. The data map (minus `event`) is
     * passed to your event constructor; include any extra keys you need.
     */
    public function event(string $eventClass, array $extraData = []): self
    {
        $this->data['event'] = $eventClass;
        foreach ($extraData as $k => $v) {
            $this->data[$k] = is_string($v) ? $v : (string) json_encode($v);
        }
        $this->contentAvailable = true; // wakes iOS for silent delivery

        return $this;
    }

    public function badge(int $count): self
    {
        $this->badge = $count;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $message = ['token' => $this->token];

        if ($this->title !== null) {
            $message['notification'] = ['title' => $this->title, 'body' => $this->body];
        }

        if ($this->data !== []) {
            $message['data'] = $this->data;
        }

        $aps = [];
        if ($this->contentAvailable) {
            $aps['content-available'] = 1;
        }
        if ($this->badge !== null) {
            $aps['badge'] = $this->badge;
        }
        if ($aps !== []) {
            $message['apns'] = ['payload' => ['aps' => $aps]];
        }

        // Data-only messages need high priority to wake a backgrounded device.
        if ($this->title === null && $this->data !== []) {
            $message['android'] = ['priority' => 'high'];
        }

        return $message;
    }
}
