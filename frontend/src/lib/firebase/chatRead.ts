"use client";

/**
 * Client-side read tracking for the realtime chat. The Firestore model stores no
 * per-user read state, so "unread" is derived locally: we remember the
 * `updatedAt` of each conversation the user has last viewed, in localStorage. A
 * conversation counts as unread when its latest incoming message is newer than
 * that watermark. Opening a thread updates the watermark (see {@link
 * markConversationSeen}), which clears its unread state across the app.
 */

const STORAGE_KEY = "taqat_chat_seen";
/** Same-tab signal that the seen-map changed (storage events only fire cross-tab). */
const SEEN_EVENT = "taqat:chat-seen";

/** Map of conversationId → last-seen `updatedAt` (epoch millis). */
type SeenMap = Record<string, number>;

function readSeen(): SeenMap {
  if (typeof window === "undefined") return {};
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as SeenMap) : {};
  } catch {
    return {};
  }
}

/** The current seen-map (conversationId → last-seen epoch millis). */
export function getSeenMap(): SeenMap {
  return readSeen();
}

/**
 * Mark a conversation as seen up to `updatedAt`. No-op when the watermark is
 * already current, so it is safe to call on every snapshot of an open thread.
 */
export function markConversationSeen(
  convId: string,
  updatedAt: number | null,
): void {
  if (typeof window === "undefined" || !convId) return;

  const map = readSeen();
  const ts = updatedAt ?? Date.now();
  if ((map[convId] ?? 0) >= ts) return;

  map[convId] = ts;
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
  } catch {
    // Ignore quota/serialization errors — the badge is a non-critical hint.
  }
  window.dispatchEvent(new Event(SEEN_EVENT));
}

/**
 * Subscribe to seen-map changes — same tab (custom event) and other tabs
 * (storage event). Returns an unsubscribe function.
 */
export function subscribeSeen(callback: () => void): () => void {
  if (typeof window === "undefined") return () => {};

  const onStorage = (event: StorageEvent) => {
    if (event.key === null || event.key === STORAGE_KEY) callback();
  };

  window.addEventListener(SEEN_EVENT, callback);
  window.addEventListener("storage", onStorage);

  return () => {
    window.removeEventListener(SEEN_EVENT, callback);
    window.removeEventListener("storage", onStorage);
  };
}
