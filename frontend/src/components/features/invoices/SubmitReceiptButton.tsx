"use client";

import { useRef, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { submitReceipt } from "@/lib/actions/invoices";

const MAX_SIZE = 5 * 1024 * 1024; // 5MB

/** Freelancer: pick a payment receipt and upload it for an unpaid invoice. */
export function SubmitReceiptButton({ invoiceId }: { invoiceId: string }) {
  const t = useTranslations("invoices.member");
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
      const res = await submitReceipt(invoiceId, file);
      if (res.ok) {
        toast({ tone: "ok", title: t("receipt.submitted") });
      } else {
        toast({ tone: "err", title: t("receipt.failed"), body: res.message });
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
        {t("receipt.upload")}
      </Button>
    </>
  );
}
