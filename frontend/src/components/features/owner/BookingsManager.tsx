"use client";

import { useMemo, useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/Badge";
import { Icon } from "@/components/ui/Icon";
import { Tabs } from "@/components/ui/Tabs";
import { useToast } from "@/components/providers/ToastProvider";
import { reviewBooking } from "@/lib/actions/owner";
import type { BookingRequest, BookingStatus, Seat } from "@/lib/types";
import { avatarInitial, shortDate } from "./format";
import { ApproveBookingModal } from "./ApproveBookingModal";
import { RejectBookingModal } from "./RejectBookingModal";

export interface BookingsManagerProps {
  bookings: BookingRequest[];
  availableSeats: Seat[];
}

const TAB_STATUS: Record<string, BookingStatus> = {
  pending: "pending",
  approved: "approved",
  rejected: "rejected",
};

/** Booking-request queue with status tabs and approve/reject flows. */
export function BookingsManager({ bookings, availableSeats }: BookingsManagerProps) {
  const t = useTranslations("owner");
  const tc = useTranslations();
  const locale = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [tab, setTab] = useState<string>("pending");
  const [approveFor, setApproveFor] = useState<BookingRequest | null>(null);
  const [rejectFor, setRejectFor] = useState<BookingRequest | null>(null);

  const rows = useMemo(
    () => bookings.filter((b) => b.status === TAB_STATUS[tab]),
    [bookings, tab],
  );

  const counts = useMemo(
    () => ({
      pending: bookings.filter((b) => b.status === "pending").length,
      approved: bookings.filter((b) => b.status === "approved").length,
      rejected: bookings.filter((b) => b.status === "rejected").length,
    }),
    [bookings],
  );

  const tabs = [
    { id: "pending", label: `${t("bookings.tabPending")} (${counts.pending})` },
    { id: "approved", label: `${t("bookings.tabApproved")} (${counts.approved})` },
    { id: "rejected", label: `${t("bookings.tabRejected")} (${counts.rejected})` },
  ];

  const doApprove = (seatId: string) => {
    if (!approveFor) return;
    startTransition(async () => {
      const res = await reviewBooking(approveFor.id, { action: "approve", seatId });
      if (res.ok) {
        toast({ tone: "ok", title: t("bookings.approved") });
        setApproveFor(null);
      } else {
        toast({ tone: "err", title: t("bookings.reviewFailed"), body: res.message });
      }
    });
  };

  const doReject = (reason: string) => {
    if (!rejectFor) return;
    startTransition(async () => {
      const res = await reviewBooking(rejectFor.id, {
        action: "reject",
        rejectionReason: reason || null,
      });
      if (res.ok) {
        toast({ tone: "ok", title: t("bookings.rejected") });
        setRejectFor(null);
      } else {
        toast({ tone: "err", title: t("bookings.reviewFailed"), body: res.message });
      }
    });
  };

  const emptyText: Record<string, [string, string]> = {
    pending: [t("bookings.emptyPending"), t("bookings.emptyPendingBody")],
    approved: [t("bookings.emptyApproved"), t("bookings.emptyPendingBody")],
    rejected: [t("bookings.emptyRejected"), t("bookings.emptyPendingBody")],
  };

  return (
    <div className="stack" style={{ gap: 16 }}>
      <Tabs items={tabs} value={tab} onChange={setTab} />

      {tab === "pending" && rows.length > 0 && (
        <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
          {t("bookings.awaiting", { count: rows.length })}
        </p>
      )}

      {rows.length === 0 ? (
        <div className="empty-state" style={{ minHeight: 280 }}>
          <div className="st-ico amber" style={{ width: 48, height: 48 }}>
            <Icon name="inbox" />
          </div>
          <div>
            <div className="h3">{emptyText[tab][0]}</div>
            <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
              {emptyText[tab][1]}
            </div>
          </div>
        </div>
      ) : (
        <div className="stack" style={{ gap: 14 }}>
          {rows.map((b) => (
            <div key={b.id} className="card card-pad">
              <div className="between wrap" style={{ gap: 16 }}>
                <div className="row" style={{ gap: 14 }}>
                  <Avatar
                    initial={avatarInitial(b.member?.name)}
                    src={b.member?.avatar}
                    alt={b.member?.name}
                    size="lg"
                    round
                  />
                  <div>
                    <div className="row" style={{ gap: 10, alignItems: "center" }}>
                      <div className="h3" style={{ fontSize: "var(--fs-lg)" }}>
                        {b.member?.name ?? "—"}
                      </div>
                      <StatusBadge status={b.status} locale={locale} />
                    </div>
                    <div className="muted">{b.member?.specialty ?? "—"}</div>
                    <div className="row wrap" style={{ gap: 14, marginTop: 8 }}>
                      <span
                        className="row muted-3"
                        style={{ gap: 5, fontSize: "var(--fs-sm)" }}
                      >
                        <Icon name="calendar" size={15} />
                        <span className="tnum ltr">{shortDate(b.created_at)}</span>
                      </span>
                      <span
                        className="row muted-3"
                        style={{ gap: 5, fontSize: "var(--fs-sm)" }}
                      >
                        <Icon name="grid" size={15} />
                        {b.preferred_seat_type
                          ? tc(`seatType.${b.preferred_seat_type}` as never)
                          : t("bookings.anySeat")}
                      </span>
                    </div>
                    {b.message && (
                      <p
                        className="muted"
                        style={{ marginTop: 10, fontSize: "var(--fs-sm)" }}
                      >
                        “{b.message}”
                      </p>
                    )}
                    {b.status === "rejected" && b.rejection_reason && (
                      <p
                        className="muted-3"
                        style={{ marginTop: 8, fontSize: "var(--fs-sm)" }}
                      >
                        {t("bookings.rejectedReasonLabel")}: {b.rejection_reason}
                      </p>
                    )}
                  </div>
                </div>

                {b.status === "pending" && (
                  <div className="row" style={{ gap: 10 }}>
                    <Button
                      variant="secondary"
                      icon="x"
                      onClick={() => setRejectFor(b)}
                    >
                      {t("bookings.reject")}
                    </Button>
                    <Button
                      variant="primary"
                      icon="check"
                      onClick={() => setApproveFor(b)}
                    >
                      {t("bookings.approve")}
                    </Button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {approveFor && (
        <ApproveBookingModal
          memberName={approveFor.member?.name ?? "—"}
          seats={availableSeats}
          pending={pending}
          onConfirm={doApprove}
          onClose={() => setApproveFor(null)}
        />
      )}
      {rejectFor && (
        <RejectBookingModal
          pending={pending}
          onConfirm={doReject}
          onClose={() => setRejectFor(null)}
        />
      )}
    </div>
  );
}
