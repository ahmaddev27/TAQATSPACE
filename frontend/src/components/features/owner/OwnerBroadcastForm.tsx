"use client";

import { useTranslations } from "next-intl";
import {
  BroadcastComposer,
  type RecipientOption,
} from "@/components/features/messaging/BroadcastComposer";
import { sendOwnerBroadcast } from "@/lib/actions/owner";
import type { SubscriptionStatus } from "@/lib/types";

export interface OwnerBroadcastFormProps {
  recipients: RecipientOption[];
}

const SUB_STATUSES: SubscriptionStatus[] = [
  "active",
  "pending",
  "expired",
  "suspended",
  "cancelled",
];

/**
 * Owner (workspace) broadcast composer. Segments the owner's OWN members by
 * subscription status and sends through the workspace's accounts (or the
 * platform when `use_platform`). Recipients are scoped to the workspace
 * server-side, so the "specific" picker can only reach this workspace's members.
 */
export function OwnerBroadcastForm({ recipients }: OwnerBroadcastFormProps) {
  const t = useTranslations("broadcast.owner");

  return (
    <BroadcastComposer
      recipients={recipients}
      action={sendOwnerBroadcast}
      segmentFields={[
        {
          key: "status",
          label: t("segment.status"),
          options: SUB_STATUSES.map((s) => ({
            value: s,
            label: t(`statuses.${s}`),
          })),
        },
      ]}
    />
  );
}
