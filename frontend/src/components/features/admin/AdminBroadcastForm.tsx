"use client";

import { useTranslations } from "next-intl";
import {
  BroadcastComposer,
  type RecipientOption,
} from "@/components/features/messaging/BroadcastComposer";
import { sendAdminBroadcast } from "@/lib/actions/admin";
import type { UserRole, UserStatus } from "@/lib/types";

export interface AdminBroadcastFormProps {
  recipients: RecipientOption[];
}

const ROLES: UserRole[] = ["freelancer", "workspace_owner", "admin"];
const STATUSES: UserStatus[] = ["active", "suspended", "pending_verification"];

/**
 * Admin (platform) broadcast composer. Segments the whole user base by account
 * role and/or status and sends through Taqat's platform accounts.
 */
export function AdminBroadcastForm({ recipients }: AdminBroadcastFormProps) {
  const t = useTranslations("broadcast.admin");

  return (
    <BroadcastComposer
      recipients={recipients}
      action={sendAdminBroadcast}
      segmentFields={[
        {
          key: "role",
          label: t("segment.role"),
          options: ROLES.map((r) => ({ value: r, label: t(`roles.${r}`) })),
        },
        {
          key: "status",
          label: t("segment.status"),
          options: STATUSES.map((s) => ({
            value: s,
            label: t(`statuses.${s}`),
          })),
        },
      ]}
    />
  );
}
