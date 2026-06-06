"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Tabs } from "@/components/ui/Tabs";
import { ProfileForm, type ProfileFormProps } from "./ProfileForm";
import { ChangePasswordSection } from "./ChangePasswordSection";

export interface ProfileScreenProps {
  /** Personal-info form props, forwarded as-is to the shared ProfileForm. */
  form: ProfileFormProps;
}

/**
 * Centered, tabbed profile screen shared by every role (admin / freelancer /
 * owner). One tab edits personal info (avatar + fields), the other changes the
 * password. The container is width-constrained and centered so the form reads
 * as a focused account page rather than a full-bleed dashboard table.
 */
export function ProfileScreen({ form }: ProfileScreenProps) {
  const t = useTranslations("profile");
  const [tab, setTab] = useState<"info" | "password">("info");

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
