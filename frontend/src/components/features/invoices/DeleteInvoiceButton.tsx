"use client";

import { useTransition } from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import { deleteInvoice } from "@/lib/actions/invoices";

export interface DeleteInvoiceButtonProps {
  invoiceId: string;
  invoiceNumber: string;
}

/** Owner action: permanently delete an invoice, behind a confirm dialog. */
export function DeleteInvoiceButton({
  invoiceId,
  invoiceNumber,
}: DeleteInvoiceButtonProps) {
  const t = useTranslations("invoices.owner");
  const confirm = useConfirm();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const onClick = async () => {
    const ok = await confirm({
      title: t("delete.title"),
      message: t("delete.message", { invoice: invoiceNumber }),
      confirmLabel: t("delete.confirm"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;

    startTransition(async () => {
      const res = await deleteInvoice(invoiceId);
      toast(
        res.ok
          ? { tone: "ok", title: t("toast.deleted") }
          : { tone: "err", title: t("toast.deleteFailed"), body: res.message },
      );
    });
  };

  return (
    <button
      type="button"
      className="btn btn-ghost btn-sm"
      onClick={onClick}
      disabled={pending}
      aria-busy={pending}
      aria-label={t("delete.title")}
    >
      {pending ? (
        <span className="btn-spinner" aria-hidden="true" />
      ) : (
        <Icon name="trash" size={15} />
      )}
    </button>
  );
}
