<?php

namespace Lumi\NativePush\Server;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends pushes from your SERVER via the free FCM v1 API. Handles the OAuth2
 * bearer token from your service-account JSON. Requires google/auth:
 *
 *   composer require google/auth
 */
class FcmSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(
        private ?string $projectId = null,
        private ?string $credentialsPath = null,
    ) {
        $this->projectId ??= config('push.project_id');
        $this->credentialsPath ??= config('push.credentials');
    }

    public function send(FcmMessage $message): array
    {
        if (! class_exists(ServiceAccountCredentials::class)) {
            throw new RuntimeException('google/auth is not installed. Run: composer require google/auth');
        }
        if (empty($this->projectId)) {
            throw new RuntimeException('push.project_id (FCM_PROJECT_ID) is not configured.');
        }
        if (empty($this->credentialsPath) || ! is_file($this->credentialsPath)) {
            throw new RuntimeException('Service-account JSON not found at: ' . ($this->credentialsPath ?? 'null'));
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($endpoint, ['message' => $message->toArray()]);

        if ($response->failed()) {
            throw new RuntimeException('FCM send failed (' . $response->status() . '): ' . $response->body());
        }

        return $response->json();
    }

    public function notify(string $token, string $title, string $body, array $data = []): array
    {
        return $this->send(FcmMessage::make()->to($token)->notification($title, $body)->data($data));
    }

    private function accessToken(): string
    {
        $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath);

        return $credentials->fetchAuthToken()['access_token']
            ?? throw new RuntimeException('Could not fetch FCM access token.');
    }
}
