"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import {
  deleteContactMessage,
  markContactRead,
} from "@/lib/actions/contact";
import type { ContactMessage } from "@/lib/api/contact";

export interface ContactInboxProps {
  messages: ContactMessage[];
}

/** Admin inbox: read public contact messages, reply by email, mark read, delete. */
export function ContactInbox({ messages }: ContactInboxProps) {
  const t = useTranslations("admin.contact");
  const { toast } = useToast();
  const [busyId, setBusyId] = useState<string | null>(null);
  const [pending, startTransition] = useTransition();

  const run = (
    id: string,
    action: () => Promise<{ ok: boolean; message?: string }>,
    okTitle: string,
  ) => {
    setBusyId(id);
    startTransition(async () => {
      const res = await action();
      if (res.ok) {
        toast({ tone: "ok", title: okTitle });
      } else {
        toast({ tone: "err", title: t("failed"), body: res.message });
      }
      setBusyId(null);
    });
  };

  if (messages.length === 0) {
    return (
      <div className="card card-pad empty-state" style={{ minHeight: 220 }}>
        <span className="st-ico">
          <Icon name="mail" />
        </span>
        <div>
          <div className="h3">{t("emptyTitle")}</div>
          <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
            {t("emptyBody")}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="stack" style={{ gap: 14 }}>
      {messages.map((m) => (
        <div key={m.id} className="card card-pad stack" style={{ gap: 10 }}>
          <div className="between wrap" style={{ gap: 10 }}>
            <div style={{ minWidth: 0 }}>
              <div className="row" style={{ gap: 8, alignItems: "center" }}>
                {!m.is_read && <span className="chat-unread-dot" aria-hidden="true" />}
                <strong>{m.subject}</strong>
              </div>
              <div
                className="muted-3"
                style={{ fontSize: "var(--fs-sm)", marginTop: 2 }}
              >
                {m.name} · <span className="ltr">{m.email}</span>
              </div>
            </div>
            <span className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>
              {m.created_at?.slice(0, 10)}
            </span>
          </div>

          <p className="muted" style={{ whiteSpace: "pre-wrap", margin: 0 }}>
            {m.message}
          </p>

          <div className="row wrap" style={{ gap: 8 }}>
            <a
              href={`mailto:${m.email}?subject=${encodeURIComponent(`Re: ${m.subject}`)}`}
              className="btn btn-primary btn-sm"
            >
              <Icon name="mail" size={15} />
              {t("reply")}
            </a>
            {!m.is_read && (
              <Button
                variant="secondary"
                size="sm"
                icon="check"
                loading={pending && busyId === m.id}
                onClick={() => run(m.id, () => markContactRead(m.id), t("markedRead"))}
              >
                {t("markRead")}
              </Button>
            )}
            <Button
              variant="ghost"
              size="sm"
              icon="x"
              loading={pending && busyId === m.id}
              onClick={() => run(m.id, () => deleteContactMessage(m.id), t("deleted"))}
            >
              {t("delete")}
            </Button>
          </div>
        </div>
      ))}
    </div>
  );
}
