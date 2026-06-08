/**
 * FCM web-push registration — env-gated and graceful.
 *
 * Public surface:
 *  - `registerForPush(onForeground?)` — request permission, mint an FCM token,
 *    register it with the backend, and subscribe to foreground messages.
 *  - `unregisterPush()` — delete the local token and unregister it server-side.
 *
 * Every path is a no-op when Firebase is unconfigured, the browser lacks push
 * support, or the user denied notifications. Nothing here ever throws; failures
 * are swallowed so push problems never disrupt the app.
 */

import type { MessagePayload } from "firebase/messaging";
import {
  FIREBASE_VAPID_KEY,
  getFirebaseConfig,
  getFirebaseMessaging,
  isFirebaseConfigured,
} from "./app";

/** Backend proxy (Next.js BFF) that attaches the bearer cookie server-side. */
const DEVICE_TOKENS_ENDPOINT = "/api/notifications/device-tokens";

/** Path of the background-push service worker served from `public/`. */
const SW_PATH = "/firebase-messaging-sw.js";

/** Callback invoked for a message received while the tab is focused. */
export type ForegroundHandler = (payload: MessagePayload) => void;

/** Tracks the active foreground subscription so it can be torn down on logout. */
let unsubscribeForeground: (() => void) | null = null;

/**
 * Whether the current browser environment can support FCM web push at all:
 * configured + secure context + service worker + Notification + Push APIs.
 */
function canUsePush(): boolean {
  return (
    isFirebaseConfigured() &&
    typeof window !== "undefined" &&
    "serviceWorker" in navigator &&
    "Notification" in window &&
    "PushManager" in window
  );
}

/**
 * Register the background-push service worker, passing the Firebase config in the
 * query string (a service worker cannot read `process.env`). The SW stays inert
 * if no config is passed. Returns `null` on any failure.
 */
async function ensureServiceWorker(): Promise<ServiceWorkerRegistration | null> {
  const config = getFirebaseConfig();
  if (!config) return null;

  try {
    const params = new URLSearchParams({
      apiKey: config.apiKey,
      authDomain: config.authDomain,
      projectId: config.projectId,
      messagingSenderId: config.messagingSenderId,
      appId: config.appId,
      storageBucket: config.storageBucket,
    });
    return await navigator.serviceWorker.register(`${SW_PATH}?${params.toString()}`);
  } catch {
    return null;
  }
}

/** Send the freshly-minted token to the backend via the BFF proxy. */
async function persistToken(token: string): Promise<void> {
  try {
    await fetch(DEVICE_TOKENS_ENDPOINT, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token, platform: "web" }),
    });
  } catch {
    // Backend hiccup — token still works locally; next login retries.
  }
}

/** Tell the backend to forget a token we are about to delete locally. */
async function forgetToken(token: string): Promise<void> {
  try {
    await fetch(DEVICE_TOKENS_ENDPOINT, {
      method: "DELETE",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token }),
    });
  } catch {
    // Best-effort: the token is being deleted client-side regardless.
  }
}

/**
 * Request notification permission, mint an FCM token, register it with the
 * backend, and subscribe to foreground messages. Safe to call on every
 * authenticated session start; no-ops when push is unavailable or denied.
 *
 * @param onForeground optional handler for messages received while focused.
 */
export async function registerForPush(
  onForeground?: ForegroundHandler,
): Promise<void> {
  if (!canUsePush() || !FIREBASE_VAPID_KEY) return;

  try {
    // Don't re-prompt a user who already said no — and never spam the console.
    if (Notification.permission === "denied") return;

    const permission =
      Notification.permission === "granted"
        ? "granted"
        : await Notification.requestPermission();
    if (permission !== "granted") return;

    const messaging = await getFirebaseMessaging();
    if (!messaging) return;

    const registration = await ensureServiceWorker();
    if (!registration) return;

    const { getToken, onMessage } = await import("firebase/messaging");
    const token = await getToken(messaging, {
      vapidKey: FIREBASE_VAPID_KEY,
      serviceWorkerRegistration: registration,
    });
    if (!token) return;

    await persistToken(token);

    // Replace any prior foreground subscription (e.g. account switch) so a
    // single handler is active at a time.
    unsubscribeForeground?.();
    unsubscribeForeground = onForeground
      ? onMessage(messaging, onForeground)
      : null;
  } catch {
    // Permission churn, unsupported browser, or network error — stay silent.
  }
}

/**
 * Delete the local FCM token, unregister it from the backend, and drop the
 * foreground subscription. Called on logout; no-ops when push is unavailable.
 */
export async function unregisterPush(): Promise<void> {
  unsubscribeForeground?.();
  unsubscribeForeground = null;

  if (!canUsePush()) return;

  try {
    const messaging = await getFirebaseMessaging();
    if (!messaging) return;

    const { getToken, deleteToken } = await import("firebase/messaging");

    // Recover the current token so the backend can drop the matching row, then
    // invalidate it locally.
    let token: string | null = null;
    try {
      token = await getToken(messaging, { vapidKey: FIREBASE_VAPID_KEY });
    } catch {
      token = null;
    }
    if (token) await forgetToken(token);

    await deleteToken(messaging);
  } catch {
    // Token already gone or browser unsupported — nothing to do.
  }
}
