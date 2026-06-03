"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { useToast } from "@/components/providers/ToastProvider";
import { issueInvoice } from "@/lib/actions/invoices";

/** Minimal member shape needed to populate the member <select>. */
export interface InvoiceMemberOption {
  id: string;
  name: string;
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
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [form, setForm] = useState<FormState>({
    memberId: members[0]?.id ?? "",
    amount: "",
    dueDate: "",
    notes: "",
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

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
            <Select
              value={form.memberId}
              onChange={(e) => set("memberId", e.target.value)}
            >
              {members.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name}
                </option>
              ))}
            </Select>
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
