<?php

namespace Lumi\NativePush\Tests;

use Illuminate\Filesystem\Filesystem;

class CopyFirebaseAssetsCommandTest extends TestCase
{
    private string $root;

    private string $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/native-push-'.uniqid();
        $this->plugin = $this->root.'/vendor/fatlum/nativephp-push';

        (new Filesystem)->ensureDirectoryExists($this->root.'/nativephp/android');
        (new Filesystem)->ensureDirectoryExists($this->root.'/nativephp/ios');
        (new Filesystem)->ensureDirectoryExists($this->plugin.'/resources');

        $this->app->setBasePath($this->root);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function runHook(string $platform)
    {
        return $this->artisan('native-push:copy-assets', [
            '--platform' => $platform,
            '--build-path' => $this->root.'/nativephp/'.$platform,
            '--plugin-path' => $this->plugin,
        ]);
    }

    public function test_android_copies_google_services_json_from_app_root(): void
    {
        file_put_contents($this->root.'/google-services.json', '{"root":true}');

        $this->runHook('android')->assertSuccessful();

        $this->assertStringEqualsFile($this->root.'/nativephp/android/app/google-services.json', '{"root":true}');
    }

    public function test_ios_copies_plist_from_app_root(): void
    {
        file_put_contents($this->root.'/GoogleService-Info.plist', '<plist/>');

        $this->runHook('ios')->assertSuccessful();

        $this->assertStringEqualsFile($this->root.'/nativephp/ios/NativePHP/GoogleService-Info.plist', '<plist/>');
    }

    public function test_falls_back_to_nativephp_resources_directory(): void
    {
        (new Filesystem)->ensureDirectoryExists($this->root.'/nativephp/resources');
        file_put_contents($this->root.'/nativephp/resources/google-services.json', '{"nativephp":true}');

        $this->runHook('android')->assertSuccessful();

        $this->assertStringEqualsFile($this->root.'/nativephp/android/app/google-services.json', '{"nativephp":true}');
    }

    public function test_falls_back_to_plugin_resources_with_deprecation_warning(): void
    {
        file_put_contents($this->plugin.'/resources/google-services.json', '{"plugin":true}');

        $this->runHook('android')
            ->expectsOutputToContain('deprecated')
            ->assertSuccessful();

        $this->assertStringEqualsFile($this->root.'/nativephp/android/app/google-services.json', '{"plugin":true}');
    }

    public function test_app_root_wins_over_plugin_resources(): void
    {
        file_put_contents($this->root.'/google-services.json', '{"root":true}');
        file_put_contents($this->plugin.'/resources/google-services.json', '{"plugin":true}');

        $this->runHook('android')
            ->doesntExpectOutputToContain('deprecated')
            ->assertSuccessful();

        $this->assertStringEqualsFile($this->root.'/nativephp/android/app/google-services.json', '{"root":true}');
    }

    public function test_no_source_but_destination_already_present_is_silent(): void
    {
        (new Filesystem)->ensureDirectoryExists($this->root.'/nativephp/android/app');
        file_put_contents($this->root.'/nativephp/android/app/google-services.json', '{"core":true}');

        $this->runHook('android')
            ->doesntExpectOutputToContain('not found')
            ->assertSuccessful();

        $this->assertStringEqualsFile($this->root.'/nativephp/android/app/google-services.json', '{"core":true}');
    }

    public function test_no_source_anywhere_warns_but_succeeds(): void
    {
        $this->runHook('android')
            ->expectsOutputToContain('google-services.json not found')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->root.'/nativephp/android/app/google-services.json');
    }

    public function test_unknown_platform_is_a_noop(): void
    {
        $this->runHook('web')->assertSuccessful();
    }
}
