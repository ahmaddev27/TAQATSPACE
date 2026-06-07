"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Tabs } from "@/components/ui/Tabs";
import { ProfileForm, type ProfileFormProps } from "./ProfileForm";
import { ChangePasswordSection } from "./ChangePasswordSection";

export interface ProfileScreenProps {
  /** Personal-info form props, forwarded as-is to the shared ProfileForm. */
  form: ProfileFormProps;
  /**
   * Whether this account owns a local password (the internal admin). SSO
   * users (owner / freelancer) manage credentials at the IdP, so the
   * password tab is hidden for them entirely.
   */
  canChangePassword?: boolean;
}

/**
 * Centered profile screen shared by every role (admin / freelancer / owner).
 * It always shows personal info (avatar + fields); the password tab appears
 * only for accounts that own a local password — i.e. the internal admin. SSO
 * accounts manage credentials at the IdP, so they see the info form with no
 * tabs. The container is width-constrained and centered so the form reads as a
 * focused account page rather than a full-bleed dashboard table.
 */
export function ProfileScreen({ form, canChangePassword = false }: ProfileScreenProps) {
  const t = useTranslations("profile");
  const [tab, setTab] = useState<"info" | "password">("info");

  if (!canChangePassword) {
    return (
      <div className="profile-screen">
        <div className="stack" style={{ gap: 4 }}>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted">{t("subtitle")}</p>
        </div>

        <ProfileForm {...form} />
      </div>
    );
  }

  const tabs = [
    { id: "info", label: t("tabInfo") },
    { id: "password", label: t("tabPassword") },
  ];

  return (
    <div className="profile-screen">
      <div className="stack" style={{ gap: 4 }}>
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <Tabs items={tabs} value={tab} onChange={(id) => setTab(id as typeof tab)} />

      {tab === "info" ? (
        <ProfileForm {...form} />
      ) : (
        <ChangePasswordSection />
      )}
    </div>
  );
}
