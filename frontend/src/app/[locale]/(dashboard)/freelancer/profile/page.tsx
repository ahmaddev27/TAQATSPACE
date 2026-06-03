import { getTranslations } from "next-intl/server";
import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, User } from "@/lib/types";
import { ProfileForm } from "@/components/features/freelancer/ProfileForm";
import { ChangePasswordSection } from "@/components/features/freelancer/ChangePasswordSection";

/** First grapheme of a name (Arabic + Latin safe), uppercased. */
function avatarInitial(name: string): string {
  const first = Array.from(name.trim())[0] ?? "?";
  return first.toUpperCase();
}

export default async function FreelancerProfilePage() {
  const [res, t] = await Promise.all([
    serverFetch<ApiEnvelope<{ user: User }>>("/auth/me"),
    getTranslations("freelancer.profile"),
  ]);

  const user = res.data.user;

  return (
    <div className="stack" style={{ gap: 24, maxWidth: 760 }}>
      <div className="stack" style={{ gap: 4 }}>
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      <ProfileForm
        defaults={{
          name: user.name,
          email: user.email,
          phone: user.phone ?? "",
          specialty: user.specialty ?? "",
          bio: user.bio ?? "",
          avatar: user.avatar,
        }}
        avatarInitial={avatarInitial(user.name)}
      />

      <ChangePasswordSection />
    </div>
  );
}
