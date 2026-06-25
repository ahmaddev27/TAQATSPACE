"use client";

import { useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";
import { Drawer } from "@/components/ui/Drawer";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import type { Member } from "@/lib/types";
import { avatarInitial, money, shortDate } from "./format";

export interface MemberDrawerProps {
  member: Member;
  locale: string;
  pending: boolean;
  onClose: () => void;
  onSuspend: () => void;
  onActivate: () => void;
}

/** Read-only member detail panel with a status action in the footer. */
export function MemberDrawer({
  member,
  locale,
  pending,
  onClose,
  onSuspend,
  onActivate,
}: MemberDrawerProps) {
  const t = useTranslations("owner");
  const tc = useTranslations();
  const confirm = useConfirm();
  const isSuspended = member.status === "suspended";

  const askSuspend = async () => {
    const ok = await confirm({
      title: t("members.suspend"),
      message: t("members.suspendConfirm"),
      confirmLabel: t("members.suspend"),
      tone: "danger",
      icon: "x",
    });
    if (ok) onSuspend();
  };

  const rows: Array<[string, string, string?]> = [
    [t("members.email"), member.user.email, "ltr"],
    [t("members.phone"), member.user.phone ?? "—", "ltr"],
    [t("members.seat"), member.seat_number ?? t("members.noSeat"), "tnum"],
    [t("members.plan"), tc(`planType.${member.plan_type}` as never)],
    ...(member.package
      ? ([[t("members.internetPackage"), member.package]] as Array<
          [string, string, string?]
        >)
      : []),
    [t("members.monthlyPrice"), money(member.monthly_price), "tnum"],
    [t("members.memberSince"), shortDate(member.join_date), "tnum ltr"],
  ];

  return (
    <Drawer
      title={t("members.drawerTitle")}
      onClose={onClose}
      closeLabel={t("common.close")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("common.close")}
          </Button>
          {isSuspended ? (
            <Button
              variant="primary"
              icon="check"
              loading={pending}
              onClick={onActivate}
            >
              {t("members.activate")}
            </Button>
          ) : (
            <Button
              variant="danger"
              icon="x"
              loading={pending}
              onClick={askSuspend}
            >
              {t("members.suspend")}
            </Button>
          )}
        </>
      }
    >
      <div className="stack" style={{ gap: 0 }}>
        <div className="drawer-section row" style={{ gap: 14 }}>
          <Avatar initial={avatarInitial(member.user.name)} src={member.user.avatar} alt={member.user.name} size="lg" round />
          <div>
            <div className="h3">{member.user.name}</div>
            <div className="muted">{member.user.specialty ?? "—"}</div>
            <div style={{ marginTop: 6 }}>
              <StatusBadge status={member.status} locale={locale} />
            </div>
          </div>
        </div>

        <div className="drawer-section stack" style={{ gap: 12 }}>
          {rows.map(([label, value, cls], i) => (
            <div key={i} className="between">
              <span className="muted">{label}</span>
              <span className={cls ?? ""} style={{ fontWeight: 500 }}>
                {value}
              </span>
            </div>
          ))}
        </div>
      </div>
    </Drawer>
  );
}
