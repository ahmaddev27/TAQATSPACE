"use client";

import { useRef, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { uploadOwnerReceipt } from "@/lib/actions/invoices";

const MAX_SIZE = 5 * 1024 * 1024; // 5MB

/** Owner: attach a payment receipt and record the invoice as paid in one step. */
export function OwnerReceiptButton({ invoiceId }: { invoiceId: string }) {
  const t = useTranslations("invoices.owner");
  const { toast } = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const [pending, startTransition] = useTransition();

  function onPick(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = ""; // allow re-selecting the same file
    if (!file) return;

    if (file.size > MAX_SIZE) {
      toast({ tone: "err", title: t("receipt.tooLarge") });
      return;
    }

    startTransition(async () => {
      const res = await uploadOwnerReceipt(invoiceId, file);
      if (res.ok) {
        toast({ tone: "ok", title: t("receipt.uploaded") });
      } else {
        toast({ tone: "err", title: t("receipt.uploadFailed"), body: res.message });
      }
    });
  }

  return (
    <>
      <input
        ref={inputRef}
        type="file"
        accept="application/pdf,image/png,image/jpeg"
        onChange={onPick}
        style={{ display: "none" }}
        aria-hidden="true"
        tabIndex={-1}
      />
      <Button
        variant="secondary"
        size="sm"
        icon="upload"
        loading={pending}
        onClick={() => inputRef.current?.click()}
      >
        {t("actions.uploadReceipt")}
      </Button>
    </>
  );
}
