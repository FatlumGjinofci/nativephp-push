<?php

namespace Lumi\NativePush\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * `copy_assets` lifecycle hook.
 *
 * Places google-services.json at the Android app module root
 * (app/google-services.json), which is where the `com.google.gms.google-services`
 * Gradle plugin expects it. The generated NativePHP Android project already
 * applies that plugin conditionally:
 *
 *     val googleServicesJson = file("google-services.json")
 *     if (googleServicesJson.exists()) { apply(plugin = "com.google.gms.google-services") }
 *
 * The manifest "assets" map can only target app/src/main/assets|res, never the
 * module root, so this hook is required. iOS needs nothing here — its
 * GoogleService-Info.plist is handled by the manifest "assets" map.
 */
class CopyFirebaseAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'native-push:copy-assets';

    protected $description = 'Copy google-services.json to the Android app module root for lumi/nativephp-push';

    public function handle(): int
    {
        if (! $this->isAndroid()) {
            return self::SUCCESS;
        }

        $source = $this->pluginPath().'/resources/google-services.json';
        $dest = $this->buildPath().'/app/google-services.json';

        if (! file_exists($source)) {
            $this->warn('lumi/nativephp-push: google-services.json not found in plugin resources; '
                .'skipping Android Firebase config. Android push will not work until it is added.');

            return self::SUCCESS;
        }

        return $this->copyFile($source, $dest) ? self::SUCCESS : self::FAILURE;
    }
}
