"use client";

/**
 * The conversation the user is actively viewing in the chat screen, as a simple
 * per-tab singleton. The global new-message popup reads this to avoid popping for
 * the thread already on screen (Messenger does the same). Set by the chat screen
 * while a thread is open; cleared when it closes or the screen unmounts.
 *
 * A plain module variable (not React state) is intentional: the popup reads it
 * synchronously at fire time, with no re-render or event plumbing needed.
 */
let activeConversationId: string | null = null;

export function setActiveConversation(convId: string | null): void {
  activeConversationId = convId;
}

export function getActiveConversation(): string | null {
  return activeConversationId;
}
