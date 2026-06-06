import { setRequestLocale } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, User } from "@/lib/types";
import { ProfileScreen } from "@/components/features/profile/ProfileScreen";

export const dynamic = "force-dynamic";

/** First grapheme of a name (Arabic + Latin safe), uppercased. */
function avatarInitial(name: string): string {
  const first = Array.from(name.trim())[0] ?? "?";
  return first.toUpperCase();
}

export default async function OwnerProfilePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);

  const res = await serverFetch<ApiEnvelope<{ user: User }>>("/auth/me");
  const user = res.data.user;

  return (
    <ProfileScreen
      form={{
        defaults: {
          name: user.name,
          email: user.email,
          phone: user.phone ?? "",
          specialty: user.specialty ?? "",
          bio: user.bio ?? "",
          avatar: user.avatar,
        },
        avatarInitial: avatarInitial(user.name),
        revalidate: ["/owner/profile", "/owner"],
      }}
    />
  );
}
