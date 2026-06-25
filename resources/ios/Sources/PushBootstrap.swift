import Foundation
import UIKit
import UserNotifications
import FirebaseCore
import FirebaseMessaging

// nativephp.json -> ios.init_function = "PushBootstrap.initialize"
// Runs once at launch. Configure Firebase and attach the observer that bridges
// core's NotificationCenter push events into Laravel. Keep it fast.
enum PushBootstrap {
    static func initialize() {
        if FirebaseApp.app() == nil {
            FirebaseApp.configure()
        }

        let observer = PushObserver.shared
        Messaging.messaging().delegate = observer
        UNUserNotificationCenter.current().delegate = observer

        // Core's AppDelegate re-broadcasts APNs + remote-notification callbacks.
        let center = NotificationCenter.default
        center.addObserver(observer,
                           selector: #selector(PushObserver.onApnsToken(_:)),
                           name: .didRegisterForRemoteNotifications, object: nil)
        center.addObserver(observer,
                           selector: #selector(PushObserver.onRemoteNotification(_:)),
                           name: .didReceiveRemoteNotification, object: nil)
    }
}
