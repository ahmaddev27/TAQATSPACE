"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Select } from "@/components/ui/Select";
import type { Member } from "@/lib/types";

export interface AssignSeatModalProps {
  seatLabel: string;
  members: Member[];
  pending: boolean;
  onAssign: (memberId: string) => void;
  onCancel: () => void;
}

/**
 * Member-picker form for assigning a selected available seat. Rendered inline
 * inside the seat board's side panel (the panel itself is the surface).
 */
export function AssignSeatModal({
  seatLabel,
  members,
  pending,
  onAssign,
  onCancel,
}: AssignSeatModalProps) {
  const t = useTranslations("owner");
  const [memberId, setMemberId] = useState<string>(
    members[0] ? String(members[0].user.id) : "",
  );

  const hasMembers = members.length > 0;

  return (
    <div className="stack" style={{ gap: 14 }}>
      <div className="between">
        <span className="muted">{t("seats.selectedSeat")}</span>
        <span className="badge badge-info tnum">{seatLabel}</span>
      </div>

      {hasMembers ? (
        <Field label={t("seats.member")}>
          <Select
            value={memberId}
            onChange={(e) => setMemberId(e.target.value)}
          >
            {members.map((m) => (
              <option key={m.user.id} value={String(m.user.id)}>
                {m.user.name}
              </option>
            ))}
          </Select>
        </Field>
      ) : (
        <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
          {t("seats.noActiveMembers")}
        </p>
      )}

      <Button
        variant="primary"
        block
        icon="check"
        disabled={!hasMembers || !memberId}
        loading={pending}
        onClick={() => onAssign(memberId)}
      >
        {t("seats.confirmAssign")}
      </Button>
      <Button variant="ghost" block onClick={onCancel}>
        {t("seats.cancel")}
      </Button>
    </div>
  );
}
