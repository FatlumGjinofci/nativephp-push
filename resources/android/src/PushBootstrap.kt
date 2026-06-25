package com.lumi.plugins.push

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import com.google.firebase.FirebaseApp

/** Plugin runtime state (set during init / enrollment). */
object PushRuntime {
    const val DEFAULT_TOKEN_EVENT = "Native\\Mobile\\Events\\PushNotification\\TokenGenerated"

    @Volatile var appContext: Context? = null
    @Volatile var enrollmentId: String? = null
    @Volatile var tokenEventClass: String = DEFAULT_TOKEN_EVENT
    @Volatile var cachedToken: String? = null
}

/**
 * nativephp.json -> android.init_function = "com.lumi.plugins.push.PushBootstrap.initialize"
 * Runs once at startup. If the build applied the google-services plugin,
 * initializeApp(context) succeeds with no extra config.
 */
object PushBootstrap {
    @JvmStatic
    fun initialize(context: Context) {
        PushRuntime.appContext = context.applicationContext

        try {
            if (FirebaseApp.getApps(context).isEmpty()) {
                FirebaseApp.initializeApp(context)
            }
        } catch (e: Exception) {
            android.util.Log.e("NativePush", "Firebase init failed: ${e.message}")
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = context.getSystemService(NotificationManager::class.java)
            manager?.createNotificationChannel(
                NotificationChannel("default", "General", NotificationManager.IMPORTANCE_DEFAULT)
            )
        }
    }
}
