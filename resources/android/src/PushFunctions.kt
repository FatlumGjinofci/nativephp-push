package com.lumi.plugins.push

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessaging
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.ui.MainActivity
import org.json.JSONObject

object PushFunctions {

    // PushNotification.CheckPermission
    class CheckPermission : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = PushRuntime.appContext
                ?: return BridgeResponse.success(mapOf("status" to "unknown"))

            val status = when {
                Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ->
                    if (NotificationManagerCompat.from(context).areNotificationsEnabled()) "granted" else "denied"
                ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) ==
                    PackageManager.PERMISSION_GRANTED -> "granted"
                else -> "not_determined"
            }
            return BridgeResponse.success(mapOf("status" to status))
        }
    }

    // PushNotification.RequestPermission — core's enroll() passes {id, event}.
    class RequestPermission : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            (parameters["id"] as? String)?.let { PushRuntime.enrollmentId = it }
            (parameters["event"] as? String)?.takeIf { it.isNotEmpty() }?.let { PushRuntime.tokenEventClass = it }

            MainActivity.instance?.let { activity ->
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
                    ContextCompat.checkSelfPermission(activity, Manifest.permission.POST_NOTIFICATIONS) !=
                    PackageManager.PERMISSION_GRANTED
                ) {
                    ActivityCompat.requestPermissions(
                        activity, arrayOf(Manifest.permission.POST_NOTIFICATIONS), 4711
                    )
                }
            }

            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful) task.result?.let { token ->
                    PushRuntime.cachedToken = token
                    PushDispatch.dispatch(
                        PushRuntime.tokenEventClass,
                        JSONObject().put("token", token).put("id", PushRuntime.enrollmentId)
                    )
                }
            }
            return BridgeResponse.success(mapOf("requested" to true))
        }
    }

    // PushNotification.GetToken
    class GetToken : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            PushRuntime.cachedToken?.let { return BridgeResponse.success(mapOf("token" to it)) }

            val latch = java.util.concurrent.CountDownLatch(1)
            var token = ""
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful) token = task.result ?: ""
                latch.countDown()
            }
            latch.await(3, java.util.concurrent.TimeUnit.SECONDS)
            if (token.isNotEmpty()) PushRuntime.cachedToken = token
            return BridgeResponse.success(mapOf("token" to token))
        }
    }

    // PushNotification.ClearBadge
    class ClearBadge : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            PushRuntime.appContext?.let { NotificationManagerCompat.from(it).cancelAll() }
            return BridgeResponse.success(mapOf("cleared" to true))
        }
    }
}
