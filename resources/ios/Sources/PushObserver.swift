import Foundation
import UIKit
import UserNotifications
import FirebaseMessaging

/// Bridges APNs/FCM into core. Holds the enrollment id + event class set by
/// PushNotification.RequestPermission.
final class PushObserver: NSObject, MessagingDelegate, UNUserNotificationCenterDelegate {

    static let shared = PushObserver()

    private static let defaultTokenEvent = "Native\\Mobile\\Events\\PushNotification\\TokenGenerated"

    var enrollmentId: String?
    var tokenEventClass: String = PushObserver.defaultTokenEvent
    private(set) var cachedToken: String?

    // MARK: APNs token (from core's NotificationCenter broadcast)

    @objc func onApnsToken(_ note: Notification) {
        guard let deviceToken = note.userInfo?["deviceToken"] as? Data else { return }
        // Hand the APNs token to Firebase so it can mint an FCM token.
        Messaging.messaging().apnsToken = deviceToken
    }

    // MARK: FCM token

    func messaging(_ messaging: Messaging, didReceiveRegistrationToken fcmToken: String?) {
        guard let token = fcmToken else { return }
        cachedToken = token
        dispatchToPHP(tokenEventClass, payload: ["token": token, "id": enrollmentId ?? NSNull()])
    }

    // MARK: Incoming pushes (silent/data via core broadcast)

    @objc func onRemoteNotification(_ note: Notification) {
        guard let userInfo = note.userInfo?["payload"] as? [AnyHashable: Any] else { return }
        handlePush(userInfo)
    }

    // Foreground presentation + taps.
    func userNotificationCenter(_ center: UNUserNotificationCenter,
                                willPresent notification: UNNotification,
                                withCompletionHandler completionHandler:
                                    @escaping (UNNotificationPresentationOptions) -> Void) {
        handlePush(notification.request.content.userInfo)
        completionHandler([.banner, .badge, .sound])
    }

    func userNotificationCenter(_ center: UNUserNotificationCenter,
                                didReceive response: UNNotificationResponse,
                                withCompletionHandler completionHandler: @escaping () -> Void) {
        handlePush(response.notification.request.content.userInfo)
        completionHandler()
    }

    // MARK: Dispatch

    private func handlePush(_ userInfo: [AnyHashable: Any]) {
        // Only data messages naming an `event` class trigger PHP (mirrors the paid plugin).
        guard let eventClass = userInfo["event"] as? String else { return }

        var data: [String: Any] = [:]
        for (key, value) in userInfo {
            if let k = key as? String, k != "event", k != "aps" {
                data[k] = value
            }
        }
        dispatchToPHP(eventClass, payload: ["data": data])
    }

    /// Foreground: dispatch live through the web view. Background: run the
    /// dispatch artisan command in the on-device PHP runtime.
    private func dispatchToPHP(_ eventClass: String, payload: [String: Any]) {
        let isActive = (UIApplication.shared.applicationState == .active)

        if isActive {
            DispatchQueue.main.async {
                LaravelBridge.shared.send?(eventClass, payload)
            }
            return
        }

        // Background: serialize payload as base64 JSON and dispatch via artisan.
        guard let json = try? JSONSerialization.data(withJSONObject: payload),
              let b64 = String(data: json.base64EncodedData(), encoding: .utf8) else { return }
        let escaped = eventClass.replacingOccurrences(of: "\\", with: "\\\\")
        let command = "native:push:dispatch '\(escaped)' '\(b64)' --base64"
        _ = PersistentPHPRuntime.shared.artisan(command: command)
    }
}
