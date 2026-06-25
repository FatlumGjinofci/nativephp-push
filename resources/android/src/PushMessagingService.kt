package com.lumi.plugins.push

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import org.json.JSONObject

/**
 * Receives FCM token refreshes and data messages. Data messages naming an
 * `event` class are dispatched to PHP — foreground via the live web view,
 * background/killed via core's ephemeral PHP runtime (see PushDispatch).
 */
class PushMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        PushRuntime.cachedToken = token
        PushDispatch.dispatch(
            PushRuntime.tokenEventClass,
            JSONObject().put("token", token).put("id", PushRuntime.enrollmentId)
        )
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val data = message.data
        val eventClass = data["event"] ?: return  // only data messages naming an event trigger PHP

        val dataJson = JSONObject()
        for ((k, v) in data) {
            if (k != "event") dataJson.put(k, v)
        }
        // Mirror core's named-arg dispatch: event constructor receives `data`.
        PushDispatch.dispatch(eventClass, JSONObject().put("data", dataJson))
    }
}
