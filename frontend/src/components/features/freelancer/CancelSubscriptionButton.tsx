"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import { cancelSubscriptionAction } from "@/lib/actions/freelancer";

export interface CancelSubscriptionButtonProps {
  subscriptionId: string;
  workspaceName: string;
  /** Pre-formatted end date (or null when the plan has no end date). */
  endDate: string | null;
}

/**
 * "Cancel subscription" CTA. Opens a confirmation Modal that explains the
 * consequence (seat access stops after the end date), then calls the
 * `cancelSubscriptionAction` Server Action and surfaces the result as a toast.
 */
export function CancelSubscriptionButton({
  subscriptionId,
  workspaceName,
  endDate,
}: CancelSubscriptionButtonProps) {
  const t = useTranslations("freelancer.subscription");
  const { toast } = useToast();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();

  function confirm() {
    startTransition(async () => {
      const result = await cancelSubscriptionAction(subscriptionId);
      if (result.ok) {
        setOpen(false);
        toast({ tone: "ok", title: t("cancelSuccess") });
        router.refresh();
      } else {
        toast({ tone: "err", title: t("cancelError"), body: result.message });
      }
    });
  }

  const body = endDate
    ? t("cancelModalBody", { workspace: workspaceName, date: endDate })
    : t("cancelModalBodyNoDate", { workspace: workspaceName });

  return (
    <>
      <Button variant="ghost" onClick={() => setOpen(true)}>
        {t("cancelCta")}
      </Button>

      {open && (
        <Modal
          title={t("cancelModalTitle")}
          icon="alert"
          onClose={() => (pending ? undefined : setOpen(false))}
          footer={
            <>
              <Button
                variant="ghost"
                onClick={() => setOpen(false)}
                disabled={pending}
              >
                {t("keepPlan")}
              </Button>
              <Button variant="danger" onClick={confirm} loading={pending}>
                {t("confirmCancel")}
              </Button>
            </>
          }
        >
          <p className="muted" style={{ lineHeight: 1.7 }}>
            {body}
          </p>
        </Modal>
      )}
    </>
  );
}
