"use client";

import { useMemo, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import type { MessageThread } from "@/lib/api/messages";
import { avatarInitial } from "@/components/features/owner/format";
import { relativeTime } from "./time";

export interface ThreadListProps {
  threads: MessageThread[];
  activeMemberId: string | null;
  onSelect: (thread: MessageThread) => void;
}

/** Left pane: searchable list of member conversations with unread badges. */
export function ThreadList({
  threads,
  activeMemberId,
  onSelect,
}: ThreadListProps) {
  const t = useTranslations("messaging.messages");
  const locale = useLocale();
  const [query, setQuery] = useState("");

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return threads;
    return threads.filter((th) =>
      th.member.name.toLowerCase().includes(q),
    );
  }, [threads, query]);

  return (
    <div className="thread-list">
      <div className="thread-list-head">
        <div className="dt-search input-icon" style={{ flex: 1 }}>
          <Icon name="search" />
          <input
            className="input"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t("search")}
            aria-label={t("search")}
          />
        </div>
      </div>

      <div className="thread-list-scroll">
        {filtered.length === 0 ? (
          <div className="empty-state" style={{ padding: 32 }}>
            <div>
              <div style={{ fontWeight: 600, color: "var(--text-2)" }}>
                {t("noThreadsTitle")}
              </div>
              <div style={{ fontSize: "var(--fs-sm)" }}>
                {t("noThreadsBody")}
              </div>
            </div>
          </div>
        ) : (
          filtered.map((th) => {
            const active = th.member.id === activeMemberId;
            return (
              <div
                key={th.member.id}
                className={`thread-item ${active ? "active" : ""}`.trim()}
                role="button"
                tabIndex={0}
                onClick={() => onSelect(th)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    onSelect(th);
                  }
                }}
              >
                <Avatar initial={avatarInitial(th.member.name)} round />
                <div className="grow">
                  <div className="thread-name">{th.member.name}</div>
                  <div className="thread-preview">
                    {th.last_message?.content ?? "—"}
                  </div>
                </div>
                <div className="thread-meta">
                  <span className="thread-time">
                    {relativeTime(th.last_message?.created_at, locale)}
                  </span>
                  {th.unread_count > 0 && (
                    <span className="thread-unread tnum">
                      {th.unread_count > 9 ? "9+" : th.unread_count}
                    </span>
                  )}
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
