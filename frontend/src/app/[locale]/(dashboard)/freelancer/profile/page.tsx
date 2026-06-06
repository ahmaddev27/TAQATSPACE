import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, User } from "@/lib/types";
import { ProfileScreen } from "@/components/features/profile/ProfileScreen";

/** First grapheme of a name (Arabic + Latin safe), uppercased. */
function avatarInitial(name: string): string {
  const first = Array.from(name.trim())[0] ?? "?";
  return first.toUpperCase();
}

export default async function FreelancerProfilePage() {
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
        showFreelancerFields: true,
        revalidate: ["/freelancer/profile", "/freelancer"],
      }}
    />
  );
}
