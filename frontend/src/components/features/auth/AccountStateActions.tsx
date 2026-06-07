"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { useAuth } from "@/components/providers/AuthProvider";
import { Icon } from "@/components/ui/Icon";

/**
 * Footer actions for the dashboard "account not active" gate card. Rendered as a
 * client island because the surrounding layout is a Server Component, while
 * sign-out lives in the client AuthProvider (clears cookies + handles SSO single
 * logout).
 */
export function AccountStateActions() {
  const t = useTranslations("auth.accountState");
  const { logout } = useAuth();

  return (
    <div className="row" style={{ gap: 12, justifyContent: "center" }}>
      <Link className="btn btn-secondary" href="/">
        {t("backHome")}
      </Link>
      <button type="button" className="btn btn-ghost" onClick={() => void logout()}>
        <Icon name="logout" size={17} />
        {t("logout")}
      </button>
    </div>
  );
}
