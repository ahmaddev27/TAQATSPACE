/* eslint-disable */
/*
 * Firebase Cloud Messaging — background push service worker.
 *
 * A service worker cannot read `process.env`, so the Firebase web config is
 * passed in the registration query string by `lib/firebase/messaging.ts`
 * (`navigator.serviceWorker.register('/firebase-messaging-sw.js?apiKey=...')`).
 * When no config is present this worker stays completely inert — no Firebase is
 * loaded — so the app is unaffected when push is not configured.
 *
 * The Firebase "compat" scripts are loaded from the official CDN because a SW
 * is a separate, un-bundled script; pinning the version keeps it deterministic.
 */

const FIREBASE_SDK_VERSION = "12.14.0";

/** Parse the public Firebase config from this worker's own URL query string. */
function readConfigFromQuery() {
  try {
    const params = new URL(self.location.href).searchParams;
    const config = {
      apiKey: params.get("apiKey"),
      authDomain: params.get("authDomain"),
      projectId: params.get("projectId"),
      messagingSenderId: params.get("messagingSenderId"),
      appId: params.get("appId"),
      storageBucket: params.get("storageBucket"),
    };
    // Require the keys FCM actually needs; bail (stay inert) otherwise.
    const required = ["apiKey", "projectId", "messagingSenderId", "appId"];
    if (required.some((key) => !config[key])) return null;
    return config;
  } catch (err) {
    return null;
  }
}

const firebaseConfig = readConfigFromQuery();

if (firebaseConfig) {
  importScripts(
    `https://www.gstatic.com/firebasejs/${FIREBASE_SDK_VERSION}/firebase-app-compat.js`,
  );
  importScripts(
    `https://www.gstatic.com/firebasejs/${FIREBASE_SDK_VERSION}/firebase-messaging-compat.js`,
  );

  firebase.initializeApp(firebaseConfig);

  const messaging = firebase.messaging();

  // Background message handler: shown only when the tab is backgrounded/closed.
  // Foreground messages are handled in-app via `onMessage` (toast) instead.
  messaging.onBackgroundMessage((payload) => {
    const notification = payload.notification || {};
    const title = notification.title || "TaqatSpace";
    const options = {
      body: notification.body || "",
      icon: "/images/icon-192.png",
      badge: "/images/badge-72.png",
      data: payload.data || {},
    };
    self.registration.showNotification(title, options);
  });

  // Focus an existing tab (or open one) when a background notification is
  // clicked. `click_action`/`link` in the data payload sets the destination.
  self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const target =
      (event.notification.data && event.notification.data.link) || "/";

    event.waitUntil(
      self.clients
        .matchAll({ type: "window", includeUncontrolled: true })
        .then((clientList) => {
          for (const client of clientList) {
            if ("focus" in client) {
              client.navigate(target);
              return client.focus();
            }
          }
          if (self.clients.openWindow) {
            return self.clients.openWindow(target);
          }
        }),
    );
  });
}
