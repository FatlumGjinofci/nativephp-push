// resources/js/push.js — optional helpers for Vue/React/vanilla SPAs.
// (Livewire/Blade apps use the PHP facade Native\Mobile\Facades\PushNotifications instead.)
const baseUrl = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const res = await fetch(baseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    });
    return res.json();
}

export const checkPermission = () => bridgeCall('PushNotification.CheckPermission');
export const enroll = (id, event) => bridgeCall('PushNotification.RequestPermission', { id, event });
export const getToken = () => bridgeCall('PushNotification.GetToken');
export const clearBadge = () => bridgeCall('PushNotification.ClearBadge');

export default { checkPermission, enroll, getToken, clearBadge };
