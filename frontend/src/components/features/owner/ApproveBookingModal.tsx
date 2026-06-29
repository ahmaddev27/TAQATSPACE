"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { SeatMap, type SeatMapSeat } from "@/components/ui/SeatMap";
import { Link } from "@/i18n/navigation";
import type { Package, Seat } from "@/lib/types";

export interface ApproveBookingModalProps {
  memberName: string;
  /** The freelancer's requested seat-type label, or null for "any seat". */
  requestedTypeLabel: string | null;
  seats: Seat[];
  /** The workspace's active internet packages; one MUST be chosen to approve. */
  packages: Package[];
  pending: boolean;
  onConfirm: (seatId: string | null, packageId: string) => void;
  onClose: () => void;
}

/**
 * Approve a booking by picking an available seat (matching the requested type)
 * and a required internet package. When the workspace has no packages, approval
 * is blocked and the owner is pointed to the packages page to create one first.
 */
export function ApproveBookingModal({
  memberName,
  requestedTypeLabel,
  seats,
  packages,
  pending,
  onConfirm,
  onClose,
}: ApproveBookingModalProps) {
  const t = useTranslations("owner");
  const [selectedSeat, setSelectedSeat] = useState<string | null>(
    seats[0]?.id ?? null,
  );
  const [selectedPackage, setSelectedPackage] = useState<string>("");

  const hasPackages = packages.length > 0;
  const canApprove = hasPackages && selectedPackage !== "";

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
            disabled={!canApprove}
            loading={pending}
            onClick={() =>
              canApprove && onConfirm(selectedSeat, selectedPackage)
            }
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
          selected={selectedSeat}
          onSelect={setSelectedSeat}
          cols={8}
        />
      )}

      <div style={{ marginTop: 20 }}>
        {hasPackages ? (
          <label className="stack" style={{ gap: 6 }}>
            <span style={{ fontSize: "var(--fs-sm)", fontWeight: 600 }}>
              {t("bookings.internetPackage")}
            </span>
            <Select
              value={selectedPackage}
              onChange={(e) => setSelectedPackage(e.target.value)}
            >
              <option value="" disabled>
                {t("bookings.choosePackage")}
              </option>
              {packages.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} — {p.price}
                </option>
              ))}
            </Select>
          </label>
        ) : (
          <div className="card card-pad" style={{ background: "var(--surface-2)" }}>
            <div className="h3" style={{ fontSize: "var(--fs-md)" }}>
              {t("bookings.noPackagesTitle")}
            </div>
            <p className="muted" style={{ marginTop: 4, fontSize: "var(--fs-sm)" }}>
              {t("bookings.noPackagesBody")}
            </p>
            <Link
              href="/owner/packages"
              className="btn btn-secondary"
              style={{ marginTop: 12, display: "inline-flex" }}
            >
              {t("bookings.createPackageCta")}
            </Link>
          </div>
        )}
      </div>
    </Modal>
  );
}
