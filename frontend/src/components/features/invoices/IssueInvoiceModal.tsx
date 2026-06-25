"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { issueInvoice } from "@/lib/actions/invoices";
import type { PlanType } from "@/lib/types";

/** Member shape shown in the rich member picker. */
export interface InvoiceMemberOption {
  id: string;
  name: string;
  avatar: string | null;
  planType: PlanType;
  seatNumber: string | null;
  monthlyPrice: number;
  package: string | null;
}

export interface IssueInvoiceModalProps {
  members: InvoiceMemberOption[];
  onClose: () => void;
}

interface FormState {
  memberId: string;
  amount: string;
  dueDate: string;
  notes: string;
}

/** Owner: issue a manual invoice for an active member. */
export function IssueInvoiceModal({ members, onClose }: IssueInvoiceModalProps) {
  const t = useTranslations("invoices.owner");
  const tc = useTranslations("common");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [form, setForm] = useState<FormState>({
    memberId: members[0]?.id ?? "",
    amount: members[0] ? String(members[0].monthlyPrice) : "",
    dueDate: "",
    notes: "",
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  /** Selecting a member pre-fills the amount with their subscription price. */
  const selectMember = (m: InvoiceMemberOption) =>
    setForm((f) => ({ ...f, memberId: m.id, amount: String(m.monthlyPrice) }));

  const submit = () => {
    startTransition(async () => {
      const res = await issueInvoice({
        member_id: form.memberId,
        amount: Number(form.amount) || 0,
        due_date: form.dueDate,
        notes: form.notes.trim() || null,
      });

      if (res.ok) {
        toast({ tone: "ok", title: t("toast.issued") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({
          tone: "err",
          title: t("toast.issueFailed"),
          body: res.message,
        });
      }
    });
  };

  const hasMembers = members.length > 0;

  return (
    <Modal
      title={t("issueModal.title")}
      icon="receipt"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("issueModal.cancel")}
          </Button>
          <Button
            variant="primary"
            icon="send"
            loading={pending}
            disabled={!hasMembers}
            onClick={submit}
          >
            {t("issueModal.submit")}
          </Button>
        </>
      }
    >
      {hasMembers ? (
        <div className="stack" style={{ gap: 16 }}>
          <Field label={t("issueModal.member")} error={errors.member_id?.[0]}>
            <MemberPicker
              members={members}
              value={form.memberId}
              onChange={selectMember}
              meta={(m) =>
                [
                  m.package,
                  m.seatNumber ? `#${m.seatNumber}` : null,
                  tc(`planType.${m.planType}`),
                  `${m.monthlyPrice} ${tc("currency")}`,
                ]
                  .filter(Boolean)
                  .join(" · ")
              }
            />
          </Field>

          <div className="grid2">
            <Field label={t("issueModal.amount")} error={errors.amount?.[0]}>
              <Input
                className="tnum ltr"
                inputMode="decimal"
                placeholder="0.00"
                value={form.amount}
                onChange={(e) => set("amount", e.target.value)}
              />
            </Field>
            <Field label={t("issueModal.dueDate")} error={errors.due_date?.[0]}>
              <Input
                className="ltr"
                type="date"
                value={form.dueDate}
                onChange={(e) => set("dueDate", e.target.value)}
              />
            </Field>
          </div>

          <Field
            label={t("issueModal.notes")}
            error={errors.notes?.[0]}
            optional
          >
            <Textarea
              rows={3}
              placeholder={t("issueModal.notesPlaceholder")}
              value={form.notes}
              onChange={(e) => set("notes", e.target.value)}
            />
          </Field>
        </div>
      ) : (
        <p className="muted">{t("issueModal.noMembers")}</p>
      )}
    </Modal>
  );
}

interface MemberPickerProps {
  members: InvoiceMemberOption[];
  value: string;
  onChange: (member: InvoiceMemberOption) => void;
  meta: (member: InvoiceMemberOption) => string;
}

/**
 * Avatar-rich member dropdown: each option shows the member's photo, name, and a
 * meta line (internet package · seat · plan · price). A native <select> can't
 * render avatars, so this is a custom popover.
 */
function MemberPicker({ members, value, onChange, meta }: MemberPickerProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const selected = members.find((m) => m.id === value) ?? null;

  useEffect(() => {
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  return (
    <div ref={ref} style={{ position: "relative" }}>
      <button
        type="button"
        className="input row between"
        style={{ width: "100%", gap: 10, cursor: "pointer" }}
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        {selected ? (
          <MemberRow member={selected} meta={meta(selected)} />
        ) : (
          <span className="muted">—</span>
        )}
        <Icon name="chevD" size={16} className="muted" />
      </button>

      {open && (
        <div
          role="listbox"
          className="card"
          style={{
            position: "absolute",
            insetInlineStart: 0,
            insetInlineEnd: 0,
            top: "calc(100% + 6px)",
            zIndex: 30,
            maxHeight: 280,
            overflowY: "auto",
            padding: 6,
            boxShadow: "var(--shadow-lg, 0 8px 24px rgba(0,0,0,.12))",
          }}
        >
          {members.map((m) => (
            <button
              key={m.id}
              type="button"
              role="option"
              aria-selected={m.id === value}
              className="row"
              style={{
                width: "100%",
                gap: 10,
                padding: "8px 10px",
                borderRadius: 8,
                cursor: "pointer",
                background:
                  m.id === value ? "var(--surface-2, rgba(0,0,0,.04))" : "transparent",
              }}
              onClick={() => {
                onChange(m);
                setOpen(false);
              }}
            >
              <MemberRow member={m} meta={meta(m)} />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function MemberRow({
  member,
  meta,
}: {
  member: InvoiceMemberOption;
  meta: string;
}) {
  return (
    <span className="row" style={{ gap: 10, minWidth: 0, alignItems: "center" }}>
      <Avatar initial={member.name.charAt(0)} src={member.avatar} alt={member.name} round />
      <span className="stack" style={{ gap: 2, minWidth: 0, textAlign: "start" }}>
        <span style={{ fontWeight: 600 }}>{member.name}</span>
        {meta && (
          <span
            className="muted-3"
            style={{
              fontSize: "var(--fs-xs)",
              whiteSpace: "nowrap",
              overflow: "hidden",
              textOverflow: "ellipsis",
            }}
          >
            {meta}
          </span>
        )}
      </span>
    </span>
  );
}
