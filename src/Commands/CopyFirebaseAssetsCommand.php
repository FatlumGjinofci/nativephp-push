<?php

namespace Lumi\NativePush\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * `copy_assets` lifecycle hook.
 *
 * Places the Firebase config file for the platform being built:
 *
 *   android  google-services.json      -> <build>/app/google-services.json
 *   ios      GoogleService-Info.plist  -> <build>/NativePHP/GoogleService-Info.plist
 *
 * On NativePHP Mobile v4 the manifest's `project_files` declaration already
 * does this (and applies the Google Services Gradle plugin), so this hook is
 * a no-op there in practice. It remains for v3, where the manifest key is
 * ignored, and as the only path that still honours the deprecated 0.1.x
 * location inside this plugin's own resources/ directory.
 *
 * Source resolution order (first match wins):
 *   1. <app root>/<file>                     (documented location, v3 + v4)
 *   2. <app root>/nativephp/resources/<file> (v3 core's own fallback)
 *   3. <plugin>/resources/<file>             (deprecated, 0.1.x)
 */
class CopyFirebaseAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'native-push:copy-assets';

    protected $description = 'Copy the Firebase config file into the native project for fatlum/nativephp-push';

    /** @var array<string, array{0: string, 1: string}> platform => [file, destination relative to build path] */
    private const FILES = [
        'android' => ['google-services.json', 'app/google-services.json'],
        'ios' => ['GoogleService-Info.plist', 'NativePHP/GoogleService-Info.plist'],
    ];

    public function handle(): int
    {
        $platform = $this->platform();

        if (! isset(self::FILES[$platform])) {
            return self::SUCCESS;
        }

        [$file, $relativeDestination] = self::FILES[$platform];
        $destination = $this->buildPath().'/'.$relativeDestination;

        foreach ($this->candidateSources($file) as $source => $deprecated) {
            if (! is_file($source)) {
                continue;
            }

            if ($deprecated) {
                $this->warn("fatlum/nativephp-push: reading {$file} from the plugin's resources/ directory is deprecated "
                    ."and does not work on NativePHP Mobile v4. Move it to your app root: ".base_path($file));
            }

            return $this->copyFile($source, $destination) ? self::SUCCESS : self::FAILURE;
        }

        if (is_file($destination)) {
            // Core (v3) or the manifest's project_files (v4) already placed it.
            return self::SUCCESS;
        }

        $this->warn("fatlum/nativephp-push: {$file} not found. Place it at ".base_path($file)
            ." — {$platform} push will not work until it is added.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool> absolute path => whether the location is deprecated
     */
    private function candidateSources(string $file): array
    {
        return [
            base_path($file) => false,
            base_path('nativephp/resources/'.$file) => false,
            $this->pluginPath().'/resources/'.$file => true,
        ];
    }
}
