"use client";

import { useCallback, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import {
  loadConversation,
  markThreadRead,
  sendMessage,
} from "@/lib/actions/messaging";
import type {
  ConversationResult,
  Message,
  MessageThread,
} from "@/lib/api/messages";
import { ThreadList } from "./ThreadList";
import { MessageThread as ThreadPane } from "./MessageThread";
import { Composer } from "./Composer";
import { BroadcastModal } from "./BroadcastModal";
import "./messaging.css";

export interface MessagesViewProps {
  initialThreads: MessageThread[];
  ownerId: string;
  /** Active members count, for the broadcast recipient label. */
  recipientCount: number;
}

/**
 * Owner messaging workspace: two-pane thread list + conversation, a composer,
 * and a broadcast dialog. Thread selection lazily loads the full conversation
 * via a Server Action and marks it read. Local optimistic updates keep the UI
 * responsive; a Pusher subscription could later push new messages into
 * `setActiveConversation` without changing this component's contract.
 */
export function MessagesView({
  initialThreads,
  ownerId,
  recipientCount,
}: MessagesViewProps) {
  const t = useTranslations("messaging.messages");
  const { toast } = useToast();
  const [, startTransition] = useTransition();

  const [threads, setThreads] = useState<MessageThread[]>(initialThreads);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [conversation, setConversation] = useState<ConversationResult | null>(
    null,
  );
  const [loading, setLoading] = useState(false);
  const [broadcast, setBroadcast] = useState(false);

  const openThread = useCallback(
    async (thread: MessageThread) => {
      setActiveId(thread.member.id);
      setLoading(true);
      const res = await loadConversation(thread.member.id);
      setLoading(false);
      if (res.ok) {
        setConversation(res.data);
        // Clear the unread badge locally; persist read state in the background.
        if (thread.unread_count > 0) {
          setThreads((list) =>
            list.map((th) =>
              th.member.id === thread.member.id
                ? { ...th, unread_count: 0 }
                : th,
            ),
          );
          startTransition(async () => {
            await markThreadRead(thread.member.id);
          });
        }
      } else {
        toast({ tone: "err", title: t("loadFailed"), body: res.message });
        setActiveId(null);
      }
    },
    [t, toast],
  );

  const handleSend = useCallback(
    async (content: string): Promise<boolean> => {
      if (!activeId) return false;
      const res = await sendMessage(activeId, content);
      if (!res.ok || !res.data) {
        toast({ tone: "err", title: t("sendFailed"), body: res.message });
        return false;
      }
      const sent: Message = res.data;
      setConversation((prev) =>
        prev ? { ...prev, messages: [...prev.messages, sent] } : prev,
      );
      // Bump the thread's last-message preview + reorder to the top.
      setThreads((list) => {
        const idx = list.findIndex((th) => th.member.id === activeId);
        if (idx === -1) return list;
        const updated: MessageThread = {
          ...list[idx],
          last_message: {
            id: sent.id,
            sender_id: sent.sender_id,
            content: sent.content,
            read_at: sent.read_at,
            created_at: sent.created_at,
          },
        };
        return [updated, ...list.filter((_, i) => i !== idx)];
      });
      return true;
    },
    [activeId, t, toast],
  );

  const closeThread = useCallback(() => {
    setActiveId(null);
    setConversation(null);
  }, []);

  return (
    <>
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
        <Button
          variant="secondary"
          icon="megaphone"
          onClick={() => setBroadcast(true)}
        >
          {t("broadcast")}
        </Button>
      </div>

      <div className={`msg-shell ${activeId ? "has-active" : ""}`.trim()}>
        <ThreadList
          threads={threads}
          activeMemberId={activeId}
          onSelect={openThread}
        />

        <div className="msg-pane">
          {conversation ? (
            <>
              <ThreadPane
                conversation={conversation}
                ownerId={ownerId}
                loading={loading}
                onBack={closeThread}
              />
              <Composer onSend={handleSend} />
            </>
          ) : (
            <div className="msg-empty">
              <div>
                <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                  {t("emptyThreadTitle")}
                </div>
                <div style={{ fontSize: "var(--fs-sm)" }}>
                  {t("emptyThreadBody")}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {broadcast && (
        <BroadcastModal
          recipientCount={recipientCount}
          onClose={() => setBroadcast(false)}
        />
      )}
    </>
  );
}
