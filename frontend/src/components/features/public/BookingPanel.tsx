"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { useRouter } from "@/i18n/navigation";
import { Field } from "@/components/ui/Field";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { Button } from "@/components/ui/Button";
import { useAuth } from "@/components/providers/AuthProvider";
import { useToast } from "@/components/providers/ToastProvider";
import type { PublicDict } from "./i18n";
import { submitBookingAction } from "./actions";
import { seatTypeLabel, toNumber } from "./helpers";
import type { SeatType, SeatTypePrice } from "@/lib/types";

export interface BookingPanelProps {
  workspaceId: string;
  /** Fallback monthly price used when no seat type provides one. */
  price: number;
  /** Per-seat-type pricing rows (only enabled rows are bookable). */
  seatTypes?: SeatTypePrice[];
  dict: PublicDict;
  /** Deep-linked from a partner: scroll to + focus the booking action on mount. */
  autoBook?: boolean;
}

/** Sticky booking panel — submits a request or routes guests to login. */
export function BookingPanel({
  workspaceId,
  price,
  seatTypes,
  dict,
  autoBook = false,
}: BookingPanelProps) {
  const d = dict.detail;
  const c = dict.common;
  const { isAuthenticated, isLoading, role } = useAuth();
  const router = useRouter();
  const { toast } = useToast();
  const [isPending, startTransition] = useTransition();
  const messageRef = useRef<HTMLTextAreaElement>(null);

  // Partner deep-link (`?book=1`): bring the booking action into view + focus it.
  useEffect(() => {
    if (!autoBook) return;
    messageRef.current?.scrollIntoView({ behavior: "smooth", block: "center" });
    messageRef.current?.focus({ preventScroll: true });
  }, [autoBook]);

  // Only enabled types are bookable; fall back to the three defaults if none.
  const bookable = (seatTypes ?? []).filter((row) => row.enabled);
  const options: SeatType[] =
    bookable.length > 0
      ? bookable.map((row) => row.type)
      : ["flexible", "fixed", "private_office"];

  const [seatType, setSeatType] = useState<SeatType>(options[0]);
  const [message, setMessage] = useState("");

  // Price shown reflects the selected type's monthly price when available.
  const selectedRow = bookable.find((row) => row.type === seatType);
  const displayPrice =
    selectedRow?.price_monthly != null
      ? toNumber(selectedRow.price_monthly)
      : price;

  // Preserve the booking intent across login so the user returns booking-ready.
  const redirectTarget = `/workspaces/${workspaceId}?book=1`;
  // Only freelancers can book; treat any other authenticated role as a guest CTA.
  const canBook = isAuthenticated && role === "freelancer";

  function handleSubmit() {
    if (!canBook) {
      router.push(`/login?redirect=${encodeURIComponent(redirectTarget)}`);
      return;
    }
    startTransition(async () => {
      const result = await submitBookingAction({
        workspace_id: workspaceId,
        preferred_seat_type: seatType,
        message,
      });
      if (result.ok) {
        setMessage("");
        toast({ tone: "ok", title: d.bookingSuccess });
      } else if (result.status === 401) {
        router.push(`/login?redirect=${encodeURIComponent(redirectTarget)}`);
      } else {
        toast({ tone: "err", title: result.message || d.bookingError });
      }
    });
  }

  return (
    <aside className="detail-aside">
      <div className="card book-card">
        <div className="row" style={{ gap: 8, alignItems: "baseline" }}>
          <span className="ws-price tnum" style={{ fontSize: "2rem" }}>
            {c.currency}
            {displayPrice}
          </span>
          <span className="muted">{c.perMonth}</span>
        </div>

        <div className="divider" style={{ margin: "16px 0" }} />

        <Field label={d.seatTypeLabel}>
          <Select
            value={seatType}
            onChange={(ev) => setSeatType(ev.target.value as SeatType)}
          >
            {options.map((type) => (
              <option key={type} value={type}>
                {seatTypeLabel(type, d)}
              </option>
            ))}
          </Select>
        </Field>

        <div style={{ height: 12 }} />

        <Field label={d.messageLabel}>
          <Textarea
            ref={messageRef}
            rows={3}
            value={message}
            onChange={(ev) => setMessage(ev.target.value)}
            placeholder={d.messagePlaceholder}
          />
        </Field>

        <div style={{ height: 16 }} />

        <Button
          variant="primary"
          size="lg"
          block
          icon="calendar"
          onClick={handleSubmit}
          loading={isPending}
          disabled={isLoading || isPending}
        >
          {canBook ? d.request : d.loginToBook}
        </Button>

        <p
          className="muted-3"
          style={{ fontSize: "var(--fs-xs)", textAlign: "center", marginTop: 10 }}
        >
          {d.bookingNote}
        </p>
      </div>
    </aside>
  );
}
