"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { SeatMap, type SeatMapSeat } from "@/components/ui/SeatMap";
import type { Seat } from "@/lib/types";

export interface ApproveBookingModalProps {
  memberName: string;
  /** The freelancer's requested seat-type label, or null for "any seat". */
  requestedTypeLabel: string | null;
  seats: Seat[];
  pending: boolean;
  onConfirm: (seatId: string) => void;
  onClose: () => void;
}

/** Approve a booking by picking an available seat (matching the requested type). */
export function ApproveBookingModal({
  memberName,
  requestedTypeLabel,
  seats,
  pending,
  onConfirm,
  onClose,
}: ApproveBookingModalProps) {
  const t = useTranslations("owner");
  const [selected, setSelected] = useState<string | null>(
    seats[0]?.id ?? null,
  );

  const mapSeats: SeatMapSeat[] = seats.map((s) => ({
    id: s.id,
    label: s.seat_number,
    state: "available",
  }));

  return (
    <Modal
      title={t("bookings.approveTitle")}
      icon="grid"
      onClose={onClose}
      closeLabel={t("common.close")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("common.cancel")}
          </Button>
          <Button
            variant="primary"
            icon="check"
            disabled={!selected}
            loading={pending}
            onClick={() => selected && onConfirm(selected)}
          >
            {t("bookings.confirmApprove")}
          </Button>
        </>
      }
    >
      <p className="muted" style={{ marginBottom: 12, fontSize: "var(--fs-sm)" }}>
        {t("bookings.approvePrompt", { name: memberName })}
      </p>
      {requestedTypeLabel && (
        <p className="row" style={{ gap: 6, marginBottom: 16, fontSize: "var(--fs-sm)" }}>
          <Icon name="grid" size={15} />
          {t("bookings.requestedTypeHint", { type: requestedTypeLabel })}
        </p>
      )}
      {mapSeats.length === 0 ? (
        <p className="muted">
          {requestedTypeLabel
            ? t("bookings.noMatchingSeats")
            : t("seats.noSeatsTitle")}
        </p>
      ) : (
        <SeatMap
          seats={mapSeats}
          selected={selected}
          onSelect={setSelected}
          cols={8}
        />
      )}
    </Modal>
  );
}
