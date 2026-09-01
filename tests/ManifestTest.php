<?php

namespace Lumi\NativePush\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the nativephp.json declarations that NativePHP Mobile v4 relies on.
 *
 * Destinations must match core's LegacyFirebaseConfig::DESTINATIONS exactly,
 * otherwise core does not see the Firebase config as "claimed" by this plugin
 * and falls back to its deprecated shim.
 */
class ManifestTest extends TestCase
{
    private array $manifest;

    protected function setUp(): void
    {
        $raw = file_get_contents(__DIR__.'/../nativephp.json');
        $this->manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_android_declares_google_services_project_file(): void
    {
        $files = $this->manifest['android']['project_files'] ?? [];
        $this->assertCount(1, $files);

        $this->assertSame('app/google-services.json', $files[0]['destination']);
        $this->assertSame('google-services.json', $files[0]['sources'][0]);
        $this->assertContains('nativephp/resources/google-services.json', $files[0]['sources']);
        $this->assertTrue($files[0]['required']);
    }

    public function test_android_applies_google_services_gradle_plugin_to_app_module(): void
    {
        $plugins = $this->manifest['android']['gradle_plugins'] ?? [];
        $this->assertCount(1, $plugins);

        $this->assertSame('com.google.gms.google-services', $plugins[0]['id']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $plugins[0]['version']);
        $this->assertSame('app', $plugins[0]['apply_to']);
    }

    public function test_ios_declares_google_service_info_project_file(): void
    {
        $files = $this->manifest['ios']['project_files'] ?? [];
        $this->assertCount(1, $files);

        $this->assertSame('NativePHP/GoogleService-Info.plist', $files[0]['destination']);
        $this->assertSame('GoogleService-Info.plist', $files[0]['sources'][0]);
        $this->assertContains('nativephp/resources/GoogleService-Info.plist', $files[0]['sources']);
        $this->assertTrue($files[0]['required']);
    }

    public function test_ios_plist_is_no_longer_copied_from_plugin_resources_via_assets(): void
    {
        // Two same-named plists under NativePHP/ collide in the synchronized Xcode group.
        $this->assertArrayNotHasKey('ios', $this->manifest['assets'] ?? []);
    }

    public function test_copy_assets_hook_is_still_registered(): void
    {
        $this->assertSame('native-push:copy-assets', $this->manifest['hooks']['copy_assets'] ?? null);
    }

    public function test_bridge_function_names_match_core_push_api(): void
    {
        $names = array_column($this->manifest['bridge_functions'], 'name');

        $this->assertSame([
            'PushNotification.CheckPermission',
            'PushNotification.RequestPermission',
            'PushNotification.GetToken',
            'PushNotification.ClearBadge',
        ], $names);
    }
}
