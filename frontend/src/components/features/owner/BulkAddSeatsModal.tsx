"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { useToast } from "@/components/providers/ToastProvider";
import { bulkAddSeats } from "@/lib/actions/owner";
import type { Branch, SeatKind, SeatType } from "@/lib/types";

const MAX_COUNT = 200;
const SEAT_TYPES: SeatType[] = ["flexible", "fixed", "private_office"];
const SEAT_KINDS: SeatKind[] = ["desk", "room", "booth"];

export interface BulkAddSeatsModalProps {
  branches: Branch[];
  onClose: () => void;
}

/** Add up to 200 stations in one go (prefix + running number). */
export function BulkAddSeatsModal({ branches, onClose }: BulkAddSeatsModalProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [start, setStart] = useState("1");
  const [count, setCount] = useState("10");
  const [prefix, setPrefix] = useState("");
  const [type, setType] = useState<SeatType>("flexible");
  const [kind, setKind] = useState<SeatKind>("desk");
  const [zone, setZone] = useState("");
  const [branchId, setBranchId] = useState("");
  const [isPrivate, setIsPrivate] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const countNumber = Number(count);
  const valid =
    Number.isInteger(countNumber) &&
    countNumber >= 1 &&
    countNumber <= MAX_COUNT &&
    Number.isInteger(Number(start)) &&
    Number(start) >= 0;

  const submit = () => {
    setError(null);
    startTransition(async () => {
      const res = await bulkAddSeats({
        start: Number(start),
        count: countNumber,
        prefix: prefix.trim() || undefined,
        type,
        kind,
        zone: zone.trim() || undefined,
        branch_id: branchId || undefined,
        is_private: isPrivate,
      });
      if (res.ok) {
        toast({ tone: "ok", title: t("seats.stations.created"), body: String(countNumber) });
        onClose();
      } else {
        setError(res.message);
        toast({ tone: "err", title: t("seats.stations.createFailed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      title={t("seats.stations.bulkTitle")}
      icon="grid"
      onClose={onClose}
      closeLabel={t("seats.stations.close")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("seats.stations.cancel")}
          </Button>
          <Button loading={pending} disabled={!valid} onClick={submit}>
            {t("seats.stations.submit")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 12 }}>
        <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>
          {t("seats.stations.bulkHint")}
        </p>
        <div className="row" style={{ gap: 12 }}>
          <Field label={t("seats.stations.prefix")} htmlFor="bulk-prefix">
            <Input id="bulk-prefix" value={prefix} maxLength={20} onChange={(e) => setPrefix(e.target.value)} />
          </Field>
          <Field label={t("seats.stations.start")} htmlFor="bulk-start">
            <Input id="bulk-start" type="number" min={0} value={start} onChange={(e) => setStart(e.target.value)} />
          </Field>
          <Field label={t("seats.stations.count")} htmlFor="bulk-count">
            <Input id="bulk-count" type="number" min={1} max={MAX_COUNT} value={count} onChange={(e) => setCount(e.target.value)} />
          </Field>
        </div>
        <div className="row" style={{ gap: 12 }}>
          <Field label={t("seats.stations.type")} htmlFor="bulk-type">
            <Select id="bulk-type" value={type} onChange={(e) => setType(e.target.value as SeatType)}>
              {SEAT_TYPES.map((value) => (
                <option key={value} value={value}>
                  {t(`settings.seatTypeNames.${value}`)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("seats.stations.kind")} htmlFor="bulk-kind">
            <Select id="bulk-kind" value={kind} onChange={(e) => setKind(e.target.value as SeatKind)}>
              {SEAT_KINDS.map((value) => (
                <option key={value} value={value}>
                  {t(`seats.stations.kindNames.${value}`)}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <div className="row" style={{ gap: 12 }}>
          <Field label={t("seats.stations.zone")} htmlFor="bulk-zone">
            <Input id="bulk-zone" value={zone} maxLength={100} onChange={(e) => setZone(e.target.value)} />
          </Field>
          <Field label={t("seats.stations.branch")} htmlFor="bulk-branch">
            <Select id="bulk-branch" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
              <option value="">{t("seats.stations.branchDefault")}</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <label className="row" style={{ gap: 8 }}>
          <input type="checkbox" checked={isPrivate} onChange={(e) => setIsPrivate(e.target.checked)} />
          {t("seats.stations.isPrivate")}
        </label>
        {error && (
          <p role="alert" style={{ color: "var(--danger, #c0392b)", fontSize: "var(--fs-sm)" }}>
            {error}
          </p>
        )}
      </div>
    </Modal>
  );
}
