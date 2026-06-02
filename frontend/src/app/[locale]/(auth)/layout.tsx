import type { ReactNode } from "react";
import { LanguageToggle } from "@/components/layout/LanguageToggle";
import { ThemeToggle } from "@/components/layout/ThemeToggle";

/** Auth chrome: a floating top bar with Language + Theme toggles. */
export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="auth-shell">
      <div className="auth-topbar">
        <LanguageToggle />
        <ThemeToggle />
      </div>
      {children}
    </div>
  );
}
