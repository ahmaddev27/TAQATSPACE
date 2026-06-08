/**
 * Firestore-backed realtime chat — env-gated and graceful.
 *
 * Auth bridge: the frontend can't read its httpOnly bearer cookie, so it POSTs
 * to the Next BFF (`/api/chat/token`), which forwards to the backend and returns
 * a Firebase **custom token**. We then `signInWithCustomToken`, after which
 * Firestore security rules identify the user by `request.auth.uid` (= our user
 * id). Every export here is a no-op when Firebase is unconfigured.
 *
 * Data model (see `firebase/firestore.rules`):
 *   conversations/{convId}
 *     { participants: [uidA, uidB], participantNames: { uid: name },
 *       workspaceId, lastMessage, lastSenderId, updatedAt }
 *   conversations/{convId}/messages/{msgId}
 *     { senderId, text, createdAt }
 *
 * `convId` is derived deterministically from the sorted participant uids, so the
 * same pair of users always resolves to a single conversation document.
 */

import type {
  DocumentData,
  Firestore,
  QueryDocumentSnapshot,
  Timestamp,
} from "firebase/firestore";
import {
  getFirebaseAuth,
  getFirestoreDb,
  isFirebaseConfigured,
} from "./app";

/** BFF proxy that mints a Firebase custom token for the authed user. */
const TOKEN_ENDPOINT = "/api/chat/token";

/** A conversation list row, as surfaced to the UI. */
export interface ChatConversation {
  id: string;
  participants: string[];
  participantNames: Record<string, string>;
  workspaceId: string | null;
  lastMessage: string;
  lastSenderId: string | null;
  /** Epoch millis of the last update, for stable ordering/display. */
  updatedAt: number | null;
}

/** A single chat message, as surfaced to the UI. */
export interface ChatMessage {
  id: string;
  senderId: string;
  text: string;
  /** Epoch millis; null while the serverTimestamp is still resolving. */
  createdAt: number | null;
}

/** The minimal identity needed to author a message / name a conversation. */
export interface ChatParticipant {
  id: string;
  name: string;
}

/** Resolved chat session: the signed-in Firebase uid (= our user id). */
export interface ChatSession {
  uid: string;
}

/** Cached sign-in so repeated screen mounts don't re-mint / re-auth. */
let sessionPromise: Promise<ChatSession | null> | null = null;

/**
 * Deterministic conversation id for a pair of uids: the sorted ids joined by
 * `__`. Order-independent, so both participants compute the same id.
 */
export function conversationId(uidA: string, uidB: string): string {
  return [uidA, uidB].sort().join("__");
}

/**
 * Sign the current user in to Firebase Auth for chat, via the custom-token
 * bridge. Idempotent and cached: concurrent/repeat calls share one promise.
 * Resolves to `null` (never throws) when Firebase is unconfigured, the backend
 * returns 503, or sign-in fails — callers render the unavailable state.
 */
export async function signInForChat(): Promise<ChatSession | null> {
  if (!isFirebaseConfigured()) return null;

  if (!sessionPromise) {
    sessionPromise = (async () => {
      try {
        const auth = await getFirebaseAuth();
        if (!auth) return null;

        const res = await fetch(TOKEN_ENDPOINT, {
          method: "POST",
          credentials: "include",
        });
        if (!res.ok) return null;

        const { token, uid } = (await res.json()) as {
          token?: string;
          uid?: string;
        };
        if (!token || !uid) return null;

        const { signInWithCustomToken } = await import("firebase/auth");
        await signInWithCustomToken(auth, token);

        return { uid: String(uid) };
      } catch {
        // Reset so a later retry (e.g. transient network error) can succeed.
        sessionPromise = null;
        return null;
      }
    })();
  }

  return sessionPromise;
}

/** Coerce a Firestore Timestamp (or null) to epoch millis. */
function toMillis(value: unknown): number | null {
  if (value && typeof (value as Timestamp).toMillis === "function") {
    return (value as Timestamp).toMillis();
  }
  return null;
}

function mapConversation(
  snap: QueryDocumentSnapshot<DocumentData>,
): ChatConversation {
  const data = snap.data();
  return {
    id: snap.id,
    participants: Array.isArray(data.participants) ? data.participants : [],
    participantNames:
      data.participantNames && typeof data.participantNames === "object"
        ? data.participantNames
        : {},
    workspaceId: data.workspaceId ?? null,
    lastMessage: typeof data.lastMessage === "string" ? data.lastMessage : "",
    lastSenderId: data.lastSenderId ?? null,
    updatedAt: toMillis(data.updatedAt),
  };
}

function mapMessage(snap: QueryDocumentSnapshot<DocumentData>): ChatMessage {
  const data = snap.data();
  return {
    id: snap.id,
    senderId: typeof data.senderId === "string" ? data.senderId : "",
    text: typeof data.text === "string" ? data.text : "",
    createdAt: toMillis(data.createdAt),
  };
}

