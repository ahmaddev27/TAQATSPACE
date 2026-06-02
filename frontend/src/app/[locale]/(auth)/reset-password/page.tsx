import { ResetPasswordForm } from "@/components/features/auth/ResetPasswordForm";

export default async function ResetPasswordPage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string; email?: string }>;
}) {
  const { token = "", email = "" } = await searchParams;

  return (
    <div className="auth-center">
      <ResetPasswordForm token={token} email={email} />
    </div>
  );
}
