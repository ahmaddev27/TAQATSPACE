"use client";

import { useTransition } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { useToast } from "@/components/providers/ToastProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import {
  acceptCashierInvitation,
  declineCashierInvitation,
} from "@/lib/actions/cashier";
import type { CashierInvitationOffer } from "@/lib/types/auth";

interface CashierInviteCardProps {
  invitation: CashierInvitationOffer;
}

/**
 * Onboarding surface for a pending cashier invitation. Shown above the normal
 * freelancer/owner role picker when the signed-in account has an open invite:
 *  - Accept -> the account becomes a cashier; refresh + land on the terminal.
 *  - Decline -> the invite is cleared; refreshing drops the user back to the
 *    standard role choice (the card unmounts once the offer disappears).
 */
export function CashierInviteCard({ invitation }: CashierInviteCardProps) {
  const t = useTranslations("auth.onboarding.cashierInvite");
  const router = useRouter();
  const { refreshUser } = useAuth();
  const { toast } = useToast();

  const [pending, startTransition] = useTransition();
  const workspace = invitation.workspace_name ?? t("fallbackWorkspace");

  function handleAccept() {
    startTransition(async () => {
      const result = await acceptCashierInvitation(invitation.id);
      if (!result.ok) {
        toast({ tone: "err", title: t("error"), body: result.message });
        return;
      }
      toast({ tone: "ok", title: t("accepted") });
      await refreshUser();
      router.replace("/cashier");
    });
  }

  function handleDecline() {
    startTransition(async () => {
      const result = await declineCashierInvitation(invitation.id);
      if (!result.ok) {
        toast({ tone: "err", title: t("error"), body: result.message });
        return;
      }
      toast({ tone: "info", title: t("declined") });
      await refreshUser();
    });
  }

  return (
    <div className="card card-pad" style={{ maxWidth: 520, margin: "0 auto" }}>
      <h2 className="h3" style={{ marginBottom: 8 }}>
        {t("heading", { workspace })}
      </h2>
      <p className="muted" style={{ marginBottom: 16 }}>
        {t("body", { workspace })}
      </p>

      <Alert tone="info" title={t("note")} />

      <div className="row" style={{ gap: 12, marginTop: 20 }}>
        <Button
          variant="primary"
          icon="check"
          loading={pending}
          onClick={handleAccept}
          block
        >
          {t("accept")}
        </Button>
        <Button
          variant="secondary"
          icon="x"
          disabled={pending}
          onClick={handleDecline}
          block
        >
          {t("decline")}
        </Button>
      </div>
    </div>
  );
}
