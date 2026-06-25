import Foundation
import UIKit
import UserNotifications
import FirebaseMessaging

enum PushFunctions {

    // PushNotification.CheckPermission — no prompt.
    class CheckPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let semaphore = DispatchSemaphore(value: 0)
            var status = "unknown"
            UNUserNotificationCenter.current().getNotificationSettings { settings in
                switch settings.authorizationStatus {
                case .authorized:    status = "granted"
                case .denied:        status = "denied"
                case .notDetermined: status = "not_determined"
                case .provisional:   status = "provisional"
                case .ephemeral:     status = "ephemeral"
                @unknown default:    status = "unknown"
                }
                semaphore.signal()
            }
            _ = semaphore.wait(timeout: .now() + 2)
            return BridgeResponse.success(data: ["status": status])
        }
    }

    // PushNotification.RequestPermission — core's enroll() calls this with {id, event}.
    class RequestPermission: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if let id = parameters["id"] as? String {
                PushObserver.shared.enrollmentId = id
            }
            if let event = parameters["event"] as? String, !event.isEmpty {
                PushObserver.shared.tokenEventClass = event
            }

            UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, _ in
                guard granted else { return }
                DispatchQueue.main.async {
                    UIApplication.shared.registerForRemoteNotifications()
                }
            }
            return BridgeResponse.success(data: ["requested": true])
        }
    }

    // PushNotification.GetToken
    class GetToken: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if let cached = PushObserver.shared.cachedToken {
                return BridgeResponse.success(data: ["token": cached])
            }
            let semaphore = DispatchSemaphore(value: 0)
            var token = ""
            Messaging.messaging().token { result, _ in
                token = result ?? ""
                semaphore.signal()
            }
            _ = semaphore.wait(timeout: .now() + 3)
            return BridgeResponse.success(data: ["token": token])
        }
    }

    // PushNotification.ClearBadge
    class ClearBadge: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            DispatchQueue.main.async {
                if #available(iOS 16.0, *) {
                    UNUserNotificationCenter.current().setBadgeCount(0)
                } else {
                    UIApplication.shared.applicationIconBadgeNumber = 0
                }
                UNUserNotificationCenter.current().removeAllDeliveredNotifications()
            }
            return BridgeResponse.success(data: ["cleared": true])
        }
    }
}