/** No-op unsubscribe handed back when chat is unavailable. */
const NOOP = (): void => {};

/**
 * Subscribe to the conversations the given uid participates in, newest first.
 * Returns an unsubscribe function; a no-op when Firebase is unconfigured.
 */
export function watchConversations(
  uid: string,
  cb: (conversations: ChatConversation[]) => void,
): () => void {
  if (!isFirebaseConfigured()) return NOOP;

  let unsub: (() => void) | null = null;
  let cancelled = false;

  (async () => {
    const db = await getFirestoreDb();
    if (!db || cancelled) return;

    const { collection, onSnapshot, orderBy, query, where } = await import(
      "firebase/firestore"
    );

    const q = query(
      collection(db, "conversations"),
      where("participants", "array-contains", uid),
      orderBy("updatedAt", "desc"),
    );

    unsub = onSnapshot(
      q,
      (snapshot) => cb(snapshot.docs.map(mapConversation)),
      () => cb([]),
    );
  })();

  return () => {
    cancelled = true;
    unsub?.();
  };
}

/**
 * Subscribe to a conversation's messages, oldest first. Returns an unsubscribe
 * function; a no-op when Firebase is unconfigured or `convId` is empty.
 */
export function watchMessages(
  convId: string,
  cb: (messages: ChatMessage[]) => void,
): () => void {
  if (!isFirebaseConfigured() || !convId) return NOOP;

  let unsub: (() => void) | null = null;
  let cancelled = false;

  (async () => {
    const db = await getFirestoreDb();
    if (!db || cancelled) return;

    const { collection, onSnapshot, orderBy, query } = await import(
      "firebase/firestore"
    );

    const q = query(
      collection(db, "conversations", convId, "messages"),
      orderBy("createdAt", "asc"),
    );

    unsub = onSnapshot(
      q,
      (snapshot) => cb(snapshot.docs.map(mapMessage)),
      () => cb([]),
    );
  })();

  return () => {
    cancelled = true;
    unsub?.();
  };
}

/**
 * Append a message to a conversation and upsert the conversation doc's
 * denormalised preview (`lastMessage`/`lastSenderId`/`updatedAt`). Creates the
 * conversation lazily on the first message via `setDoc(..., { merge: true })`.
 *
 * Resolves to `false` (never throws) when Firebase is unconfigured, the text is
 * blank, or the write fails — the caller can surface a toast.
 *
 * @param participants the two participant identities (self + contact).
 * @param sender       the authoring participant (must be one of `participants`).
 * @param workspaceId  the workspace tying the two users together (for new docs).
 */
export async function sendMessage(
  convId: string,
  participants: ChatParticipant[],
  text: string,
  sender: ChatParticipant,
  workspaceId: string | null = null,
): Promise<boolean> {
  const trimmed = text.trim();
  if (!isFirebaseConfigured() || !convId || trimmed === "") return false;

  try {
    const db = await getFirestoreDb();
    if (!db) return false;

    await writeMessage(db, convId, participants, trimmed, sender, workspaceId);
    return true;
  } catch {
    return false;
  }
}

/**
 * The actual Firestore write. The parent conversation is upserted FIRST, then
 * the message is appended — two sequential writes rather than one batch.
 *
 * Why not a batch: the message-create security rule checks membership via
 * `get(parentConversation)`, and Firestore evaluates a batched write against the
 * pre-batch committed state. A conversation created in the same batch as its
 * first message isn't visible to that `get()` yet, so the write is denied
 * ("Missing or insufficient permissions"). Committing the conversation first
 * makes the parent exist before the message rule runs.
 */
async function writeMessage(
  db: Firestore,
  convId: string,
  participants: ChatParticipant[],
  text: string,
  sender: ChatParticipant,
  workspaceId: string | null = null,
): Promise<void> {
  const { collection, doc, serverTimestamp, setDoc } = await import(
    "firebase/firestore"
  );

  const conversationRef = doc(db, "conversations", convId);
  const messageRef = doc(collection(conversationRef, "messages"));

  const participantNames = Object.fromEntries(
    participants.map((p) => [p.id, p.name]),
  );

  // 1) Upsert the conversation + its denormalised preview (creates it on the
  //    first message), so the parent exists before the message rule's get().
  await setDoc(
    conversationRef,
    {
      participants: participants.map((p) => p.id),
      participantNames,
      workspaceId,
      lastMessage: text,
      lastSenderId: sender.id,
      updatedAt: serverTimestamp(),
    },
    { merge: true },
  );

  // 2) Append the message.
  await setDoc(messageRef, {
    senderId: sender.id,
    text,
    createdAt: serverTimestamp(),
  });
}
