package com.lumi.plugins.push

import android.util.Base64
import com.nativephp.mobile.bridge.PHPBridge
import com.nativephp.mobile.ui.MainActivity
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject

/**
 * Sends an event to PHP. While the app is alive (MainActivity.instance != null)
 * it goes through the live web view so mounted Livewire components react. When
 * the app is backgrounded/killed it runs core's ephemeral PHP runtime and fires
 * the event via the `native:push:dispatch` artisan command.
 */
object PushDispatch {

    fun dispatch(eventClass: String, payload: JSONObject) {
        val activity = MainActivity.instance
        if (activity != null) {
            // Foreground: reuse core's coordinator (handles main-thread + JS injection).
            NativeActionCoordinator.dispatchEvent(activity, eventClass, payload.toString())
        } else {
            dispatchInBackground(eventClass, payload)
        }
    }

    private fun dispatchInBackground(eventClass: String, payload: JSONObject) {
        val context = PushRuntime.appContext ?: return
        val bridge = PHPBridge(context)

        val b64 = Base64.encodeToString(
            payload.toString().toByteArray(Charsets.UTF_8),
            Base64.NO_WRAP
        )
        // Symfony StringInput keeps single-quoted args literal, preserving backslashes.
        val command = "native:push:dispatch '$eventClass' '$b64' --base64"

        try {
            bridge.nativeRuntimeInit()
            val booted = bridge.nativeEphemeralBoot(
                "${bridge.getLaravelPath()}/vendor/nativephp/mobile/bootstrap/android/persistent.php"
            )
            if (booted == 0) {
                bridge.nativeEphemeralArtisan(command)
            } else {
                android.util.Log.e("NativePush", "Ephemeral boot failed (code=$booted)")
            }
        } catch (e: Throwable) {
            android.util.Log.e("NativePush", "Background dispatch failed: ${e.message}")
        } finally {
            try { bridge.nativeEphemeralShutdown() } catch (_: Throwable) {}
        }
    }
}
