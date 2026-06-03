"use client";

import { useTransition } from "react";
import { useTranslations } from "next-intl";
import { Icon } from "@/components/ui/Icon";
import { useToast } from "@/components/providers/ToastProvider";
import { sendReminder } from "@/lib/actions/invoices";

export interface RemindButtonProps {
  invoiceId: string;
}

/**
 * "Send reminder" action. The backend rate-limits to one reminder per 24h and
 * returns 429 when called too soon; the message is surfaced via a toast.
 */
export function RemindButton({ invoiceId }: RemindButtonProps) {
  const t = useTranslations("invoices.owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const onClick = () => {
    startTransition(async () => {
      const res = await sendReminder(invoiceId);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.reminderSent") });
      } else {
        toast({
          tone: "err",
          title: t("toast.reminderFailed"),
          body: res.message,
        });
      }
    });
  };

  return (
    <button
      type="button"
      className="btn btn-ghost btn-sm"
      onClick={onClick}
      disabled={pending}
      aria-busy={pending}
    >
      {pending ? (
        <span className="btn-spinner" aria-hidden="true" />
      ) : (
        <Icon name="send" size={15} />
      )}
      {t("actions.remind")}
    </button>
  );
}
