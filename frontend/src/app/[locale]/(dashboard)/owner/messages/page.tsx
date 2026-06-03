import { setRequestLocale } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import { listThreads } from "@/lib/api/messages";
import { listMembers } from "@/lib/api/members";
import { MessagesView } from "@/components/features/messaging/MessagesView";
import type { ApiEnvelope, User } from "@/lib/types";

export const dynamic = "force-dynamic";

export default async function OwnerMessagesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  // Threads, the signed-in owner id, and the active-member count load in
  // parallel; the owner id determines which side each bubble sits on.
  const [threads, me, activeMembers] = await Promise.all([
    listThreads(),
    serverFetch<ApiEnvelope<{ user: User }>>("/auth/me"),
    listMembers({ status: "active", per_page: 1 }),
  ]);

  return (
    <div className="page">
      <MessagesView
        initialThreads={threads}
        ownerId={me.data.user.id}
        recipientCount={activeMembers.meta.total}
      />
    </div>
  );
}
