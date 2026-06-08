/**
 * Firebase web SDK bootstrap — fully env-gated.
 *
 * The entire realtime/push stack is optional: when the `NEXT_PUBLIC_FIREBASE_*`
 * environment variables are absent, every export here no-ops and the SDK is
 * never imported or initialized. The app builds and runs identically with or
 * without Firebase credentials, so the user can add them later without code
 * changes.
 *
 * All Firebase imports are dynamic and browser-guarded so nothing leaks into the
 * server bundle or runs during SSR.
 */

import type { FirebaseApp, FirebaseOptions } from "firebase/app";
import type { Messaging } from "firebase/messaging";

/**
 * Public Firebase config, read from build-time env. These are `NEXT_PUBLIC_*`
 * so they are safe to expose to the browser (Firebase web config is not secret).
 * Extends `FirebaseOptions` so it drops straight into `initializeApp`.
 */
export interface FirebaseClientConfig extends FirebaseOptions {
  apiKey: string;
  authDomain: string;
  projectId: string;
  appId: string;
  messagingSenderId: string;
  storageBucket: string;
}

/** The six required config keys, mapped to their env vars. Order is stable. */
const REQUIRED_ENV: Record<
  "apiKey" | "authDomain" | "projectId" | "appId" | "messagingSenderId" | "storageBucket",
  string | undefined
> = {
  apiKey: process.env.NEXT_PUBLIC_FIREBASE_API_KEY,
  authDomain: process.env.NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN,
  projectId: process.env.NEXT_PUBLIC_FIREBASE_PROJECT_ID,
  appId: process.env.NEXT_PUBLIC_FIREBASE_APP_ID,
  messagingSenderId: process.env.NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID,
  storageBucket: process.env.NEXT_PUBLIC_FIREBASE_STORAGE_BUCKET,
};

/** Optional VAPID key required only for FCM web push (registerForPush). */
export const FIREBASE_VAPID_KEY =
  process.env.NEXT_PUBLIC_FIREBASE_VAPID_KEY ?? "";

/**
 * Resolved config, or `null` when any required env var is missing/blank. Frozen
 * once at module load — env is fixed for the lifetime of the bundle.
 */
const CONFIG: FirebaseClientConfig | null = (() => {
  const entries = Object.entries(REQUIRED_ENV);
  const allPresent = entries.every(
    ([, value]) => typeof value === "string" && value.trim() !== "",
  );
  if (!allPresent) return null;
  return Object.fromEntries(entries) as unknown as FirebaseClientConfig;
})();

/**
 * Whether Firebase has a complete config. The single gate every caller checks
 * before touching anything Firebase-related; when false the SDK is untouched.
 */
export function isFirebaseConfigured(): boolean {
  return CONFIG !== null;
}

/**
 * The public config when configured, else `null`. Used by the registrar to
 * build the service-worker query string (the SW can't read `process.env`).
 */
export function getFirebaseConfig(): FirebaseClientConfig | null {
  return CONFIG;
}

/** Lazily-created singletons so HMR/StrictMode double-invokes don't re-init. */
let appPromise: Promise<FirebaseApp> | null = null;
let messagingPromise: Promise<Messaging | null> | null = null;

/**
 * Lazily initialize (or reuse) the Firebase app. Returns `null` when unconfigured
 * or called outside the browser — never throws, never initializes a half-config.
 */
export async function getFirebaseApp(): Promise<FirebaseApp | null> {
  if (!CONFIG || typeof window === "undefined") return null;

  if (!appPromise) {
    appPromise = (async () => {
      const { getApps, getApp, initializeApp } = await import("firebase/app");
      return getApps().length ? getApp() : initializeApp(CONFIG);
    })();
  }
  return appPromise;
}

/**
 * Lazily resolve the FCM `Messaging` instance. Returns `null` when unconfigured,
 * outside the browser, or when the browser cannot support FCM (`isSupported()`
 * is false — e.g. no service workers / push). Never throws.
 */
export async function getFirebaseMessaging(): Promise<Messaging | null> {
  if (!CONFIG || typeof window === "undefined") return null;

  if (!messagingPromise) {
    messagingPromise = (async () => {
      try {
        const { isSupported, getMessaging } = await import("firebase/messaging");
        if (!(await isSupported())) return null;
        const app = await getFirebaseApp();
        return app ? getMessaging(app) : null;
      } catch {
        // Unsupported browser or transient SDK error — stay silent and inert.
        return null;
      }
    })();
  }
  return messagingPromise;
}
