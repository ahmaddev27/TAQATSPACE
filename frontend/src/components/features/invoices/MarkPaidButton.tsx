"use client";

import { useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { markPaid } from "@/lib/actions/invoices";

export interface MarkPaidButtonProps {
  invoiceId: string;
}

/** Inline "Mark paid" action for an unpaid owner invoice. */
export function MarkPaidButton({ invoiceId }: MarkPaidButtonProps) {
  const t = useTranslations("invoices.owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const onClick = () => {
    startTransition(async () => {
      const res = await markPaid(invoiceId);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.paidTitle") });
      } else {
        toast({ tone: "err", title: t("toast.paidFailed"), body: res.message });
      }
    });
  };

  return (
    <Button
      variant="primary"
      size="sm"
      icon="check"
      loading={pending}
      onClick={onClick}
    >
      {t("actions.markPaid")}
    </Button>
  );
}
