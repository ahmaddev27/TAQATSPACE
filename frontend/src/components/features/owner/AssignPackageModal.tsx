"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { useToast } from "@/components/providers/ToastProvider";
import { assignPackage, unassignPackage } from "@/lib/actions/owner";
import type { Member, Package } from "@/lib/types";
import { avatarInitial } from "./format";

export interface AssignPackageModalProps {
  pkg: Package;
  members: Member[];
  onClose: () => void;
}

/** Assign/unassign an internet package to/from members. */
export function AssignPackageModal({ pkg, members, onClose }: AssignPackageModalProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const assignable = members.filter((m) => m.status === "active");
  const [memberId, setMemberId] = useState<string>(
    assignable[0] ? String(assignable[0].user.id) : "",
  );

  const assigned = pkg.members ?? [];

  const doAssign = () => {
    if (!memberId) return;
    startTransition(async () => {
      const res = await assignPackage(pkg.id, memberId);
      if (res.ok) {
        toast({ tone: "ok", title: t("packages.assigned") });
      } else {
        toast({ tone: "err", title: t("packages.saveFailed"), body: res.message });
      }
    });
  };

  const doUnassign = (mid: string) => {
    startTransition(async () => {
      const res = await unassignPackage(pkg.id, mid);
      if (res.ok) {
        toast({ tone: "ok", title: t("packages.unassignedMember") });
      } else {
        toast({ tone: "err", title: t("packages.saveFailed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      title={t("packages.assignTitle", { name: pkg.name })}
      icon="wifi"
      onClose={onClose}
      closeLabel={t("common.close")}
      footer={
        <Button variant="ghost" onClick={onClose}>
          {t("common.close")}
        </Button>
      }
    >
      <div className="stack" style={{ gap: 18 }}>
        <div className="stack" style={{ gap: 12 }}>
          <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
            {t("packages.assignPrompt")}
          </p>
          <div className="row" style={{ gap: 10, alignItems: "flex-end" }}>
            <Field label={t("seats.member")} className="grow">
              <Select
                value={memberId}
                onChange={(e) => setMemberId(e.target.value)}
                disabled={assignable.length === 0}
              >
                {assignable.map((m) => (
                  <option key={m.user.id} value={String(m.user.id)}>
                    {m.user.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Button
              variant="primary"
              icon="plus"
              disabled={!memberId || pending}
              onClick={doAssign}
            >
              {t("packages.assign")}
            </Button>
          </div>
        </div>

        <div className="divider" />

        <div className="stack" style={{ gap: 10 }}>
          <div className="muted" style={{ fontSize: "var(--fs-sm)", fontWeight: 600 }}>
            {t("packages.assignedMembers")}
          </div>
          {assigned.length === 0 ? (
            <p className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
              {t("packages.noAssigned")}
            </p>
          ) : (
            assigned.map((m) => (
              <div key={m.id} className="row" style={{ gap: 10 }}>
                <Avatar initial={avatarInitial(m.name)} src={m.avatar} alt={m.name} size="sm" round />
                <span
                  className="grow stack"
                  style={{ gap: 2, fontSize: "var(--fs-sm)" }}
                >
                  <span>{m.name}</span>
                  {m.seat_number && (
                    <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                      {t("packages.seatLabel", { seat: m.seat_number })}
                    </span>
                  )}
                </span>
                <Button
                  variant="ghost"
                  size="sm"
                  icon="x"
                  disabled={pending}
                  onClick={() => doUnassign(m.id)}
                >
                  {t("packages.unassign")}
                </Button>
              </div>
            ))
          )}
        </div>
      </div>
    </Modal>
  );
}
